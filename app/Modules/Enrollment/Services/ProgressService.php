<?php

declare(strict_types=1);

namespace App\Modules\Enrollment\Services;

use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonProgress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Progres lesson (FR-CNT-005, keamanan/08 SEC-EXAM-17/18). Laporan posisi video dari klien
 * divalidasi kewajarannya: kemajuan tidak boleh melebihi waktu nyata berlalu × 2 (kecepatan
 * putar maksimum) + toleransi. Selisih yang tidak wajar dicatat sebagai indikator.
 */
final class ProgressService
{
    private const MAX_PLAYBACK_RATE = 2;

    private const TOLERANCE_SECONDS = 5;

    /** Kredit durasi belajar maksimal per heartbeat/ping (detik). */
    private const PING_CREDIT_CAP = 60;

    /** Rasio tonton minimum agar video dianggap selesai (Pengaturan Sistem → Pembelajaran). */
    public static function videoCompletionRatio(): float
    {
        return max(50, min(100, (int) config('lms.video_completion_percent', 90))) / 100;
    }

    public function __construct(
        private readonly EnrollmentService $enrollments,
        private readonly CompletionEvaluator $completion,
    ) {}

    public function open(Enrollment $enrollment, Lesson $lesson): LessonProgress
    {
        $progress = $this->record($enrollment, $lesson);
        if ($progress->first_opened_at === null) {
            $progress->forceFill(['first_opened_at' => now()])->save();
        }
        if ($enrollment->status === 'enrolled') {
            $this->enrollments->transition($enrollment, 'in_progress', $enrollment->user, 'Mulai belajar');
        }

        return $progress;
    }

    /** Heartbeat video (tiap ±15 detik) berisi posisi pemutaran dalam detik. */
    public function heartbeat(Enrollment $enrollment, Lesson $lesson, int $position): LessonProgress
    {
        if (! $lesson->isTimed()) {
            throw ValidationException::withMessages(['position' => 'Lesson bukan video/audio.']);
        }

        return DB::transaction(function () use ($enrollment, $lesson, $position): LessonProgress {
            $progress = $this->record($enrollment, $lesson, lock: true);
            $now = now();
            $last = $progress->last_heartbeat_at ?? $progress->first_opened_at ?? $now;
            $elapsed = max(0, (int) $last->diffInSeconds($now));
            $allowed = $elapsed * self::MAX_PLAYBACK_RATE + self::TOLERANCE_SECONDS;
            $duration = (int) ($lesson->duration_seconds ?? 0);
            $position = max(0, $duration > 0 ? min($position, $duration) : $position);

            $delta = max(0, $position - $progress->max_position_seconds);
            $credited = min($delta, $allowed);
            $flags = $progress->integrity_flags ?? [];
            if ($delta > $allowed) {
                $flags['implausible_progress'] = (int) ($flags['implausible_progress'] ?? 0) + 1;
            }

            $watched = $progress->watched_seconds + $credited;
            $attributes = [
                'watched_seconds' => $watched,
                'max_position_seconds' => max($progress->max_position_seconds, $progress->max_position_seconds + $credited),
                'time_spent_seconds' => $progress->time_spent_seconds + min($elapsed, self::PING_CREDIT_CAP),
                'last_heartbeat_at' => $now,
                'integrity_flags' => $flags === [] ? null : $flags,
            ];
            if ($progress->status !== 'completed' && $duration > 0 && $watched >= (int) floor($duration * self::videoCompletionRatio())) {
                $attributes += ['status' => 'completed', 'completed_at' => $now];
            }
            $progress->forceFill($attributes)->save();

            return $progress;
        }, 3);
    }

    /**
     * Ping aktivitas untuk lesson non-media (teks/PDF/dokumen/tautan) — menambah durasi belajar
     * paling banyak PING_CREDIT_CAP detik per ping agar tab yang ditinggal tidak menggelembungkan angka.
     */
    public function ping(Enrollment $enrollment, Lesson $lesson): LessonProgress
    {
        return DB::transaction(function () use ($enrollment, $lesson): LessonProgress {
            $progress = $this->record($enrollment, $lesson, lock: true);
            $now = now();
            $last = $progress->last_heartbeat_at ?? $progress->first_opened_at ?? $now;
            $elapsed = max(0, (int) $last->diffInSeconds($now));
            $progress->forceFill(['time_spent_seconds' => $progress->time_spent_seconds + min($elapsed, self::PING_CREDIT_CAP), 'last_heartbeat_at' => $now])->save();

            return $progress;
        }, 3);
    }

    /** "Tandai selesai" untuk PDF/dokumen/teks/tautan (SEC-EXAM-18: waktu baca tak wajar = indikator). */
    public function markComplete(Enrollment $enrollment, Lesson $lesson): LessonProgress
    {
        if (! in_array($lesson->type, Lesson::MANUAL_COMPLETE_TYPES, true)) {
            throw ValidationException::withMessages(['lesson' => 'Lesson ini selesai otomatis dari aktivitasnya.']);
        }

        $progress = $this->record($enrollment, $lesson);
        if ($progress->status !== 'completed') {
            $flags = $progress->integrity_flags ?? [];
            if ($progress->first_opened_at === null || $progress->first_opened_at->diffInSeconds(now()) < 10) {
                $flags['fast_completion'] = true;
            }
            $progress->forceFill(['status' => 'completed', 'completed_at' => now(), 'integrity_flags' => $flags === [] ? null : $flags])->save();
        }

        return $progress;
    }

    /** Lesson kuis selesai saat kuisnya lulus. */
    public function markQuizLessonComplete(Enrollment $enrollment, string $assessmentId): void
    {
        $lessons = Lesson::query()->where('type', 'quiz')->where('assessment_id', $assessmentId)->get();
        foreach ($lessons as $lesson) {
            $progress = $this->record($enrollment, $lesson);
            if ($progress->status !== 'completed') {
                $progress->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
            }
        }
    }

    /** Progres enrollment = lesson wajib selesai / total lesson wajib (FR-CNT-005). */
    public function recalculate(Enrollment $enrollment): void
    {
        [$done, $total] = self::requiredCounts($enrollment);
        $percent = $total === 0 ? 100 : (int) floor($done * 100 / $total);
        Enrollment::query()->whereKey($enrollment->id)->update(['progress_percent' => $percent, 'updated_at' => now()]);
        $enrollment->progress_percent = $percent;

        $this->completion->evaluate($enrollment->fresh() ?? $enrollment);
    }

    /** @return array{0: int, 1: int} [selesai, total] lesson wajib */
    public static function requiredCounts(Enrollment $enrollment): array
    {
        $required = DB::table('lessons')
            ->join('chapters', 'chapters.id', '=', 'lessons.chapter_id')
            ->join('modules', 'modules.id', '=', 'chapters.module_id')
            ->where('modules.course_class_id', $enrollment->course_class_id)
            ->where('lessons.is_required', true)
            ->pluck('lessons.id');

        $done = $required->isEmpty() ? 0 : DB::table('lesson_progress')
            ->where('enrollment_id', $enrollment->id)
            ->where('status', 'completed')
            ->whereIn('lesson_id', $required)
            ->count();

        return [$done, $required->count()];
    }

    private function record(Enrollment $enrollment, Lesson $lesson, bool $lock = false): LessonProgress
    {
        if (! $enrollment->canAccessContent() || $lesson->courseClassId() !== $enrollment->course_class_id) {
            abort(404);
        }

        DB::table('lesson_progress')->insertOrIgnore([
            'id' => (string) Str::uuid7(),
            'organization_id' => $enrollment->organization_id,
            'user_id' => $enrollment->user_id,
            'course_class_id' => $enrollment->course_class_id,
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'status' => 'started',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $query = LessonProgress::query()->where('enrollment_id', $enrollment->id)->where('lesson_id', $lesson->id);

        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }
}
