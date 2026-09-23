<?php

declare(strict_types=1);

namespace App\Modules\Access\Services;

use App\Modules\Access\RoleCode;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Penetapan & pencabutan peran (keamanan/03 SEC-AUTHZ-18). Setiap perubahan tercatat
 * di audit dan mencabut sesi aktif pengguna agar hak baru berlaku seketika.
 */
final class RoleAssigner
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function assign(User $user, RoleCode $role, ?string $organizationId, ?User $grantedBy): void
    {
        if ($role->isPlatform() && $organizationId !== null) {
            throw new InvalidArgumentException('Peran platform tidak boleh terikat organisasi.');
        }
        if (! $role->isPlatform() && $organizationId === null) {
            throw new InvalidArgumentException('Peran organisasi wajib memiliki organization_id.');
        }

        DB::transaction(function () use ($user, $role, $organizationId, $grantedBy): void {
            $roleId = DB::table('roles')->where('code', $role->value)->value('id');
            $exists = DB::table('role_user')
                ->where(['user_id' => $user->id, 'role_id' => $roleId, 'organization_id' => $organizationId])
                ->exists();
            if ($exists) {
                return;
            }

            DB::table('role_user')->insert([
                'id' => (string) Str::uuid7(),
                'user_id' => $user->id,
                'role_id' => $roleId,
                'organization_id' => $organizationId,
                'granted_by' => $grantedBy?->id,
                'granted_at' => now(),
            ]);

            DB::table('users')->where('id', $user->id)->increment('session_version');

            $this->audit->record('user.role_assigned', $grantedBy, 'user', $user->id, [
                'role' => $role->value, 'organization_id' => $organizationId,
            ], organizationId: $organizationId);
        });

        $user->flushPermissionCache();
    }

    public function revoke(User $user, RoleCode $role, ?string $organizationId, ?User $revokedBy, string $reason): void
    {
        DB::transaction(function () use ($user, $role, $organizationId, $revokedBy, $reason): void {
            $roleId = DB::table('roles')->where('code', $role->value)->value('id');
            DB::table('role_user')
                ->where(['user_id' => $user->id, 'role_id' => $roleId, 'organization_id' => $organizationId])
                ->delete();
            DB::table('users')->where('id', $user->id)->increment('session_version');

            $this->audit->record('user.role_revoked', $revokedBy, 'user', $user->id, [
                'role' => $role->value, 'organization_id' => $organizationId,
            ], $reason, $organizationId);
        });

        $user->flushPermissionCache();
    }
}
