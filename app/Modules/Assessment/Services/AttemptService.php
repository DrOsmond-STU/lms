<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Services;

use App\Modules\Assessment\Models\Assessment;
use App\Modules\Assessment\Models\AttemptAnswer;
use App\Modules\Assessment\Models\ExamAttempt;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\QuestionOption;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Services\CompletionEvaluator;
use App\Modules\Enrollment\Services\EnrollmentService;
use App\Modules\Enrollment\Services\ProgressService;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Services\Notifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Mesin attempt asesmen (FR-ASM-002..009, keamanan/08 SEC-EXAM-01..09):
 * - soal & urutan di-snapshot saat mulai; ID soal/opsi ke klien berupa alias per attempt;
 * - batas waktu ditegakkan server (`deadline_at` + grace 30 detik), auto-submit terjadwal;
 * - satu attempt aktif per (asesmen, enrollment) — indeks unik parsial;
 * - skor hanya dihitung server dari jawaban tersimpan.
 */
final class AttemptService
{
    /** Toleransi pengumpulan setelah deadline (Pengaturan Sistem → Pembelajaran & Ujian; 0–60 dtk). */
    public static function graceSeconds(): int
    {
        return max(0, min(60, (int) config('lms.exam_grace_seconds', 30)));
    }

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly Notifier $notifier,
        private readonly ProgressService $progress,
        private readonly EnrollmentService $enrollments,
    ) {}

    /**
     * Syarat memulai attempt (SEC-EXAM-07). Mengembalikan pesan penolakan atau null.
     */
    public function blockReason(Enrollment $enrollment, Assessment $assessment): ?string
    {
        if (! $enrollment->isActive() || $assessment->course_class_id !== $enrollment->course_class_id) {
            return 'Asesmen tidak tersedia untuk enrollment ini.';
        }
        if (! $assessment->isWithinWindow()) {
            return $assessment->opens_at?->isFuture() ? 'Asesmen belum dibuka.' : 'Jendela asesmen sudah ditutup.';
        }
        if ($this->remainingAttempts($enrollment, $assessment) <= 0) {
            return 'Kesempatan mengerjakan sudah habis.';
        }

        $last = ExamAttempt::query()->where('enrollment_id', $enrollment->id)->where('assessment_id', $assessment->id)
            ->whereNotNull('submitted_at')->orderByDesc('submitted_at')->first();
        if ($last !== null && $assessment->cooldown_minutes > 0 && $last->submitted_at?->copy()->addMinutes($assessment->cooldown_minutes)->isFuture()) {
            return 'Tunggu jeda antar kesempatan sampai '.$last->submitted_at->copy()->addMinutes($assessment->cooldown_minutes)->timezone(display_tz())->format('H:i').' '.tz_label().'.';
        }

        // Ujian akhir terkunci sampai lesson & kuis wajib selesai (FR-ASM-004).
        if ($assessment->isFinal() && $assessment->requires_prerequisites) {
            $check = CompletionEvaluator::check($enrollment);
            if ($check['lessons_done'] < $check['lessons_total'] || ! $check['quizzes_ok']) {
                return 'Selesaikan semua materi & kuis wajib terlebih dahulu.';
            }
        }

        return null;
    }

    public function attemptsUsed(Enrollment $enrollment, Assessment $assessment): int
    {
        return ExamAttempt::query()->where('enrollment_id', $enrollment->id)->where('assessment_id', $assessment->id)
            ->where('status', '<>', 'voided')->count();
    }

    public function remainingAttempts(Enrollment $enrollment, Assessment $assessment): int
    {
        $extra = (int) DB::table('attempt_grants')->where('enrollment_id', $enrollment->id)->where('assessment_id', $assessment->id)->sum('extra_attempts');

        return $assessment->max_attempts + $extra - $this->attemptsUsed($enrollment, $assessment);
    }

    public function activeAttempt(Enrollment $enrollment, Assessment $assessment): ?ExamAttempt
    {
        return ExamAttempt::query()->where('enrollment_id', $enrollment->id)->where('assessment_id', $assessment->id)
            ->where('status', 'in_progress')->first();
    }

    /**
     * Mulai atau lanjutkan attempt aktif (SEC-EXAM-06: tab/perangkat lain melanjutkan attempt
     * yang sama tanpa mereset timer).
     *
     * @throws ValidationException
     */
    public function start(Enrollment $enrollment, Assessment $assessment, ?string $ip): ExamAttempt
    {
        $existing = $this->activeAttempt($enrollment, $assessment);
        if ($existing !== null) {
            return $existing;
        }
        if (($reason = $this->blockReason($enrollment, $assessment)) !== null) {
            throw ValidationException::withMessages(['assessment' => $reason]);
        }

        $questions = $this->selectQuestions($assessment);
        if ($questions->isEmpty()) {
            throw ValidationException::withMessages(['assessment' => 'Asesmen belum memiliki soal. Hubungi trainer.']);
        }

        $optionOrder = [];
        foreach ($questions as $question) {
            $ids = $question->options->pluck('id')->all();
            if ($assessment->shuffle_options && $question->type !== 'true_false') {
                shuffle($ids);
            }
            $optionOrder[$question->id] = array_values($ids);
        }

        $now = now();
        $deadline = $now->copy()->addMinutes($assessment->duration_minutes);
        if ($assessment->closes_at !== null && $assessment->closes_at->lessThan($deadline)) {
            $deadline = $assessment->closes_at->copy();
        }

        try {
            return DB::transaction(function () use ($enrollment, $assessment, $questions, $optionOrder, $now, $deadline, $ip): ExamAttempt {
                $attempt = new ExamAttempt;
                $attempt->forceFill([
                    'id' => (string) Str::uuid7(),
                    'organization_id' => $enrollment->organization_id,
                    'course_class_id' => $enrollment->course_class_id,
                    'assessment_id' => $assessment->id,
                    'enrollment_id' => $enrollment->id,
                    'user_id' => $enrollment->user_id,
                    'attempt_no' => (int) ExamAttempt::query()->where('enrollment_id', $enrollment->id)->where('assessment_id', $assessment->id)->max('attempt_no') + 1,
                    'status' => 'in_progress',
                    'started_at' => $now,
                    'deadline_at' => $deadline,
                    'question_order' => $questions->pluck('id')->values()->all(),
                    'option_order' => $optionOrder,
                    'question_versions' => $questions->mapWithKeys(fn (Question $q) => [$q->id => $q->version])->all(),
                    'ip' => $ip,
                ])->save();

                return $attempt;
            });
        } catch (UniqueConstraintViolationException) {
            // Permintaan paralel: attempt aktif sudah dibuat oleh request lain.
            return $this->activeAttempt($enrollment, $assessment) ?? throw ValidationException::withMessages(['assessment' => 'Coba lagi.']);
        }
    }

    /** Alias ID per attempt (SEC-EXAM-02). */
    public static function alias(ExamAttempt $attempt, string $id): string
    {
        return substr(hash_hmac('sha256', $id, $attempt->id.'|'.config('app.key')), 0, 20);
    }

    /**
     * Payload soal untuk klien — tanpa kunci jawaban (SEC-EXAM-01).
     *
     * @return list<array{alias: string, number: int, type: string, stem_html: string, points: string, options: list<array{alias: string, body_html: string, selected: bool}>, targets: list<array{alias: string, text: string}>, pairs: array<string, string>, text_answer: string|null}>
     */
    public function payload(ExamAttempt $attempt): array
    {
        $questions = Question::query()->with('options')->whereIn('id', $attempt->question_order)->get()->keyBy('id');
        $answers = AttemptAnswer::query()->where('exam_attempt_id', $attempt->id)->get()->keyBy('question_id');
        $items = [];
        foreach ($attempt->question_order as $index => $questionId) {
            $question = $questions->get($questionId);
            if (! $question instanceof Question) {
                continue;
            }
            $answer = $answers->get($questionId);
            $selected = $answer instanceof AttemptAnswer ? ($answer->selected_option_ids ?? []) : [];
            $options = [];
            $byId = $question->options->keyBy('id');
            foreach ($attempt->option_order[$questionId] ?? [] as $optionId) {
                $option = $byId->get($optionId);
                if ($option !== null) {
                    $options[] = ['alias' => self::alias($attempt, $optionId), 'body_html' => $option->body_html, 'selected' => in_array($optionId, $selected, true)];
                }
            }
            // Menjodohkan: sisi kiri = opsi urut asli, sisi kanan (target) = teks pasangan urut acak per attempt.
            $targets = [];
            $pairs = [];
            if ($question->type === 'matching') {
                $options = [];
                foreach ($question->options as $left) {
                    $options[] = ['alias' => self::alias($attempt, $left->id), 'body_html' => $left->body_html, 'selected' => false];
                }
                foreach ($attempt->option_order[$questionId] ?? [] as $optionId) {
                    $option = $byId->get($optionId);
                    if ($option !== null) {
                        $targets[] = ['alias' => self::alias($attempt, 'target:'.$optionId), 'text' => (string) $option->match_text];
                    }
                }
                foreach (($answer instanceof AttemptAnswer ? ($answer->match_pairs ?? []) : []) as $leftId => $rightId) {
                    $pairs[self::alias($attempt, (string) $leftId)] = self::alias($attempt, 'target:'.$rightId);
                }
            }
            $items[] = [
                'alias' => self::alias($attempt, $questionId),
                'number' => $index + 1,
                'type' => $question->type,
                'stem_html' => $question->stem_html,
                'points' => $question->points,
                'options' => $options,
                'targets' => $targets,
                'pairs' => $pairs,
                'text_answer' => $answer instanceof AttemptAnswer ? $answer->text_answer : null,
            ];
        }

        return $items;
    }

    /**
     * Autosave jawaban idempoten (SEC-EXAM-08). Setelah deadline + grace → 409.
     *
     * @param  list<string>  $optionAliases
     * @param  array<string, string>  $pairAliases  alias opsi kiri => alias target kanan (soal menjodohkan)
     */
    public function saveAnswer(ExamAttempt $attempt, string $questionAlias, array $optionAliases, ?string $text, array $pairAliases = []): void
    {
        if (! $attempt->isInProgress() || now()->greaterThan($attempt->deadline_at->copy()->addSeconds(self::graceSeconds()))) {
            throw new ConflictHttpException('Waktu mengerjakan sudah habis.');
        }

        $questionId = collect($attempt->question_order)->first(fn (string $id): bool => hash_equals(self::alias($attempt, $id), $questionAlias));
        if (! is_string($questionId)) {
            throw ValidationException::withMessages(['question' => 'Soal tidak valid.']);
        }
        /** @var Question $question */
        $question = Question::query()->findOrFail($questionId);

        if ($question->type === 'matching') {
            $pairs = [];
            foreach ($attempt->option_order[$questionId] ?? [] as $leftId) {
                $chosen = $pairAliases[self::alias($attempt, $leftId)] ?? null;
                if ($chosen === null || $chosen === '') {
                    continue;
                }
                foreach ($attempt->option_order[$questionId] ?? [] as $rightId) {
                    if (hash_equals(self::alias($attempt, 'target:'.$rightId), (string) $chosen)) {
                        $pairs[$leftId] = $rightId;
                    }
                }
            }
            DB::table('attempt_answers')->upsert([[
                'id' => (string) Str::uuid7(), 'exam_attempt_id' => $attempt->id, 'question_id' => $questionId,
                'selected_option_ids' => null, 'match_pairs' => json_encode($pairs), 'text_answer' => null, 'answered_at' => now(),
            ]], ['exam_attempt_id', 'question_id'], ['match_pairs', 'answered_at']);

            return;
        }

        $optionIds = [];
        foreach ($attempt->option_order[$questionId] ?? [] as $optionId) {
            if (in_array(self::alias($attempt, $optionId), $optionAliases, true)) {
                $optionIds[] = $optionId;
            }
        }
        if (count($optionIds) !== count(array_unique($optionAliases))) {
            throw ValidationException::withMessages(['options' => 'Pilihan tidak valid.']);
        }
        if (in_array($question->type, ['single_choice', 'true_false'], true) && count($optionIds) > 1) {
            throw ValidationException::withMessages(['options' => 'Pilih satu jawaban.']);
        }

        DB::table('attempt_answers')->upsert([[
            'id' => (string) Str::uuid7(),
            'exam_attempt_id' => $attempt->id,
            'question_id' => $questionId,
            'selected_option_ids' => $question->isChoice() ? json_encode($optionIds) : null,
            'text_answer' => $question->isChoice() ? null : mb_substr((string) $text, 0, $question->type === 'essay' ? 10000 : 500),
            'answered_at' => now(),
        ]], ['exam_attempt_id', 'question_id'], ['selected_option_ids', 'text_answer', 'answered_at']);
    }

    /** Catat indikator integritas (pindah tab, salin/tempel) — hanya indikator (SEC-EXAM-15). */
    public function recordIntegrity(ExamAttempt $attempt, string $event): void
    {
        if (! $attempt->isInProgress() || ! in_array($event, ['blur', 'copy', 'paste'], true)) {
            return;
        }
        $flags = $attempt->integrity_flags ?? [];
        $flags[$event] = min(9999, (int) ($flags[$event] ?? 0) + 1);
        $attempt->forceFill(['integrity_flags' => $flags])->save();
    }

    public function submit(ExamAttempt $attempt, bool $auto = false): ExamAttempt
    {
        $graded = DB::transaction(function () use ($attempt, $auto): ?ExamAttempt {
            /** @var ExamAttempt|null $locked */
            $locked = ExamAttempt::query()->whereKey($attempt->id)->lockForUpdate()->first();
            if ($locked === null || ! $locked->isInProgress()) {
                return null;
            }

            $this->grade($locked);
            $locked->forceFill([
                'status' => $locked->needs_manual_grading ? ($auto ? 'auto_submitted' : 'submitted') : 'graded',
                'submitted_at' => now(),
            ])->save();

            return $locked;
        });

        if ($graded === null) {
            return $attempt->refresh();
        }

        $this->afterGrading($graded);

        return $graded;
    }

    /**
     * Penilaian manual esai oleh trainer pengampu/Admin Akademik — poin langsung, atau skor per
     * kriteria rubrik (dijumlahkan & diskalakan ke poin soal), plus umpan balik untuk peserta.
     *
     * @param  array<string, float>  $points  question_id => poin
     * @param  array<string, array<string, float>>  $rubricScores  question_id => [kriteria => skor]
     * @param  array<string, string>  $feedback  question_id => umpan balik
     */
    public function gradeEssays(ExamAttempt $attempt, array $points, User $grader, array $rubricScores = [], array $feedback = []): void
    {
        if ($attempt->user_id === $grader->id) {
            throw ValidationException::withMessages(['points' => 'Anda tidak dapat menilai attempt milik sendiri.']);
        }
        if (! in_array($attempt->status, ['submitted', 'auto_submitted'], true)) {
            throw ValidationException::withMessages(['points' => 'Attempt ini tidak menunggu penilaian.']);
        }

        DB::transaction(function () use ($attempt, $points, $grader, $rubricScores, $feedback): void {
            $essays = Question::query()->whereIn('id', $attempt->question_order)->where('type', 'essay')->get()->keyBy('id');
            foreach ($essays as $questionId => $question) {
                $criteria = null;
                if ($question->rubric !== null && $question->rubric !== [] && isset($rubricScores[$questionId])) {
                    $criteria = [];
                    $sum = 0.0;
                    $max = 0.0;
                    foreach ($question->rubric as $criterion) {
                        $cap = (float) $criterion['max'];
                        $given = max(0.0, min($cap, (float) ($rubricScores[$questionId][$criterion['name']] ?? 0)));
                        $criteria[$criterion['name']] = $given;
                        $sum += $given;
                        $max += $cap;
                    }
                    $value = $max > 0 ? round($sum * (float) $question->points / $max, 2) : 0.0;
                } elseif (array_key_exists($questionId, $points)) {
                    $value = max(0.0, min((float) $question->points, (float) $points[$questionId]));
                } else {
                    continue;
                }
                DB::table('attempt_answers')->upsert([[
                    'id' => (string) Str::uuid7(), 'exam_attempt_id' => $attempt->id, 'question_id' => $questionId,
                    'points_awarded' => $value, 'is_correct' => $value >= (float) $question->points,
                    'rubric_scores' => $criteria === null ? null : json_encode($criteria),
                    'feedback' => isset($feedback[$questionId]) && trim($feedback[$questionId]) !== '' ? mb_substr(trim($feedback[$questionId]), 0, 3000) : null,
                    'graded_by' => $grader->id, 'graded_at' => now(),
                ]], ['exam_attempt_id', 'question_id'], ['points_awarded', 'is_correct', 'rubric_scores', 'feedback', 'graded_by', 'graded_at']);
            }

            $pending = $essays->keys()->diff(DB::table('attempt_answers')->where('exam_attempt_id', $attempt->id)->whereNotNull('graded_by')->pluck('question_id'));
            if ($pending->isEmpty()) {
                $score = $this->computeScore($attempt);
                $attempt->forceFill([
                    'status' => 'graded', 'needs_manual_grading' => false, 'score' => $score,
                    'passed' => $score >= (float) $attempt->assessment->passing_score,
                ])->save();
            }
            $this->audit->record('assessment.graded_manually', $grader, 'exam_attempt', $attempt->id, ['points' => $points], organizationId: $attempt->organization_id);
        });

        $attempt->refresh();
        if ($attempt->status === 'graded') {
            $this->afterGrading($attempt);
        }
    }

    public function grantExtraAttempt(Enrollment $enrollment, Assessment $assessment, int $extra, string $reason, User $actor): void
    {
        if (! in_array($enrollment->status, ['enrolled', 'in_progress', 'failed'], true) || $assessment->course_class_id !== $enrollment->course_class_id) {
            throw ValidationException::withMessages(['extra_attempts' => 'Enrollment ini tidak dapat diberi kesempatan tambahan.']);
        }

        DB::transaction(function () use ($enrollment, $assessment, $extra, $reason, $actor): void {
            if ($enrollment->status === 'failed') {
                $otherActive = Enrollment::query()->where('user_id', $enrollment->user_id)->where('program_id', $enrollment->program_id)
                    ->whereKeyNot($enrollment->id)->whereNotIn('status', ['failed', 'cancelled'])->exists();
                if ($otherActive) {
                    throw ValidationException::withMessages(['extra_attempts' => 'Peserta sudah memiliki enrollment aktif lain untuk program ini.']);
                }
                $this->enrollments->transition($enrollment, 'in_progress', $actor, 'Kesempatan tambahan: '.$reason, ['completed_at' => null]);
            }
            DB::table('attempt_grants')->insert([
                'id' => (string) Str::uuid7(), 'assessment_id' => $assessment->id, 'enrollment_id' => $enrollment->id,
                'extra_attempts' => $extra, 'reason' => $reason, 'granted_by' => $actor->id, 'created_at' => now(),
            ]);
            $this->audit->record('assessment.attempt_granted', $actor, 'enrollment', $enrollment->id, ['assessment_id' => $assessment->id, 'extra' => $extra], $reason, $enrollment->organization_id);
        });
        $this->notifier->send($enrollment->user, 'assessment', 'Kesempatan tambahan', "Anda mendapat {$extra} kesempatan tambahan untuk {$assessment->title}.", '/peserta/kelas/'.$enrollment->id);
    }

    public function void(ExamAttempt $attempt, string $reason, User $actor): void
    {
        DB::transaction(function () use ($attempt, $reason, $actor): void {
            $attempt->forceFill(['status' => 'voided', 'voided_reason' => $reason, 'submitted_at' => $attempt->submitted_at ?? now()])->save();
            $this->audit->record('assessment.attempt_voided', $actor, 'exam_attempt', $attempt->id, null, $reason, $attempt->organization_id);
        });
        $this->notifier->send($attempt->enrollment->user, 'assessment', 'Attempt dibatalkan', 'Salah satu attempt asesmen Anda dibatalkan oleh trainer/admin. Alasan: '.$reason, '/peserta/kelas/'.$attempt->enrollment_id, email: true);
    }

    /** Auto-submit attempt yang melewati deadline + grace (FR-ASM-006). */
    public function autoSubmitExpired(): int
    {
        $count = 0;
        ExamAttempt::query()->where('status', 'in_progress')
            ->where('deadline_at', '<', now()->subSeconds(self::graceSeconds()))
            ->orderBy('deadline_at')->limit(500)->get()
            ->each(function (ExamAttempt $attempt) use (&$count): void {
                $this->submit($attempt, auto: true);
                $count++;
            });

        return $count;
    }

    /** @return Collection<int, Question> */
    private function selectQuestions(Assessment $assessment): Collection
    {
        $base = Question::query()->with('options')->where('question_bank_id', $assessment->question_bank_id)->where('is_active', true);
        $selected = collect();

        // Aturan pemilihan: jumlah per tingkat kesulitan, mis. {"1": 3, "3": 5} (SEC-EXAM-11).
        foreach ($assessment->selection_rules ?? [] as $difficulty => $count) {
            $selected = $selected->merge((clone $base)->where('difficulty', (int) $difficulty)->whereNotIn('id', $selected->pluck('id'))->inRandomOrder()->limit((int) $count)->get());
        }
        $remaining = $assessment->question_count - $selected->count();
        if ($remaining > 0) {
            $selected = $selected->merge((clone $base)->whereNotIn('id', $selected->pluck('id'))->inRandomOrder()->limit($remaining)->get());
        }

        $selected = $selected->values();
        if (! $assessment->shuffle_questions) {
            $selected = $selected->sortBy('created_at')->values();
        }

        return $selected;
    }

    private function grade(ExamAttempt $attempt): void
    {
        $questions = Question::query()->with('options')->whereIn('id', $attempt->question_order)->get()->keyBy('id');
        $answers = AttemptAnswer::query()->where('exam_attempt_id', $attempt->id)->get()->keyBy('question_id');
        $manual = false;

        foreach ($attempt->question_order as $questionId) {
            $question = $questions->get($questionId);
            if (! $question instanceof Question) {
                continue;
            }
            $answer = $answers->get($questionId);
            if ($question->type === 'essay') {
                $manual = true;

                continue;
            }

            if ($question->type === 'matching') {
                // Kredit parsial: proporsi pasangan yang benar.
                $pairs = $answer instanceof AttemptAnswer ? ($answer->match_pairs ?? []) : [];
                $total = $question->options->count();
                $right = $question->options->filter(fn (QuestionOption $o) => ($pairs[$o->id] ?? null) === $o->id)->count();
                $fraction = $total === 0 ? 0.0 : $right / $total;
                DB::table('attempt_answers')->upsert([[
                    'id' => (string) Str::uuid7(), 'exam_attempt_id' => $attempt->id, 'question_id' => $questionId,
                    'is_correct' => $total > 0 && $right === $total, 'points_awarded' => round((float) $question->points * $fraction, 2),
                ]], ['exam_attempt_id', 'question_id'], ['is_correct', 'points_awarded']);

                continue;
            }

            $correct = match ($question->type) {
                'short_answer' => self::shortAnswerMatches($question, $answer?->text_answer),
                default => self::choiceMatches($question, $answer instanceof AttemptAnswer ? ($answer->selected_option_ids ?? []) : []),
            };

            DB::table('attempt_answers')->upsert([[
                'id' => (string) Str::uuid7(), 'exam_attempt_id' => $attempt->id, 'question_id' => $questionId,
                'is_correct' => $correct, 'points_awarded' => $correct ? $question->points : 0,
            ]], ['exam_attempt_id', 'question_id'], ['is_correct', 'points_awarded']);
        }

        if ($manual) {
            $attempt->forceFill(['needs_manual_grading' => true, 'score' => null, 'passed' => null]);

            return;
        }

        $score = $this->computeScore($attempt);
        $attempt->forceFill(['score' => $score, 'passed' => $score >= (float) $attempt->assessment->passing_score]);
    }

    private function computeScore(ExamAttempt $attempt): float
    {
        $total = (float) Question::query()->whereIn('id', $attempt->question_order)->sum('points');
        $earned = (float) DB::table('attempt_answers')->where('exam_attempt_id', $attempt->id)->sum('points_awarded');

        return $total <= 0 ? 0.0 : round($earned * 100 / $total, 2);
    }

    /** @param list<string> $selected */
    private static function choiceMatches(Question $question, array $selected): bool
    {
        $correct = $question->options->where('is_correct', true)->pluck('id')->sort()->values()->all();
        $chosen = collect($selected)->sort()->values()->all();

        return $correct !== [] && $correct === $chosen;
    }

    private static function shortAnswerMatches(Question $question, ?string $text): bool
    {
        $normalize = fn (string $value): string => preg_replace('/\s+/u', ' ', mb_strtolower(trim($value))) ?? '';
        $given = $normalize((string) $text);
        if ($given === '') {
            return false;
        }

        foreach ($question->accepted_answers_encrypted ?? [] as $accepted) {
            if (hash_equals($normalize($accepted), $given)) {
                return true;
            }
        }

        return false;
    }

    private function afterGrading(ExamAttempt $attempt): void
    {
        $attempt->loadMissing('assessment', 'enrollment');
        $assessment = $attempt->assessment;
        $enrollment = $attempt->enrollment;

        if ($attempt->status !== 'graded') {
            $this->notifier->send($enrollment->user, 'grading', 'Jawaban dikumpulkan', 'Jawaban '.$assessment->title.' Anda sedang menunggu penilaian trainer.', '/peserta/kelas/'.$enrollment->id);

            return;
        }

        if ($attempt->passed === true && ! $assessment->isFinal()) {
            $this->progress->markQuizLessonComplete($enrollment, $assessment->id);
        }

        $this->notifier->send($enrollment->user, 'grading', 'Hasil '.$assessment->title, $attempt->passed === true ? 'Anda lulus asesmen ini.' : 'Anda belum mencapai skor minimal.', '/peserta/kelas/'.$enrollment->id);

        $enrollment->refresh();
        $this->progress->recalculate($enrollment);
        $enrollment->refresh();

        // Ujian akhir: kesempatan habis tanpa lulus → tidak lulus.
        if ($assessment->isFinal() && $attempt->passed !== true && $enrollment->isActive()
            && $this->remainingAttempts($enrollment, $assessment) <= 0 && $this->activeAttempt($enrollment, $assessment) === null) {
            $this->enrollments->transition($enrollment, 'failed', null, 'Kesempatan ujian akhir habis tanpa mencapai skor minimal', ['completed_at' => now()]);
            $this->notifier->send($enrollment->user, 'grading', 'Belum lulus', 'Kesempatan ujian akhir '.$enrollment->program->name.' telah habis. Hubungi trainer untuk kesempatan tambahan atau daftar ulang.', '/peserta/pembelajaran', email: true);
        }
    }
}
