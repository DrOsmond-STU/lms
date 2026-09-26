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
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        $data = $this->gradebook->build($class);
        $this->audit->record('gradebook.exported', $user, 'course_class', $class->id);
        $file = 'nilai-'.Str::slug($class->program->name.'-'.$class->batch_name).'.csv';

        return response()->streamDownload(function () use ($data): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_merge(['Peserta', 'Email', 'Kelompok', 'Status', 'Progres (%)'],
                $data['assessments']->map(fn (Assessment $a) => $a->title.' ('.Assessment::KINDS[$a->kind].')')->all(),
                $data['assignments']->map(fn (Assignment $a) => 'Tugas: '.$a->title.' (maks '.fmt_score($a->max_score).')')->all(),
                ['Skor Akhir']), ';');
            foreach ($data['rows'] as $row) {
                $enrollment = $row['enrollment'];
                fputcsv($out, array_merge(
                    [$enrollment->user->name, $enrollment->user->email, $enrollment->group !== null ? $enrollment->group->name : '', $enrollment->statusLabel(), (string) $enrollment->progress_percent],
                    array_map(fn (?float $v) => $v === null ? '' : fmt_score($v), array_values($row['scores'])),
                    array_map(fn (?float $v) => $v === null ? '' : fmt_score($v), array_values($row['tasks'])),
                    [$enrollment->final_score !== null ? fmt_score($enrollment->final_score) : ''],
                ), ';');
            }
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function authorize(Request $request, CourseClass $class, string $permission): User
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($this->access->canView($user, $class) && $user->hasPermission($permission), 404);

        return $user;
    }
}
