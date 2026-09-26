<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Controllers;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Learning\Services\ClassAccess;
use App\Modules\Reporting\Services\ClassReportService;
use App\Support\Export\TableExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Laporan kelas (trainer pengampu/admin): daftar kelas + analitik per kelas + ekspor CSV/XLSX/PDF. */
final class ClassReportController
{
    public function __construct(private readonly ClassAccess $access, private readonly ClassReportService $reports, private readonly AuditLogger $audit) {}

    /** Daftar kelas yang diampu trainer dengan ringkasan (menu Laporan). */
    public function trainerIndex(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $classes = CourseClass::query()->with('program:id,name')
            ->whereIn('id', DB::table('class_trainers')->where('user_id', $user->id)->select('course_class_id'))
            ->orderByDesc('starts_on')->get();
        /** @var list<string> $ids */
        $ids = $classes->pluck('id')->values()->all();
        $summaries = $this->reports->summaries($ids);

        return view('reports.trainer-index', ['classes' => $classes, 'summaries' => $summaries]);
    }

    public function show(Request $request, CourseClass $class): View
    {
        $user = $this->authorize($request, $class);
        $class->load('program', 'trainers');

        return view('classes.report', $this->reports->build($class) + [
            'class' => $class, 'tab' => 'report',
            'workspace' => $this->access->workspaceFor($user),
            'canManageSettings' => $this->access->canManageSettings($user),
            'canExport' => $user->hasPermission('report.export'),
            'inactiveDays' => ClassReportService::INACTIVE_DAYS,
        ]);
    }

    public function export(Request $request, CourseClass $class): StreamedResponse
    {
        $user = $this->authorize($request, $class);
        abort_unless($user->hasPermission('report.export'), 404);
        $class->load('program');
        $format = TableExport::format($request->query('format'));
        $data = $this->reports->build($class);
        $this->audit->record('report.exported', $user, 'course_class', $class->id, ['report' => 'class', 'format' => $format]);

        $rows = $data['rows']->map(function (array $r): array {
            $e = $r['enrollment'];

            return [$e->user->name, $e->user->email, $e->group !== null ? $e->group->name : '', $e->statusLabel(), $e->progress_percent,
                $e->final_score !== null ? (float) $e->final_score : '', $r['last_at']?->timezone(display_tz())->format('Y-m-d H:i') ?? '',
                round($r['seconds'] / 60), $r['inactive'] ? 'Ya' : 'Tidak'];
        });

        return TableExport::download($format, 'laporan-kelas-'.$class->program->name.'-'.$class->batch_name, 'Laporan Kelas '.$class->batch_name,
            ['Peserta', 'Email', 'Kelompok', 'Status', 'Progres (%)', 'Skor Akhir', 'Aktivitas Terakhir', 'Durasi Belajar (menit)', 'Tidak Aktif'],
            $rows, $class->program->name.' · penyelesaian '.($data['completionRate'] ?? '—').'% · rata-rata skor '.($data['avgScore'] ?? '—'));
    }

    private function authorize(Request $request, CourseClass $class): User
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($this->access->canView($user, $class) && ($user->hasPermission('report.view_class') || $user->hasPermission('report.view_platform')), 404);

        return $user;
    }
}
