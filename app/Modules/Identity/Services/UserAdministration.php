<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Access\RoleCode;
use App\Modules\Access\Services\ApprovalWorkflow;
use App\Modules\Access\Services\RoleAssigner;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Audit\Services\SecurityEventLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\InvitationNotification;
use App\Modules\Identity\Notifications\MfaResetNotification;
use App\Modules\Organization\Models\Organization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Administrasi pengguna oleh admin platform (FR-USER-001..003, FR-USER-006, docs/08 ADM-07).
 *
 * Aturan hierarki (docs/07 §2–§3):
 * - Tidak ada aksi sensitif terhadap akun sendiri.
 * - Akun ber-peran admin platform hanya dapat dikelola Super Admin.
 * - Penetapan/pencabutan `super_admin` memerlukan persetujuan Super Admin kedua
 *   (maker–checker) — belum tersedia, sehingga ditolak.
 * - Admin tidak pernah menetapkan/melihat kata sandi pengguna: akun baru menerima undangan.
 */
final class UserAdministration
{
    public function __construct(
        private readonly RoleAssigner $roles,
        private readonly OneTimeTokens $tokens,
        private readonly AuditLogger $audit,
        private readonly SecurityEventLogger $securityEvents,
        private readonly DeviceSessions $devices,
        private readonly ApprovalWorkflow $approvals,
    ) {}

    /** @return list<RoleCode> */
    public function assignableRoles(User $actor): array
    {
        if ($actor->hasRole(RoleCode::SuperAdmin)) {
            return [RoleCode::AcademicAdmin, RoleCode::FinanceAdmin, RoleCode::SupportAdmin, RoleCode::OrgAdmin, RoleCode::Trainer, RoleCode::Participant];
        }
        if ($actor->hasRole(RoleCode::AcademicAdmin)) {
            return [RoleCode::OrgAdmin, RoleCode::Trainer, RoleCode::Participant];
        }

        return [];
    }

    public function canManage(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return false;
        }

        $platformTarget = array_filter($target->roleCodes(), fn (RoleCode $role): bool => $role->isPlatform()) !== [];

        return ! $platformTarget || $actor->hasRole(RoleCode::SuperAdmin);
    }

    /**
     * @param  array{name: string, email: string, role: string, organization_id?: string|null}  $data
     *
     * @throws ValidationException|AuthorizationException
     */
    public function invite(User $actor, array $data): User
    {
        $role = $this->assertAssignable($actor, $data['role']);
        $organizationId = $this->resolveOrganization($role, $data['organization_id'] ?? null);
        $email = User::normalizeEmail($data['email']);

        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'Email sudah terdaftar. Buka detail pengguna tersebut untuk menambahkan peran.']);
        }

        $user = DB::transaction(function () use ($actor, $data, $email, $role, $organizationId): User {
            $user = new User(['name' => trim($data['name']), 'email' => $email]);
            $user->forceFill(['status' => 'pending_verification', 'primary_organization_id' => $organizationId])->save();
            $this->roles->assign($user, $role, $organizationId, $actor);
            $this->addMembership($user, $organizationId, $actor);
            $this->audit->record('user.invited', $actor, 'user', $user->id, ['role' => $role->value, 'organization_id' => $organizationId], organizationId: $organizationId);

            return $user;
        });

        $this->sendInvitation($user, $role);

        return $user;
    }

    /** @throws AuthorizationException */
    public function resendInvitation(User $actor, User $target): void
    {
        if (! $this->isAwaitingInvitation($target) || ! $this->canManage($actor, $target)) {
            throw new AuthorizationException;
        }

        $roles = $target->roleCodes();
        $this->sendInvitation($target, $roles[0] ?? RoleCode::Participant);
        $this->audit->record('user.invitation_resent', $actor, 'user', $target->id);
    }

    public function isAwaitingInvitation(User $user): bool
    {
        return $user->status === 'pending_verification' && $user->password === null;
    }

    /** @throws AuthorizationException */
    public function deactivate(User $actor, User $target, string $reason): void
    {
        $this->assertCanManage($actor, $target);

        DB::transaction(function () use ($actor, $target, $reason): void {
            $target->forceFill([
                'status' => 'deactivated',
                'deactivated_at' => now(),
                'session_version' => $target->session_version + 1, // cabut semua sesi seketika
                'remember_token' => Str::random(60),
            ])->save();
            $this->tokens->revokeAll($target);
            $this->devices->revokeOthers($target, null, 'account_deactivated');
            $this->audit->record('user.deactivated', $actor, 'user', $target->id, null, $reason);
        });

        $this->securityEvents->log('account_deactivated', 'info', $target->id, ['by' => $actor->id]);
    }

    /** @throws AuthorizationException */
    public function reactivate(User $actor, User $target, string $reason): void
    {
        $this->assertCanManage($actor, $target);

        DB::transaction(function () use ($actor, $target, $reason): void {
            $target->forceFill([
                'status' => $target->email_verified_at !== null ? 'active' : 'pending_verification',
                'deactivated_at' => null,
                'session_version' => $target->session_version + 1,
            ])->save();
            $this->audit->record('user.reactivated', $actor, 'user', $target->id, null, $reason);
        });
    }

    /** @throws ValidationException|AuthorizationException */
    public function assignRole(User $actor, User $target, string $roleCode, ?string $organizationId): void
    {
        $this->assertCanManage($actor, $target);
        $role = $this->assertAssignable($actor, $roleCode);
        $organizationId = $this->resolveOrganization($role, $organizationId);

        if (RoleAssigner::conflictsWith($target, $role)) {
            throw ValidationException::withMessages(['role' => 'Peran admin platform tidak boleh digabung dengan peran Peserta — gunakan akun terpisah.']);
        }

        DB::transaction(function () use ($actor, $target, $role, $organizationId): void {
            $this->roles->assign($target, $role, $organizationId, $actor);
            $this->addMembership($target, $organizationId, $actor);
        });
    }

    /** @throws ValidationException|AuthorizationException */
    public function revokeRole(User $actor, User $target, string $assignmentId, string $reason): void
    {
        $this->assertCanManage($actor, $target);

        $assignment = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.id', $assignmentId)
            ->where('role_user.user_id', $target->id)
            ->first(['roles.code', 'role_user.organization_id']);
        if ($assignment === null) {
            throw new AuthorizationException;
        }

        $role = $this->assertAssignable($actor, (string) $assignment->code);
        $this->roles->revoke($target, $role, $assignment->organization_id, $actor, $reason);
    }

    /**
     * Penetapan Super Admin hanya lewat persetujuan Super Admin kedua (docs/07 §3).
     *
     * @throws AuthorizationException|ValidationException
     */
    public function requestSuperAdmin(User $actor, User $target, string $reason): void
    {
        if (! $actor->hasRole(RoleCode::SuperAdmin) || $actor->id === $target->id) {
            throw new AuthorizationException;
        }
        if ($target->hasRole(RoleCode::SuperAdmin) || ! $target->isActive()) {
            throw ValidationException::withMessages(['reason' => 'Pengguna sudah Super Admin atau belum aktif.']);
        }
        if (RoleAssigner::conflictsWith($target, RoleCode::SuperAdmin)) {
            throw ValidationException::withMessages(['reason' => 'Akun peserta tidak dapat menjadi Super Admin — gunakan akun terpisah.']);
        }

        $this->approvals->request('role.assign_super_admin', 'user', $target->id, ['email' => $target->email], $reason, $actor);
    }

    /** @throws AuthorizationException */
    public function resetMfa(User $actor, User $target, string $ticket, string $method, ?string $note): void
    {
        $this->assertCanManage($actor, $target);
        if (! $target->hasConfirmedMfa()) {
            throw ValidationException::withMessages(['ticket_reference' => 'Pengguna ini belum mengaktifkan MFA.']);
        }

        $reason = "Tiket {$ticket}; verifikasi: {$method}".($note !== null && $note !== '' ? "; {$note}" : '');
        DB::transaction(function () use ($actor, $target, $reason, $ticket, $method): void {
            DB::table('user_mfa_methods')->where('user_id', $target->id)->delete();
            DB::table('mfa_recovery_codes')->where('user_id', $target->id)->delete();
            $target->forceFill(['session_version' => $target->session_version + 1])->save();
            $this->devices->revokeOthers($target, null, 'mfa_reset');
            $this->audit->record('user.mfa_reset', $actor, 'user', $target->id, ['ticket' => $ticket, 'verification' => $method], $reason);
        });

        $this->securityEvents->log('mfa_reset_by_admin', 'warning', $target->id, ['by' => $actor->id, 'ticket' => $ticket]);
        $target->notify(new MfaResetNotification);
    }

    /** @throws AuthorizationException */
    private function assertCanManage(User $actor, User $target): void
    {
        if (! $this->canManage($actor, $target)) {
            throw new AuthorizationException;
        }
    }

    /** @throws ValidationException */
    private function assertAssignable(User $actor, string $roleCode): RoleCode
    {
        $role = RoleCode::tryFrom($roleCode);
        if ($role === RoleCode::SuperAdmin) {
            throw ValidationException::withMessages(['role' => 'Penetapan/pencabutan Super Admin memerlukan persetujuan Super Admin kedua dan belum tersedia di antarmuka ini.']);
        }
        if ($role === null || ! in_array($role, $this->assignableRoles($actor), true)) {
            throw ValidationException::withMessages(['role' => 'Anda tidak berwenang mengelola peran ini.']);
        }

        return $role;
    }

    /** @throws ValidationException */
    private function resolveOrganization(RoleCode $role, ?string $organizationId): ?string
    {
        if ($role->isPlatform()) {
            return null;
        }

        $organizationId = $organizationId === '' ? null : $organizationId;
        if ($organizationId === null) {
            if ($role->requiresOrganization()) {
                throw ValidationException::withMessages(['organization_id' => 'Peran ini wajib memilih organisasi.']);
            }

            return null;
        }

        if (! Str::isUuid($organizationId) || ! Organization::query()->whereKey($organizationId)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['organization_id' => 'Organisasi tidak ditemukan atau sudah diarsipkan.']);
        }

        return $organizationId;
    }

    private function addMembership(User $user, ?string $organizationId, User $actor): void
    {
        if ($organizationId === null) {
            return;
        }

        $updated = DB::table('organization_members')
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->update(['status' => 'active', 'approved_by' => $actor->id, 'approved_at' => now(), 'updated_at' => now()]);

        if ($updated === 0) {
            DB::table('organization_members')->insert([
                'id' => (string) Str::uuid7(),
                'organization_id' => $organizationId,
                'user_id' => $user->id,
                'status' => 'active',
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($user->primary_organization_id === null) {
            $user->forceFill(['primary_organization_id' => $organizationId])->save();
        }
    }

    public function sendInvitation(User $user, RoleCode $role): void
    {
        $hours = (int) config('security.invitation.ttl_hours');
        $token = $this->tokens->issueLink($user, OneTimeTokens::PURPOSE_INVITATION, $hours * 60);
        $user->notify(new InvitationNotification($token, $role->label(), $hours));
    }
}
