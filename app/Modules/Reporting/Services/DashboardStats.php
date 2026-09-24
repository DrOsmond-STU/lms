<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Services;

use App\Modules\Access\RoleCode;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Models\InAppNotification;
use App\Modules\Organization\Models\Organization;
use Illuminate\Support\Facades\DB;

/**
 * Ringkasan dashboard per peran (FR-RPT-001..003). Semua kueri dibatasi lingkup pengguna;
 * tabel ber-tenant juga dibatasi RLS di basis data.
 */
final class DashboardStats
{
    /** @return array<string, mixed> */
    public function participant(User $user): array
    {
        return [
            'active' => Enrollment::query()->with('program:id,name', 'courseClass:id,batch_name,ends_on')
                ->where('user_id', $user->id)->whereIn('status', ['enrolled', 'in_progress', 'pending_approval'])
                ->orderByDesc('updated_at')->limit(4)->get(),
            'passed' => Enrollment::query()->where('user_id', $user->id)->where('status', 'passed')->count(),
            'certificates' => DB::table('certificates')->where('user_id', $user->id)->where('status', 'active')->count(),
            'notifications' => InAppNotification::query()->where('user_id', $user->id)->orderByDesc('created_at')->limit(5)->get(),
            'unread' => InAppNotification::query()->where('user_id', $user->id)->whereNull('read_at')->count(),
        ];
    }

    /** @return array<string, mixed> */
    public function trainer(User $user): array
    {
        $classIds = DB::table('class_trainers')->where('user_id', $user->id)->pluck('course_class_id');

        return [
            'classes' => DB::table('course_classes')->join('programs', 'programs.id', '=', 'course_classes.program_id')
                ->whereIn('course_classes.id', $classIds)->whereIn('course_classes.status', ['draft', 'open', 'running'])
                ->orderBy('course_classes.starts_on')->limit(8)
                ->get(['course_classes.id', 'course_classes.batch_name', 'course_classes.status', 'course_classes.enrolled_count', 'programs.name as program_name']),
            'classCount' => $classIds->count(),
            'participants' => DB::table('enrollments')->whereIn('course_class_id', $classIds)->whereNotIn('status', ['cancelled'])->count(),
            'pendingGrading' => DB::table('exam_attempts')->whereIn('course_class_id', $classIds)->whereIn('status', ['submitted', 'auto_submitted'])->count(),
            'unread' => InAppNotification::query()->where('user_id', $user->id)->whereNull('read_at')->count(),
        ];
    }

    /** @return array<string, mixed> */
    public function organization(User $user): array
    {
        $organizationIds = $user->tenantOrganizationIds();

        return [
            'organizations' => Organization::query()->whereIn('id', $organizationIds)->orderBy('name')->get(['id', 'name', 'code']),
            'members' => DB::table('organization_members')->whereIn('organization_id', $organizationIds)
                ->selectRaw("count(*) filter (where status = 'active') as active, count(*) filter (where status = 'pending') as pending")->first(),
            'enrollments' => DB::table('enrollments')->whereIn('organization_id', $organizationIds)
                ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'certificates' => DB::table('certificates')->whereIn('organization_id', $organizationIds)->where('status', 'active')->count(),
        ];
    }

    /** @return array<string, mixed> */
    public function admin(User $user): array
    {
        $participantRole = DB::table('roles')->where('code', RoleCode::Participant->value)->value('id');
        $months = collect(range(5, 0))->map(fn (int $ago) => now()->startOfMonth()->subMonths($ago));
        $perMonth = DB::table('enrollments')->where('created_at', '>=', $months->first())
            ->selectRaw("to_char(created_at AT TIME ZONE 'Asia/Jakarta', 'YYYY-MM') as month, count(*) as total")
            ->groupBy('month')->pluck('total', 'month');
        $passed = DB::table('enrollments')->where('status', 'passed')->count();
        $failed = DB::table('enrollments')->where('status', 'failed')->count();

        return [
            'participants' => DB::table('role_user')->where('role_id', $participantRole)->distinct()->count('user_id'),
            'organizations' => DB::table('organizations')->where('status', 'active')->count(),
            'classes' => DB::table('course_classes')->whereIn('status', ['open', 'running'])->count(),
            'programs' => DB::table('programs')->where('status', 'published')->count(),
            'enrollmentsPerMonth' => $months->map(fn ($month) => ['label' => $month->translatedFormat('M Y'), 'total' => (int) ($perMonth[$month->format('Y-m')] ?? 0)])->all(),
            'passRate' => $passed + $failed === 0 ? null : round($passed * 100 / ($passed + $failed), 1),
            'certificates' => DB::table('certificates')->where('status', 'active')->count(),
            'approvalQueue' => $user->hasPermission('certificate.approve') ? DB::table('enrollments')->where('status', 'pending_approval')->count() : null,
            'secondApprovals' => DB::table('approval_requests')->whereNull('decision')->where('expires_at', '>', now())->where('requested_by', '<>', $user->id)->count(),
            'programsInReview' => $user->hasPermission('program.publish') ? DB::table('programs')->where('status', 'in_review')->count() : null,
            'recentEnrollments' => Enrollment::query()->with('user:id,name', 'program:id,name')
                ->orderByDesc('created_at')->limit(6)->get(['id', 'user_id', 'program_id', 'status', 'created_at']),
        ];
    }
}
