<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Services;

use App\Modules\Assessment\Models\Assessment;
use App\Modules\Assessment\Models\Assignment;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Learning\Models\CourseClass;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Buku nilai per kelas: skor terbaik tiap asesmen (kuis/post-test/ujian akhir), nilai tugas,
 * progres, skor akhir & status enrollment. Dipakai halaman gradebook, ekspor CSV, dan
 * halaman "Nilai Saya" peserta.
 */
final class GradebookService
{
    /**
     * @return array{
     *     assessments: Collection<int, Assessment>,
     *     assignments: Collection<int, Assignment>,
     *     rows: Collection<int, array{enrollment: Enrollment, scores: array<string, float|null>, tasks: array<string, float|null>, passed: array<string, bool>}>
     * }
     */
    public function build(CourseClass $class, ?string $onlyEnrollmentId = null): array
    {
        $assessments = Assessment::query()->where('course_class_id', $class->id)->whereIn('kind', ['quiz', 'posttest', 'final_exam'])
            ->orderByRaw("CASE kind WHEN 'quiz' THEN 0 WHEN 'posttest' THEN 1 ELSE 2 END")->orderBy('title')->get();
        $assignments = Assignment::query()->where('course_class_id', $class->id)->orderBy('position')->orderBy('title')->get();
        $enrollments = Enrollment::query()->with('user:id,name,email', 'group:id,name')->where('course_class_id', $class->id)
            ->whereNotIn('status', ['applied', 'awaiting_payment', 'cancelled'])
            ->when($onlyEnrollmentId !== null, fn ($q) => $q->whereKey($onlyEnrollmentId))
            ->get()->sortBy(fn (Enrollment $e) => $e->user->name, SORT_NATURAL | SORT_FLAG_CASE)->values();
        $enrollmentIds = $enrollments->pluck('id')->all();

        $best = DB::table('exam_attempts')->whereIn('enrollment_id', $enrollmentIds)->where('status', '<>', 'voided')->whereNotNull('score')
            ->selectRaw('enrollment_id, assessment_id, max(score) as best, bool_or(passed) as passed')->groupBy('enrollment_id', 'assessment_id')->get()
            ->groupBy('enrollment_id');
        $tasks = DB::table('assignment_submissions')->whereIn('enrollment_id', $enrollmentIds)->whereNotNull('score')
            ->get(['enrollment_id', 'assignment_id', 'score'])->groupBy('enrollment_id');

        $rows = $enrollments->map(function (Enrollment $enrollment) use ($assessments, $assignments, $best, $tasks): array {
            $scores = [];
            $passed = [];
            $mine = ($best->get($enrollment->id) ?? collect())->keyBy('assessment_id');
            foreach ($assessments as $assessment) {
                $row = $mine->get($assessment->id);
                $scores[$assessment->id] = $row === null ? null : (float) $row->best;
                $passed[$assessment->id] = $row !== null && (bool) $row->passed;
            }
            $taskScores = [];
            $mineTasks = ($tasks->get($enrollment->id) ?? collect())->keyBy('assignment_id');
            foreach ($assignments as $assignment) {
                $row = $mineTasks->get($assignment->id);
                $taskScores[$assignment->id] = $row === null ? null : (float) $row->score;
            }

            return ['enrollment' => $enrollment, 'scores' => $scores, 'tasks' => $taskScores, 'passed' => $passed];
        });

        return ['assessments' => $assessments, 'assignments' => $assignments, 'rows' => $rows];
    }
}
