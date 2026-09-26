<?php

declare(strict_types=1);

namespace App\Modules\Learning\Services;

use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ketersediaan lesson bagi satu enrollment: drip content (tanggal / hari sejak mendaftar),
 * prasyarat lesson tertentu, dan urutan wajib (sequential) per kelas. Semua diputuskan server;
 * UI hanya menampilkan alasan kunci.
 */
final class LessonAvailability
{
    /**
     * Peta lesson_id => alasan terkunci (string) untuk seluruh lesson kelas, urut kurikulum.
     *
     * @param  Collection<int, Lesson>  $ordered  lesson kelas urut modul→bab→posisi (dengan chapter.module dimuat bila perlu)
     * @param  array<string, mixed>  $done  lesson_id => true untuk yang selesai
     * @return array<string, string>
     */
    public function locks(Enrollment $enrollment, Collection $ordered, array $done): array
    {
        $locks = [];
        $sequential = (bool) $enrollment->courseClass->is_sequential;
        $titles = $ordered->pluck('title', 'id');
        $previousRequiredDone = true;

        foreach ($ordered as $lesson) {
            $reason = $this->dripReason($enrollment, $lesson);
            if ($reason === null && $lesson->prerequisite_lesson_id !== null && ! isset($done[$lesson->prerequisite_lesson_id])) {
                $reason = 'Selesaikan "'.($titles[$lesson->prerequisite_lesson_id] ?? 'materi prasyarat').'" terlebih dahulu.';
            }
            if ($reason === null && $sequential && ! $previousRequiredDone) {
                $reason = 'Selesaikan materi sebelumnya secara berurutan.';
            }
            if ($reason !== null) {
                $locks[$lesson->id] = $reason;
            }
            if ($lesson->is_required && ! isset($done[$lesson->id])) {
                $previousRequiredDone = false;
            }
        }

        return $locks;
    }

    /** Alasan terkunci untuk satu lesson (null = tersedia). */
    public function lockReason(Enrollment $enrollment, Lesson $lesson): ?string
    {
        $ordered = self::orderedLessons($enrollment->course_class_id);
        $done = DB::table('lesson_progress')->where('enrollment_id', $enrollment->id)->where('status', 'completed')->pluck('lesson_id')->flip()->all();

        return $this->locks($enrollment, $ordered, $done)[$lesson->id] ?? null;
    }

    private function dripReason(Enrollment $enrollment, Lesson $lesson): ?string
    {
        if ($lesson->unlock_at !== null && $lesson->unlock_at->isFuture()) {
            return 'Terbuka pada '.$lesson->unlock_at->timezone(display_tz())->translatedFormat('d M Y H:i').' '.tz_label().'.';
        }
        if ($lesson->unlock_after_days !== null && $lesson->unlock_after_days > 0) {
            $base = $enrollment->enrolled_at ?? $enrollment->created_at;
            $opens = $base->copy()->addDays($lesson->unlock_after_days);
            if ($opens->isFuture()) {
                return 'Terbuka '.$lesson->unlock_after_days.' hari setelah pendaftaran ('.$opens->timezone(display_tz())->translatedFormat('d M Y').').';
            }
        }

        return null;
    }

    /** @return Collection<int, Lesson> */
    public static function orderedLessons(string $classId): Collection
    {
        return Lesson::query()->select('lessons.*')
            ->join('chapters', 'chapters.id', '=', 'lessons.chapter_id')->join('modules', 'modules.id', '=', 'chapters.module_id')
            ->where('modules.course_class_id', $classId)
            ->orderBy('modules.position')->orderBy('chapters.position')->orderBy('lessons.position')->get();
    }
}
