<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Modules\Access\RoleCode;
use App\Modules\Access\Services\RoleAssigner;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Services\Notifier;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\OrganizationMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Portal Admin Organisasi (docs/08 ORG-02, FR-AUTH-003): persetujuan keanggotaan peserta
 * yang mendaftar dengan kode organisasi, daftar anggota, dan progres enrollment anggota.
 * Lingkup = organisasi tempat pengguna menjadi Admin Organisasi (juga dibatasi RLS).
 */
final class OrgPortalController
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly Notifier $notifier,
    ) {}

    public function members(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $status = in_array($request->query('status'), ['pending', 'active', 'rejected', 'removed'], true) ? (string) $request->query('status') : null;
        $members = OrganizationMember::query()->with(['user:id,name,email,status', 'organization:id,name,code'])
            ->whereIn('organization_id', $user->tenantOrganizationIds())
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")->orderByDesc('created_at')
            ->paginate(30)->withQueryString();

        return view('org.members', ['members' => $members, 'status' => $status]);
    }

    public function decide(Request $request, OrganizationMember $member, RoleAssigner $roles): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(in_array($member->organization_id, $user->tenantOrganizationIds(), true), 404);
        $data = $request->validate(['decision' => ['required', 'in:approve,reject,remove'], 'reason' => ['nullable', 'string', 'max:500']]);
        $target = User::query()->findOrFail($member->user_id);
        $organization = Organization::query()->findOrFail($member->organization_id);

        $to = ['approve' => 'active', 'reject' => 'rejected', 'remove' => 'removed'][$data['decision']];
        $allowed = ['approve' => ['pending'], 'reject' => ['pending'], 'remove' => ['active']][$data['decision']];
        abort_unless(in_array($member->status, $allowed, true), 409);

        DB::transaction(function () use ($member, $target, $to, $user, $data, $roles): void {
            $member->forceFill(['status' => $to, 'approved_by' => $user->id, 'approved_at' => now()])->save();
            if ($to === 'active') {
                if ($target->primary_organization_id === null) {
                    $target->forceFill(['primary_organization_id' => $member->organization_id])->save();
                }
                if ($target->hasRole(RoleCode::Participant) && ! $target->hasRole(RoleCode::Participant, $member->organization_id)) {
                    $roles->assign($target, RoleCode::Participant, $member->organization_id, $user);
                }
            }
            if ($to === 'removed' && $target->primary_organization_id === $member->organization_id) {
                $target->forceFill(['primary_organization_id' => null])->save();
            }
            $this->audit->record('organization.member_'.$to, $user, 'organization', $member->organization_id, ['user_id' => $member->user_id], $data['reason'] ?? null, $member->organization_id);
        });

        $message = match ($to) {
            'active' => 'Keanggotaan Anda di '.$organization->name.' disetujui.',
            'rejected' => 'Keanggotaan Anda di '.$organization->name.' tidak disetujui.',
            default => 'Anda tidak lagi tercatat sebagai anggota '.$organization->name.'.',
        };
        $this->notifier->send($target, 'registration', 'Keanggotaan organisasi', $message, '/dasbor', email: true);

        return back()->with('status', 'Keanggotaan diperbarui.');
    }

    public function enrollments(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $enrollments = Enrollment::query()->with(['user:id,name', 'program:id,name', 'courseClass:id,batch_name'])
            ->whereIn('organization_id', $user->tenantOrganizationIds())
            ->orderByDesc('updated_at')->paginate(50);

        return view('org.enrollments', ['enrollments' => $enrollments]);
    }
}
