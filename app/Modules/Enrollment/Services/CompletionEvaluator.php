<?php

declare(strict_types=1);

namespace App\Modules\Enrollment\Services;

use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Notification\Services\Notifier;
use Illuminate\Support\Facades\DB;

/**
 * Evaluasi syarat kelulusan (FR-ENR-005/006) — seluruhnya dihitung server:
 * semua lesson wajib selesai, kuis wajib lulus, dan (bila disyaratkan) skor ujian akhir
 * terbaik ≥ skor minimal. Terpenuhi → `pending_approval` (antrean approval sertifikat).
 */
final class CompletionEvaluator
{
    public function __construct(private readonly Notifier $notifier) {}

    public function evaluate(Enrollment $enrollment): void
    {
        if (! $enrollment->isActive()) {
            return;
        }

        $result = self::check($enrollment);
        if (! $result['met']) {
            return;
        }

        app(EnrollmentService::class)->transition($enrollment, 'pending_approval', null, 'Syarat kelulusan terpenuhi', [
            'final_score' => $result['final_score'],
            'completed_at' => now(),
            'progress_percent' => 100,
            'rejection_reason' => null,
        ]);

        $this->notifier->send($enrollment->user, 'certificate', 'Syarat kelulusan terpenuhi',
            'Selamat! Anda menyelesaikan '.$enrollment->program->name.'. Sertifikat sedang menunggu persetujuan Admin Akademik.',
            '/peserta/pembelajaran', email: true);
    }

    /**
     * @return array{met: bool, lessons_done: int, lessons_total: int, quizzes_ok: bool, final_required: bool, final_score: float|null, min_score: float}
     */
    public static function check(Enrollment $enrollment): array
    {
        [$done, $total] = ProgressService::requiredCounts($enrollment);
        $class = $enrollment->courseClass;

        $requiredQuizIds = DB::table('assessments')->where('course_class_id', $class->id)->where('kind', 'quiz')->where('is_required', true)->pluck('id');
        $passedQuizzes = $requiredQuizIds->isEmpty() ? 0 : DB::table('exam_attempts')
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('assessment_id', $requiredQuizIds)
            ->where('status', 'graded')->where('passed', true)
            ->distinct()->count('assessment_id');
        $quizzesOk = $passedQuizzes === $requiredQuizIds->count();

        $finalId = DB::table('assessments')->where('course_class_id', $class->id)->where('kind', 'final_exam')->value('id');
        $finalRequired = $class->requiresFinalExam() && $finalId !== null;
        $finalScore = $finalId === null ? null : DB::table('exam_attempts')
            ->where('enrollment_id', $enrollment->id)->where('assessment_id', $finalId)
            ->where('status', 'graded')->max('score');
        $finalScore = $finalScore === null ? null : (float) $finalScore;
        $minScore = $class->minimumScore();

        $met = $done === $total && $quizzesOk && (! $finalRequired || ($finalScore !== null && $finalScore >= $minScore));

        return [
            'met' => $met,
            'lessons_done' => $done,
            'lessons_total' => $total,
            'quizzes_ok' => $quizzesOk,
            'final_required' => $finalRequired,
            'final_score' => $finalScore,
            'min_score' => $minScore,
        ];
    }
}
