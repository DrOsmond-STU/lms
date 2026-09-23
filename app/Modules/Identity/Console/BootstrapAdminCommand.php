<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console;

use App\Modules\Access\RoleCode;
use App\Modules\Access\Services\AccessSynchronizer;
use App\Modules\Access\Services\RoleAssigner;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;

/**
 * Membuat Super Admin pertama (keamanan/02 SEC-AUTH-32). Menolak berjalan bila sudah ada
 * Super Admin. Tidak ada kata sandi yang ditetapkan: pengguna menerima tautan atur kata
 * sandi dan wajib mendaftarkan MFA pada login pertama.
 */
final class BootstrapAdminCommand extends Command
{
    protected $signature = 'stu:bootstrap-admin {--email= : Email Super Admin} {--name= : Nama lengkap}';

    protected $description = 'Membuat akun Super Admin pertama (sekali jalan)';

    public function handle(AccessSynchronizer $access, RoleAssigner $roles, AuditLogger $audit, TenantContext $tenant): int
    {
        $email = mb_strtolower(trim((string) $this->option('email')));
        $name = trim((string) $this->option('name'));

        $validator = Validator::make(['email' => $email, 'name' => $name], [
            'email' => ['required', 'email:rfc', 'max:254'],
            'name' => ['required', 'string', 'max:120'],
        ]);
        if ($validator->fails()) {
            $this->error(implode(' ', $validator->errors()->all()));

            return self::INVALID;
        }

        $tenant->applySystem();
        $access->sync();

        $exists = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.code', RoleCode::SuperAdmin->value)
            ->exists();
        if ($exists) {
            $this->error('Super Admin sudah ada. Penambahan Super Admin berikutnya wajib melalui persetujuan Super Admin lain.');

            return self::FAILURE;
        }

        $user = new User(['name' => $name, 'email' => $email]);
        $user->forceFill(['status' => 'active', 'email_verified_at' => now()])->save();
        $roles->assign($user, RoleCode::SuperAdmin, null, null);
        $audit->record('user.super_admin_bootstrapped', null, 'user', $user->id, ['email' => $email]);

        Password::sendResetLink(['email' => $email]);
        $tenant->clear();

        $this->info("Super Admin dibuat untuk {$email}. Tautan atur kata sandi telah dikirim ke email tersebut.");

        return self::SUCCESS;
    }
}
