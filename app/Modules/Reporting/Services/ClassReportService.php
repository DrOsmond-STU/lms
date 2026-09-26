<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Services;

use App\Modules\Assessment\Models\Assessment;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Learning\Models\CourseClass;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Analitik kelas untuk trainer/admin: jumlah peserta, tingkat penyelesaian, rata-rata nilai,
 * aktivitas belajar, peserta tidak aktif, hasil kuis per asesmen, durasi belajar, presensi.
 *
 * @phpstan-type ActivityRow array{enrollment: Enrollment, last_at: Carbon|null, seconds: int, inactive: bool}
 * @phpstan-type ClassReport array{
 *     difficultLessons: list<array{id: string, title: string, module: string, completion: float, avg_seconds: int, opened: int}>,
 *     difficultQuestions: list<array{id: string, stem: string, answers: int, correct_rate: float}>,
 *     total: int, passed: int, failed: int, active: int, pendingApproval: int,
 *     completionRate: float|null, avgScore: float|null, avgProgress: float|null, activeLast7: int,
 *     inactive: Collection<int, array{enrollment: Enrollment, last_at: Carbon|null, seconds: int, inactive: true}>,
 *     rows: Collection<int, ActivityRow>,
 *     quiz: Collection<int, array{assessment: Assessment, stats: \stdClass|null}>, gain: float|null,
 *     avgSeconds: int, totalSeconds: int, attendanceRate: float|null, sessions: int,
 *     weekly: list<array{label: string, value: int}>
 * }
 */
final class ClassReportService
{
    public const INACTIVE_DAYS = 7;

    /**
     * @return ClassReport
     */
    public function build(CourseClass $class): array
    {
        $enrollments = Enrollment::query()->with('user:id,name,email', 'group:id,name')->where('course_class_id', $class->id)
            ->whereNotIn('status', ['applied', 'awaiting_payment', 'cancelled'])->get();
        /** @var list<string> $ids */
        $ids = $enrollments->pluck('id')->values()->all();
        $total = $enrollments->count();
        $passed = $enrollments->where('status', 'passed')->count();
        $failed = $enrollments->where('status', 'failed')->count();
        $scored = $enrollments->whereNotNull('final_score');

        $activity = DB::table('lesson_progress')->whereIn('enrollment_id', $ids)
            ->selectRaw('enrollment_id, max(greatest(coalesce(last_heartbeat_at, updated_at), updated_at)) as last_at, sum(time_spent_seconds) as seconds, count(*) filter (where status = \'completed\') as done')
            ->groupBy('enrollment_id')->get()->keyBy('enrollment_id');
        $attemptActivity = DB::table('exam_attempts')->whereIn('enrollment_id', $ids)->where('status', '<>', 'voided')
            ->selectRaw('enrollment_id, max(coalesce(submitted_at, started_at)) as last_at, sum(extract(epoch from (coalesce(submitted_at, started_at) - started_at)))::bigint as seconds')
            ->groupBy('enrollment_id')->get()->keyBy('enrollment_id');
        $cutoff = now()->subDays(self::INACTIVE_DAYS);

        $rows = $enrollments->map(function (Enrollment $e) use ($activity, $attemptActivity, $cutoff): array {
            $a = $activity->get($e->id);
            $b = $attemptActivity->get($e->id);
            $lastAt = collect([$a?->last_at, $b?->last_at, $e->enrolled_at?->toDateTimeString()])->filter()->map(fn ($v) => Carbon::parse((string) $v))->max();
            $seconds = (int) ($a->seconds ?? 0) + (int) ($b->seconds ?? 0);

            return [
                'enrollment' => $e, 'last_at' => $lastAt, 'seconds' => $seconds,
                'inactive' => in_array($e->status, Enrollment::ACTIVE, true) && ($lastAt === null || $lastAt->lt($cutoff)),
            ];
        });

        $assessments = Assessment::query()->where('course_class_id', $class->id)->orderByRaw("CASE kind WHEN 'pretest' THEN 0 WHEN 'quiz' THEN 1 WHEN 'posttest' THEN 2 ELSE 3 END")->orderBy('title')->get();
        $quiz = DB::table('exam_attempts')->whereIn('enrollment_id', $ids)->where('status', '<>', 'voided')->whereNotNull('score')
            ->selectRaw('assessment_id, count(distinct enrollment_id) as participants, count(*) as attempts, avg(score) as avg_score, min(score) as min_score, max(score) as max_score, count(distinct enrollment_id) filter (where passed) as passed')
            ->groupBy('assessment_id')->get()->keyBy('assessment_id');
        $quizRows = $assessments->map(fn (Assessment $a) => ['assessment' => $a, 'stats' => $quiz->get($a->id)]);

        $pre = $assessments->firstWhere('kind', 'pretest');
        $post = $assessments->firstWhere('kind', 'posttest');
        $gain = null;
        if ($pre !== null && $post !== null) {
            $preAvg = $quiz->get($pre->id)?->avg_score;
            $postAvg = $quiz->get($post->id)?->avg_score;
            $gain = $preAvg !== null && $postAvg !== null ? round((float) $postAvg - (float) $preAvg, 1) : null;
        }

        $sessions = DB::table('class_sessions')->where('course_class_id', $class->id)->where('attendance_mode', '<>', 'none')->count();
        $present = DB::table('attendance_records')->where('course_class_id', $class->id)->whereIn('status', ['present', 'late'])->count();
        $secondsAll = $rows->sum('seconds');

        $weeks = collect(range(7, 0))->map(fn (int $ago) => now()->startOfWeek()->subWeeks($ago));
        $weekly = DB::table('lesson_progress')->whereIn('enrollment_id', $ids)->where('updated_at', '>=', $weeks->first())
            ->selectRaw("to_char(date_trunc('week', updated_at AT TIME ZONE ?), 'YYYY-MM-DD') as week, count(distinct enrollment_id) as active", [display_tz()])
            ->groupBy('week')->pluck('active', 'week');

        return [
            'difficultLessons' => $this->difficultLessons($class, $ids, $total),
            'difficultQuestions' => $this->difficultQuestions($ids),
            'total' => $total, 'passed' => $passed, 'failed' => $failed, 'active' => $enrollments->whereIn('status', Enrollment::ACTIVE)->count(),
            'pendingApproval' => $enrollments->where('status', 'pending_approval')->count(),
            'completionRate' => $total === 0 ? null : round($passed * 100 / $total, 1),
            'avgScore' => $scored->isEmpty() ? null : round((float) $scored->avg(fn (Enrollment $e) => (float) $e->final_score), 1),
            'avgProgress' => $total === 0 ? null : round((float) $enrollments->avg('progress_percent'), 1),
            'activeLast7' => $rows->filter(fn (array $r) => $r['last_at'] !== null && $r['last_at']->gte($cutoff))->count(),
            'inactive' => $rows->filter(fn (array $r) => $r['inactive'])->sortBy(fn (array $r) => $r['last_at']?->getTimestamp() ?? 0)->values(),
            'rows' => $rows->sortBy(fn (array $r) => $r['enrollment']->user->name, SORT_NATURAL | SORT_FLAG_CASE)->values(),
            'quiz' => $quizRows, 'gain' => $gain,
            'avgSeconds' => $total === 0 ? 0 : (int) round($secondsAll / $total), 'totalSeconds' => (int) $secondsAll,
            'attendanceRate' => $sessions === 0 || $total === 0 ? null : round($present * 100 / ($sessions * $total), 1), 'sessions' => $sessions,
            'weekly' => array_values($weeks->map(fn (Carbon $w) => ['label' => $w->translatedFormat('d M'), 'value' => (int) ($weekly[$w->format('Y-m-d')] ?? 0)])->all()),
        ];
    }

    /**
     * Ringkasan singkat untuk daftar kelas trainer.
     *
     * @param  list<string>  $classIds
     * @return Collection<string, object{course_class_id: string, total: int, passed: int, avg_score: float|null, avg_progress: float|null}>
     */
    public function summaries(array $classIds): Collection
    {
        /** @var Collection<string, object{course_class_id: string, total: int, passed: int, avg_score: float|null, avg_progress: float|null}> */
        return DB::table('enrollments')->whereIn('course_class_id', $classIds)->whereNotIn('status', ['applied', 'awaiting_payment', 'cancelled'])
            ->selectRaw("course_class_id, count(*) as total, count(*) filter (where status = 'passed') as passed, avg(final_score) as avg_score, avg(progress_percent) as avg_progress")
            ->groupBy('course_class_id')->get()->keyBy('course_class_id');
    }

    /**
     * Materi tersulit: tingkat penyelesaian terendah & waktu rata-rata tertinggi (min. 1 peserta membuka).
     *
     * @param  list<string>  $enrollmentIds
     * @return list<array{id: string, title: string, module: string, completion: float, avg_seconds: int, opened: int}>
     */
    private function difficultLessons(CourseClass $class, array $enrollmentIds, int $total): array
    {
        if ($total === 0) {
            return [];
        }
        $rows = DB::table('lessons')->join('chapters', 'chapters.id', '=', 'lessons.chapter_id')->join('modules', 'modules.id', '=', 'chapters.module_id')
            ->leftJoin('lesson_progress', fn ($join) => $join->on('lesson_progress.lesson_id', '=', 'lessons.id')->whereIn('lesson_progress.enrollment_id', $enrollmentIds))
            ->where('modules.course_class_id', $class->id)->where('lessons.is_required', true)
            ->groupBy('lessons.id', 'lessons.title', 'modules.title')
            ->selectRaw("lessons.id, lessons.title, modules.title as module, count(lesson_progress.id) as opened, count(*) filter (where lesson_progress.status = 'completed') as done, coalesce(avg(lesson_progress.time_spent_seconds), 0) as avg_seconds")
            ->get();

        return array_values($rows->map(fn ($r) => ['id' => (string) $r->id, 'title' => (string) $r->title, 'module' => (string) $r->module, 'completion' => round((int) $r->done * 100 / $total, 1), 'avg_seconds' => (int) $r->avg_seconds, 'opened' => (int) $r->opened])
            ->filter(fn (array $r) => $r['opened'] > 0)
            ->sortBy([['completion', 'asc'], ['avg_seconds', 'desc']])->take(5)->all());
    }

    /**
     * Soal tersulit: persentase jawaban benar terendah (min. 3 jawaban).
     *
     * @param  list<string>  $enrollmentIds
     * @return list<array{id: string, stem: string, answers: int, correct_rate: float}>
     */
    private function difficultQuestions(array $enrollmentIds): array
    {
        if ($enrollmentIds === []) {
            return [];
        }
        $rows = DB::table('attempt_answers')->join('exam_attempts', 'exam_attempts.id', '=', 'attempt_answers.exam_attempt_id')->join('questions', 'questions.id', '=', 'attempt_answers.question_id')
            ->whereIn('exam_attempts.enrollment_id', $enrollmentIds)->where('exam_attempts.status', '<>', 'voided')->whereNotNull('attempt_answers.is_correct')
            ->groupBy('questions.id', 'questions.stem_html')
            ->selectRaw('questions.id, questions.stem_html, count(*) as answers, count(*) filter (where attempt_answers.is_correct) as correct')
            ->havingRaw('count(*) >= 3')->orderByRaw('count(*) filter (where attempt_answers.is_correct)::float / count(*) asc')->limit(5)->get();

        return array_values($rows->map(fn ($r) => ['id' => (string) $r->id, 'stem' => trim(strip_tags((string) $r->stem_html)), 'answers' => (int) $r->answers, 'correct_rate' => round((int) $r->correct * 100 / max(1, (int) $r->answers), 1)])->all());
    }

    public static function duration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' dtk';
        }
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $hours > 0 ? $hours.' j '.$minutes.' mnt' : $minutes.' mnt';
    }
}
