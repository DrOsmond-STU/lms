<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Http\Controllers;

use App\Modules\Assessment\Models\Assessment;
use App\Modules\Assessment\Models\AttemptAnswer;
use App\Modules\Assessment\Models\ExamAttempt;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\QuestionBank;
use App\Modules\Assessment\Services\AttemptService;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Learning\Services\ClassAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Pengaturan asesmen & penilaian oleh trainer pengampu / staf platform
 * (FR-ASM-002/003/009, SEC-EXAM-09/19).
 */
final class AssessmentManageController
{
    public function __construct(
        private readonly ClassAccess $access,
        private readonly AuditLogger $audit,
        private readonly AttemptService $attempts,
    ) {}

    public function create(Request $request, CourseClass $class): View
    {
        $user = $this->authorize($request, $class);
        $kind = array_key_exists((string) $request->query('jenis'), Assessment::KINDS) ? (string) $request->query('jenis') : 'quiz';

        return view('assessments.form', [
            'class' => $class,
            'assessment' => (new Assessment)->forceFill([
                'kind' => $kind, 'duration_minutes' => $kind === 'final_exam' ? 90 : 20, 'max_attempts' => $kind === 'final_exam' ? 2 : 3,
                'question_count' => $kind === 'final_exam' ? 20 : 5, 'passing_score' => $class->minimumScore(), 'cooldown_minutes' => 0,
                'review_policy' => $kind === 'final_exam' ? 'after_close' : 'after_submit', 'shuffle_questions' => true, 'shuffle_options' => true,
                'is_required' => $kind !== 'pretest', 'requires_prerequisites' => $kind === 'final_exam',
            ]),
            'banks' => $this->banks($class),
            'workspace' => $this->access->workspaceFor($user),
        ]);
    }

    public function store(Request $request, CourseClass $class): RedirectResponse
    {
        $user = $this->authorize($request, $class);
        $data = $this->validated($request, $class);
        if ($data['kind'] === 'final_exam' && Assessment::query()->where('course_class_id', $class->id)->where('kind', 'final_exam')->exists()) {
            throw ValidationException::withMessages(['kind' => 'Kelas ini sudah memiliki ujian akhir.']);
        }

        $assessment = new Assessment;
        $assessment->forceFill($this->attributes($data) + ['course_class_id' => $class->id, 'kind' => $data['kind'], 'created_by' => $user->id])->save();
        $this->audit->record('assessment.created', $user, 'assessment', $assessment->id, ['class_id' => $class->id, 'kind' => $assessment->kind]);

        return redirect()->route('classes.assessments', $class)->with('status', 'Asesmen "'.$assessment->title.'" dibuat.');
    }

    public function edit(Request $request, CourseClass $class, Assessment $assessment): View
    {
        $user = $this->authorize($request, $class);
        abort_unless($assessment->course_class_id === $class->id, 404);

        return view('assessments.form', ['class' => $class, 'assessment' => $assessment, 'banks' => $this->banks($class), 'workspace' => $this->access->workspaceFor($user)]);
    }

    public function update(Request $request, CourseClass $class, Assessment $assessment): RedirectResponse
    {
        $user = $this->authorize($request, $class);
        abort_unless($assessment->course_class_id === $class->id, 404);
        $data = $this->validated($request, $class);
        $assessment->forceFill($this->attributes($data));
        $changes = $assessment->getDirty();
        $assessment->save();
        $this->audit->record('assessment.updated', $user, 'assessment', $assessment->id, $changes);

        return redirect()->route('classes.assessments', $class)->with('status', 'Pengaturan asesmen disimpan.');
    }

    /** Hapus asesmen yang belum pernah dikerjakan dan tidak dirujuk lesson kuis. */
    public function destroy(Request $request, CourseClass $class, Assessment $assessment): RedirectResponse
    {
        $user = $this->authorize($request, $class, 'assessment.delete');
        abort_unless($assessment->course_class_id === $class->id, 404);
        if (ExamAttempt::query()->where('assessment_id', $assessment->id)->exists()) {
            throw ValidationException::withMessages(['assessment' => 'Asesmen sudah dikerjakan peserta sehingga tidak dapat dihapus (riwayat nilai harus utuh). Tutup jendela waktunya bila tidak dipakai lagi.']);
        }
        if (DB::table('lessons')->where('assessment_id', $assessment->id)->exists()) {
            throw ValidationException::withMessages(['assessment' => 'Asesmen dipakai lesson kuis. Hapus atau ubah lesson tersebut terlebih dahulu.']);
        }
        $assessment->delete();
        $this->audit->record('assessment.deleted', $user, 'assessment', $assessment->id, ['class_id' => $class->id, 'title' => $assessment->title]);

        return redirect()->route('classes.assessments', $class)->with('status', 'Asesmen "'.$assessment->title.'" dihapus.');
    }

    public function attempts(Request $request, CourseClass $class, Assessment $assessment): View
    {
        $user = $this->authorize($request, $class, 'assessment.view');
        abort_unless($assessment->course_class_id === $class->id, 404);
        $attempts = ExamAttempt::query()->with('enrollment.user:id,name')->where('assessment_id', $assessment->id)
            ->orderByRaw("CASE WHEN status IN ('submitted','auto_submitted') THEN 0 ELSE 1 END")->orderByDesc('started_at')->paginate(50);
        $enrollments = Enrollment::query()->with('user:id,name')->where('course_class_id', $class->id)->whereIn('status', ['enrolled', 'in_progress', 'failed'])->get();

        return view('assessments.attempts', ['class' => $class, 'assessment' => $assessment, 'attempts' => $attempts, 'enrollments' => $enrollments, 'workspace' => $this->access->workspaceFor($user)]);
    }

    public function gradeForm(Request $request, CourseClass $class, ExamAttempt $attempt): View
    {
        $user = $this->authorize($request, $class, 'assessment.grade_manual');
        abort_unless($attempt->course_class_id === $class->id, 404);
        $questions = Question::query()->with('options')->whereIn('id', $attempt->question_order)->get()->keyBy('id');
        $answers = AttemptAnswer::query()->where('exam_attempt_id', $attempt->id)->get()->keyBy('question_id');

        return view('assessments.grade', [
            'class' => $class, 'attempt' => $attempt->load('assessment', 'enrollment.user'), 'questions' => $questions, 'answers' => $answers,
            'workspace' => $this->access->workspaceFor($user),
        ]);
    }

    public function grade(Request $request, CourseClass $class, ExamAttempt $attempt): RedirectResponse
    {
        $user = $this->authorize($request, $class, 'assessment.grade_manual');
        abort_unless($attempt->course_class_id === $class->id, 404);
        $data = $request->validate([
            'points' => ['nullable', 'array'], 'points.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rubric' => ['nullable', 'array'], 'rubric.*' => ['nullable', 'array'], 'rubric.*.*' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'feedback' => ['nullable', 'array'], 'feedback.*' => ['nullable', 'string', 'max:3000'],
        ]);
        $points = array_map('floatval', array_filter($data['points'] ?? [], fn ($v) => $v !== null && $v !== ''));
        $rubric = array_map(fn (array $criteria) => array_map('floatval', array_filter($criteria, fn ($v) => $v !== null && $v !== '')), $data['rubric'] ?? []);
        $this->attempts->gradeEssays($attempt, $points, $user, $rubric, array_map('strval', $data['feedback'] ?? []));

        return redirect()->route('assessments.attempts', [$class, $attempt->assessment_id])->with('status', 'Penilaian disimpan.');
    }

    public function grant(Request $request, CourseClass $class, Assessment $assessment): RedirectResponse
    {
        $user = $this->authorize($request, $class, 'assessment.reset_attempt');
        abort_unless($assessment->course_class_id === $class->id, 404);
        $data = $request->validate([
            'enrollment_id' => ['required', 'uuid'],
            'extra_attempts' => ['required', 'integer', 'between:1,5'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        /** @var Enrollment $enrollment */
        $enrollment = Enrollment::query()->where('course_class_id', $class->id)->findOrFail($data['enrollment_id']);
        $this->attempts->grantExtraAttempt($enrollment, $assessment, (int) $data['extra_attempts'], $data['reason'], $user);

        return back()->with('status', 'Kesempatan tambahan diberikan.');
    }

    public function void(Request $request, CourseClass $class, ExamAttempt $attempt): RedirectResponse
    {
        $user = $this->authorize($request, $class, 'assessment.reset_attempt');
        abort_unless($attempt->course_class_id === $class->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        if ($attempt->status === 'voided') {
            throw ValidationException::withMessages(['reason' => 'Attempt sudah dibatalkan.']);
        }
        $this->attempts->void($attempt, $data['reason'], $user);

        return back()->with('status', 'Attempt dibatalkan.');
    }

    private function authorize(Request $request, CourseClass $class, string $permission = 'assessment.update'): User
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($this->access->canView($user, $class), 404);
        abort_unless($this->access->canManageContent($user, $class, $permission), 403);

        return $user;
    }

    /** @return Collection<int, QuestionBank> */
    private function banks(CourseClass $class): Collection
    {
        return QuestionBank::query()->where('program_id', $class->program_id)
            ->withCount(['questions as active_questions_count' => fn ($q) => $q->where('is_active', true)])->orderBy('name')->get();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, CourseClass $class): array
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(Assessment::KINDS))],
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'question_bank_id' => ['required', 'uuid', Rule::exists('question_banks', 'id')->where('program_id', $class->program_id)],
            'duration_minutes' => ['required', 'integer', 'between:1,600'],
            'max_attempts' => ['required', 'integer', 'between:1,20'],
            'cooldown_minutes' => ['required', 'integer', 'between:0,10080'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
            'question_count' => ['required', 'integer', 'between:1,200'],
            'difficulty_rules' => ['nullable', 'array'],
            'difficulty_rules.*' => ['nullable', 'integer', 'between:0,200'],
            'passing_score' => ['required', 'numeric', 'between:0,100'],
            'review_policy' => ['required', Rule::in(array_keys(Assessment::REVIEW_POLICIES))],
            'shuffle_questions' => ['nullable', 'boolean'],
            'shuffle_options' => ['nullable', 'boolean'],
            'is_required' => ['nullable', 'boolean'],
            'requires_prerequisites' => ['nullable', 'boolean'],
        ], ['question_bank_id.exists' => 'Pilih bank soal dari program kelas ini.']);

        $available = Question::query()->where('question_bank_id', $data['question_bank_id'])->where('is_active', true)->count();
        if ($available < (int) $data['question_count']) {
            throw ValidationException::withMessages(['question_count' => "Bank soal hanya memiliki {$available} soal aktif."]);
        }
        $rules = array_filter(array_map('intval', $data['difficulty_rules'] ?? []), fn (int $count): bool => $count > 0);
        if (array_sum($rules) > (int) $data['question_count']) {
            throw ValidationException::withMessages(['difficulty_rules' => 'Jumlah soal per kesulitan melebihi jumlah soal.']);
        }
        $data['difficulty_rules'] = $rules;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'title' => trim((string) $data['title']),
            'question_bank_id' => $data['question_bank_id'],
            'duration_minutes' => (int) $data['duration_minutes'],
            'max_attempts' => (int) $data['max_attempts'],
            'cooldown_minutes' => (int) $data['cooldown_minutes'],
            'opens_at' => $data['opens_at'] ?? null,
            'closes_at' => $data['closes_at'] ?? null,
            'question_count' => (int) $data['question_count'],
            'selection_rules' => $data['difficulty_rules'] === [] ? null : $data['difficulty_rules'],
            'passing_score' => $data['passing_score'],
            'review_policy' => $data['review_policy'],
            'shuffle_questions' => (bool) ($data['shuffle_questions'] ?? false),
            'shuffle_options' => (bool) ($data['shuffle_options'] ?? false),
            'is_required' => ($data['kind'] ?? '') !== 'pretest' && (bool) ($data['is_required'] ?? false),
            'requires_prerequisites' => (bool) ($data['requires_prerequisites'] ?? false),
        ];
    }
}
