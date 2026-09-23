<?php

declare(strict_types=1);

namespace App\Modules\Access\Services;

use App\Modules\Access\Permissions;
use App\Modules\Access\RoleCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Menyelaraskan peran & izin di DB dengan katalog di kode (idempoten).
 */
final class AccessSynchronizer
{
    public function sync(): void
    {
        DB::transaction(function (): void {
            $now = now();

            foreach (Permissions::all() as $code) {
                DB::table('permissions')->insertOrIgnore([
                    'id' => (string) Str::uuid7(), 'code' => $code, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            DB::table('permissions')->whereNotIn('code', Permissions::all())->delete();

            $permissionIds = DB::table('permissions')->pluck('id', 'code');

            foreach (RoleCode::cases() as $role) {
                DB::table('roles')->upsert([[
                    'id' => (string) Str::uuid7(),
                    'code' => $role->value,
                    'name' => $role->label(),
                    'is_platform' => $role->isPlatform(),
                    'is_system' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]], ['code'], ['name', 'is_platform', 'is_system', 'updated_at']);

                $roleId = DB::table('roles')->where('code', $role->value)->value('id');
                DB::table('permission_role')->where('role_id', $roleId)->delete();
                DB::table('permission_role')->insert(array_map(
                    fn (string $code): array => ['role_id' => $roleId, 'permission_id' => $permissionIds[$code]],
                    Permissions::forRole($role->value),
                ));
            }
        });
    }
}
