<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Http\Controllers;

use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\QuestionBank;
use App\Modules\Assessment\Models\QuestionOption;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Catalog\Models\Program;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Services\ClassAccess;
use App\Support\Content\RichText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Bank soal per program (FR-ASM-001, SEC-EXAM-10): hanya Admin Akademik/Super Admin atau
 * trainer pengampu program; setiap tampilan bank soal tercatat di audit. Soal yang sudah
 * dipakai tidak diubah di tempat — perubahan menaikkan versi (snapshot attempt tetap).
 */
final class QuestionBankController
{
    public function __construct(
        private readonly ClassAccess $access,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request, Program $program): View
    {
        $user = $this->authorize($request, $program);
        $banks = QuestionBank::query()->where('program_id', $program->id)->withCount(['questions', 'questions as active_questions_count' => fn ($q) => $q->where('is_active', true)])->orderBy('name')->get();

        return view('banks.index', ['program' => $program, 'banks' => $banks, 'workspace' => $this->access->workspaceFor($user)]);
    }

    public function store(Request $request, Program $program): RedirectResponse
    {
        $user = $this->authorize($request, $program);
        $data = $request->validate(['name' => ['required', 'string', 'min:3', 'max:200']]);
        $bank = new QuestionBank;
        $bank->forceFill(['program_id' => $program->id, 'name' => trim($data['name']), 'created_by' => $user->id])->save();
        $this->audit->record('question_bank.created', $user, 'question_bank', $bank->id, ['program_id' => $program->id]);

        return redirect()->route('banks.show', $bank)->with('status', 'Bank soal dibuat.');
    }

    public function show(Request $request, QuestionBank $bank): View
    {
        $user = $this->authorize($request, $bank->program);
        $questions = Question::query()->with('options')->where('question_bank_id', $bank->id)->orderByDesc('is_active')->orderBy('created_at')->paginate(25);
        $this->audit->record('question_bank.viewed', $user, 'question_bank', $bank->id, ['page' => $questions->currentPage()]);

        return view('banks.show', ['bank' => $bank, 'questions' => $questions, 'workspace' => $this->access->workspaceFor($user)]);
    }

    public function updateBank(Request $request, QuestionBank $bank): RedirectResponse
    {
        $user = $this->authorize($request, $bank->program);
        $data = $request->validate(['name' => ['required', 'string', 'min:3', 'max:200']]);
        $bank->forceFill(['name' => trim($data['name'])])->save();
        $this->audit->record('question_bank.renamed', $user, 'question_bank', $bank->id, ['name' => $bank->name]);

        return redirect()->route('banks.show', $bank)->with('status', 'Nama bank soal diperbarui.');
    }

    /** Hapus bank soal yang tidak dipakai asesmen dan soalnya belum pernah dijawab. */
    public function destroyBank(Request $request, QuestionBank $bank): RedirectResponse
    {
        $user = $this->authorize($request, $bank->program);
        if (DB::table('assessments')->where('question_bank_id', $bank->id)->exists()) {
            throw ValidationException::withMessages(['bank' => 'Bank soal dipakai asesmen. Ganti bank soal pada asesmen tersebut terlebih dahulu.']);
        }
        $questionIds = Question::query()->where('question_bank_id', $bank->id)->pluck('id');
        if ($questionIds->isNotEmpty() && DB::table('attempt_answers')->whereIn('question_id', $questionIds)->exists()) {
            throw ValidationException::withMessages(['bank' => 'Soal di bank ini sudah pernah dijawab peserta sehingga bank tidak dapat dihapus.']);
        }
        $program = $bank->program;
        $bank->delete();
        $this->audit->record('question_bank.deleted', $user, 'question_bank', $bank->id, ['program_id' => $program->id, 'name' => $bank->name]);

        return redirect()->route('banks.index', $program)->with('status', 'Bank soal "'.$bank->name.'" dihapus.');
    }

    public function create(Request $request, QuestionBank $bank): View
    {
        $user = $this->authorize($request, $bank->program);
        $type = array_key_exists((string) $request->query('tipe'), Question::TYPES) ? (string) $request->query('tipe') : 'single_choice';

        return view('banks.question-form', ['bank' => $bank, 'question' => (new Question)->forceFill(['type' => $type, 'difficulty' => 3, 'points' => 1]), 'options' => [], 'answers' => '', 'workspace' => $this->access->workspaceFor($user)]);
    }

    public function storeQuestion(Request $request, QuestionBank $bank): RedirectResponse
    {
        $user = $this->authorize($request, $bank->program);
        $type = (string) $request->input('type');
        $data = $this->validated($request, $type);

        DB::transaction(function () use ($bank, $type, $data, $user): void {
            $question = new Question;
            $question->forceFill($this->attributes($data, $type) + ['question_bank_id' => $bank->id, 'type' => $type, 'created_by' => $user->id, 'version' => 1])->save();
            $this->syncOptions($question, $type, $data);
            $this->audit->record('question.created', $user, 'question', $question->id, ['bank_id' => $bank->id, 'type' => $type]);
        });

        return redirect()->route('banks.show', $bank)->with('status', 'Soal ditambahkan.');
    }

    public function edit(Request $request, Question $question): View
    {
        $user = $this->authorize($request, $question->bank->program);
        $question->load('options');
        $options = $question->options->map(fn (QuestionOption $option) => ['body' => html_entity_decode(strip_tags($option->body_html), ENT_QUOTES), 'correct' => $option->is_correct, 'right' => $option->match_text])->all();

        return view('banks.question-form', [
            'bank' => $question->bank, 'question' => $question, 'options' => $options,
            'answers' => implode("\n", $question->accepted_answers_encrypted ?? []), 'workspace' => $this->access->workspaceFor($user),
        ]);
    }

    /**
     * Soal yang sudah dipakai attempt disalin sebagai versi baru (soal lama dinonaktifkan)
     * sehingga penilaian attempt lama tetap konsisten dengan versi yang di-snapshot.
     */
    public function update(Request $request, Question $question): RedirectResponse
    {
        $user = $this->authorize($request, $question->bank->program);
        $data = $this->validated($request, $question->type);
        $used = DB::table('attempt_answers')->where('question_id', $question->id)->exists()
            || DB::table('exam_attempts')->whereJsonContains('question_order', $question->id)->exists();

        DB::transaction(function () use ($question, $data, $used, $user): void {
            if ($used) {
                $question->forceFill(['is_active' => false])->save();
                $copy = new Question;
                $copy->forceFill($this->attributes($data, $question->type) + [
                    'question_bank_id' => $question->question_bank_id, 'type' => $question->type,
                    'created_by' => $user->id, 'version' => $question->version + 1,
                ])->save();
                $this->syncOptions($copy, $question->type, $data);
                $this->audit->record('question.versioned', $user, 'question', $copy->id, ['previous_id' => $question->id]);

                return;
            }

            $question->forceFill($this->attributes($data, $question->type))->save();
            $this->syncOptions($question, $question->type, $data);
            $this->audit->record('question.updated', $user, 'question', $question->id);
        });

        return redirect()->route('banks.show', $question->question_bank_id)->with('status', $used ? 'Soal sudah dipakai: disimpan sebagai versi baru, versi lama dinonaktifkan.' : 'Soal diperbarui.');
    }

    /** Hapus soal yang belum pernah muncul di attempt; soal terpakai cukup dinonaktifkan. */
    public function destroyQuestion(Request $request, Question $question): RedirectResponse
    {
        $user = $this->authorize($request, $question->bank->program);
        $used = DB::table('attempt_answers')->where('question_id', $question->id)->exists()
            || DB::table('exam_attempts')->whereJsonContains('question_order', $question->id)->exists();
        if ($used) {
            throw ValidationException::withMessages(['question' => 'Soal sudah muncul di ujian peserta sehingga tidak dapat dihapus — nonaktifkan saja.']);
        }
        $bankId = $question->question_bank_id;
        $question->delete();
        $this->audit->record('question.deleted', $user, 'question', $question->id, ['bank_id' => $bankId]);

        return redirect()->route('banks.show', $bankId)->with('status', 'Soal dihapus.');
    }

    public function toggle(Request $request, Question $question): RedirectResponse
    {
        $user = $this->authorize($request, $question->bank->program);
        $question->forceFill(['is_active' => ! $question->is_active])->save();
        $this->audit->record($question->is_active ? 'question.activated' : 'question.deactivated', $user, 'question', $question->id);

        return back()->with('status', $question->is_active ? 'Soal diaktifkan.' : 'Soal dinonaktifkan.');
    }

    private function authorize(Request $request, Program $program): User
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($this->access->canManageQuestionBank($user, $program), 404);

        return $user;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, string $type): array
    {
        if (! array_key_exists($type, Question::TYPES)) {
            throw ValidationException::withMessages(['type' => 'Tipe soal tidak valid.']);
        }

        $rules = [
            'stem_md' => ['required', 'string', 'max:10000'],
            'explanation_md' => ['nullable', 'string', 'max:10000'],
            'difficulty' => ['required', 'integer', 'between:1,5'],
            'points' => ['required', 'numeric', 'min:0.5', 'max:100'],
            'tags' => ['nullable', 'string', 'max:300'],
        ];
        if (in_array($type, ['single_choice', 'multiple_choice'], true)) {
            $rules += ['options' => ['required', 'array', 'min:2', 'max:8'], 'options.*.body' => ['nullable', 'string', 'max:1000'], 'options.*.correct' => ['nullable', 'boolean']];
        } elseif ($type === 'true_false') {
            $rules += ['correct_answer' => ['required', Rule::in(['true', 'false'])]];
        } elseif ($type === 'short_answer') {
            $rules += ['accepted_answers' => ['required', 'string', 'max:2000']];
        } elseif ($type === 'matching') {
            $rules += ['pairs' => ['required', 'array', 'min:2', 'max:10'], 'pairs.*.left' => ['nullable', 'string', 'max:500'], 'pairs.*.right' => ['nullable', 'string', 'max:500']];
        } elseif ($type === 'essay') {
            $rules += ['rubric' => ['nullable', 'array', 'max:8'], 'rubric.*.name' => ['nullable', 'string', 'max:120'], 'rubric.*.max' => ['nullable', 'numeric', 'between:0,1000'], 'rubric.*.description' => ['nullable', 'string', 'max:300']];
        }

        $data = $request->validate($rules);

        if ($type === 'matching') {
            $pairs = array_values(array_filter($data['pairs'], fn (array $pair): bool => trim((string) ($pair['left'] ?? '')) !== '' && trim((string) ($pair['right'] ?? '')) !== ''));
            if (count($pairs) < 2) {
                throw ValidationException::withMessages(['pairs' => 'Isi minimal dua pasangan (kiri dan kanan).']);
            }
            $data['pairs'] = $pairs;
        }
        if ($type === 'essay') {
            $rubric = [];
            foreach ($data['rubric'] ?? [] as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name !== '') {
                    $rubric[] = array_filter(['name' => $name, 'max' => (float) ($row['max'] ?? 0), 'description' => trim((string) ($row['description'] ?? '')) ?: null], fn ($v) => $v !== null);
                }
            }
            $data['rubric'] = $rubric;
        }

        if (in_array($type, ['single_choice', 'multiple_choice'], true)) {
            $options = array_values(array_filter($data['options'], fn (array $option): bool => trim((string) ($option['body'] ?? '')) !== ''));
            $correct = count(array_filter($options, fn (array $option): bool => (bool) ($option['correct'] ?? false)));
            if (count($options) < 2) {
                throw ValidationException::withMessages(['options' => 'Isi minimal dua opsi jawaban.']);
            }
            if ($correct === 0 || ($type === 'single_choice' && $correct !== 1)) {
                throw ValidationException::withMessages(['options' => $type === 'single_choice' ? 'Tandai tepat satu jawaban benar.' : 'Tandai minimal satu jawaban benar.']);
            }
            $data['options'] = $options;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, string $type): array
    {
        $tags = array_slice(array_filter(array_map(fn (string $t): string => Str::limit(mb_strtolower(trim($t)), 40, ''), explode(',', (string) ($data['tags'] ?? '')))), 0, 10);

        return [
            'stem_md' => $data['stem_md'],
            'stem_html' => RichText::toHtml($data['stem_md']),
            'explanation_html' => RichText::toHtml($data['explanation_md'] ?? null) ?: null,
            'difficulty' => (int) $data['difficulty'],
            'points' => $data['points'],
            'competency_tags' => $tags,
            'accepted_answers_encrypted' => $type === 'short_answer'
                ? array_slice(array_filter(array_map('trim', preg_split('/\R/', (string) $data['accepted_answers']) ?: [])), 0, 20)
                : null,
            'rubric' => $type === 'essay' && ($data['rubric'] ?? []) !== [] ? $data['rubric'] : null,
        ];
    }

    /** @param array<string, mixed> $data */
    private function syncOptions(Question $question, string $type, array $data): void
    {
        QuestionOption::query()->where('question_id', $question->id)->delete();
        $rows = [];
        if ($type === 'true_false') {
            $rows = [['Benar', $data['correct_answer'] === 'true'], ['Salah', $data['correct_answer'] === 'false']];
        } elseif (in_array($type, ['single_choice', 'multiple_choice'], true)) {
            foreach ($data['options'] as $option) {
                $rows[] = [trim((string) $option['body']), (bool) ($option['correct'] ?? false)];
            }
        }

        foreach ($rows as $index => [$body, $correct]) {
            $option = new QuestionOption;
            $option->forceFill(['question_id' => $question->id, 'body_html' => e($body), 'is_correct' => $correct, 'position' => $index + 1])->save();
        }
        if ($type === 'matching') {
            foreach ($data['pairs'] as $index => $pair) {
                $option = new QuestionOption;
                $option->forceFill(['question_id' => $question->id, 'body_html' => e(trim((string) $pair['left'])), 'match_text' => trim((string) $pair['right']), 'is_correct' => true, 'position' => $index + 1])->save();
            }
        }
    }
}
