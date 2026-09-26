<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Access\RoleCode;
use App\Modules\Access\Services\RoleAssigner;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Data demo sintetis untuk pengembangan lokal. Menolak berjalan di luar APP_ENV=local.
 * Kata sandi semua akun demo: lihat konstanta PASSWORD (hanya lokal).
 */
final class LocalDemoSeeder extends Seeder
{
    public const PASSWORD = 'Demo-Lokal-2026!';

    public function run(RoleAssigner $roles): void
    {
        if (! app()->isLocal()) {
            throw new RuntimeException('LocalDemoSeeder hanya boleh dijalankan di lingkungan lokal.');
        }

        $institution = $this->organization('Universitas Semesta Teknologi Utama', 'USTU', 'institution');
        $corporate = $this->organization('PT Maju Bersama Indonesia', 'PMB', 'corporate');

        $accounts = [
            ['Dewi Anggraini', 'superadmin@stu-lms.test', RoleCode::SuperAdmin, null],
            ['Admin Akademik', 'akademik@stu-lms.test', RoleCode::AcademicAdmin, null],
            ['Admin Keuangan', 'keuangan@stu-lms.test', RoleCode::FinanceAdmin, null],
            ['Hendra Saputra', 'hr@stu-lms.test', RoleCode::OrgAdmin, $corporate->id],
            ['Sari Wulandari', 'supervisor@stu-lms.test', RoleCode::Supervisor, $corporate->id],
            ['Andi Wijaya', 'trainer@stu-lms.test', RoleCode::Trainer, $institution->id],
            ['Raka Prasetya', 'peserta@stu-lms.test', RoleCode::Participant, $institution->id],
            ['Wahyu Saputra', 'karyawan@stu-lms.test', RoleCode::Participant, $corporate->id],
        ];

        foreach ($accounts as [$name, $email, $role, $organizationId]) {
            $user = User::query()->firstWhere('email', $email) ?? new User(['name' => $name, 'email' => $email]);
            $user->forceFill([
                'password' => self::PASSWORD,
                'status' => 'active',
                'email_verified_at' => now(),
                'primary_organization_id' => $organizationId,
            ])->save();

            $roles->assign($user, $role, $organizationId, null);
            foreach (['terms' => 'legal.terms_version', 'privacy' => 'legal.privacy_version'] as $document => $key) {
                DB::table('consents')->insertOrIgnore([
                    'id' => (string) Str::uuid7(), 'user_id' => $user->id, 'document' => $document,
                    'version' => (string) config($key), 'accepted_at' => now(), 'channel' => 'registration',
                ]);
            }

            if ($organizationId !== null) {
                DB::table('organization_members')->insertOrIgnore([
                    'id' => (string) Str::uuid7(), 'organization_id' => $organizationId, 'user_id' => $user->id,
                    'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Akun demo lokal dibuat (kata sandi: '.self::PASSWORD.'). Akun non-peserta wajib mengaktifkan MFA saat login pertama.');
    }

    private function organization(string $name, string $code, string $type): Organization
    {
        $organization = Organization::query()->firstWhere('code', $code) ?? new Organization(['name' => $name]);
        $organization->forceFill(['code' => $code, 'type' => $type, 'status' => 'active', 'settings' => []])->save();

        return $organization;
    }
}
