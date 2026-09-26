<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Controllers;

use App\Modules\Assessment\Models\Assessment;
use App\Modules\Assessment\Models\Assignment;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Learning\Services\ClassAccess;
use App\Modules\Reporting\Services\GradebookService;
use App\Support\Export\TableExport;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Buku nilai kelas (trainer pengampu/admin) & ekspor CSV. */
final class GradebookController
{
    public function __construct(private readonly ClassAccess $access, private readonly GradebookService $gradebook, private readonly AuditLogger $audit) {}

    public function index(Request $request, CourseClass $class): View
    {
        $user = $this->authorize($request, $class, 'gradebook.view');
        $class->load('program', 'trainers');

        return view('classes.gradebook', $this->gradebook->build($class) + [
            'class' => $class, 'tab' => 'gradebook',
            'workspace' => $this->access->workspaceFor($user),
            'canManageSettings' => $this->access->canManageSettings($user),
            'canExport' => $user->hasPermission('gradebook.export'),
        ]);
    }

    public function export(Request $request, CourseClass $class): StreamedResponse
    {
        $user = $this->authorize($request, $class, 'gradebook.export');
        $class->load('program');
        $format = TableExport::format($request->query('format'));
        $data = $this->gradebook->build($class);
        $this->audit->record('gradebook.exported', $user, 'course_class', $class->id, ['format' => $format]);

        $header = array_merge(['Peserta', 'Email', 'Kelompok', 'Status', 'Progres (%)'],
            $data['assessments']->map(fn (Assessment $a) => $a->title.' ('.Assessment::KINDS[$a->kind].')')->all(),
            $data['assignments']->map(fn (Assignment $a) => 'Tugas: '.$a->title.' (maks '.fmt_score($a->max_score).')')->all(),
            ['Skor Akhir']);
        $rows = $data['rows']->map(function (array $row): array {
            $enrollment = $row['enrollment'];

            return array_merge(
                [$enrollment->user->name, $enrollment->user->email, $enrollment->group !== null ? $enrollment->group->name : '', $enrollment->statusLabel(), $enrollment->progress_percent],
                array_map(fn (?float $v) => $v ?? '', array_values($row['scores'])),
                array_map(fn (?float $v) => $v ?? '', array_values($row['tasks'])),
                [$enrollment->final_score !== null ? (float) $enrollment->final_score : ''],
            );
        });

        return TableExport::download($format, 'nilai-'.$class->program->name.'-'.$class->batch_name, 'Buku Nilai '.$class->batch_name, $header, $rows, $class->program->name);
    }

    private function authorize(Request $request, CourseClass $class, string $permission): User
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($this->access->canView($user, $class) && $user->hasPermission($permission), 404);

        return $user;
    }
}
