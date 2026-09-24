<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console;

use App\Modules\Access\RoleCode;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\UserAdministration;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Kirim ulang undangan atur kata sandi dari CLI — untuk akun yang belum pernah
 * menetapkan kata sandi (mis. undangan Super Admin pertama kedaluwarsa sebelum
 * ada admin lain yang bisa mengirim ulang lewat UI).
 */
final class ResendInvitationCommand extends Command
{
    protected $signature = 'stu:resend-invitation {email : Email akun yang undangannya dikirim ulang}';

    protected $description = 'Kirim ulang undangan atur kata sandi untuk akun yang belum memiliki kata sandi';

    public function handle(TenantContext $tenant, UserAdministration $users, AuditLogger $audit): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        if (Validator::make(['email' => $email], ['email' => ['required', 'email:rfc', 'max:254']])->fails()) {
            $this->error('Email tidak valid.');

            return self::INVALID;
        }

        return $tenant->runAsSystem(function () use ($email, $users, $audit): int {
            $user = User::query()->where('email', $email)->first();
            if ($user === null) {
                $this->error('Akun tidak ditemukan.');

                return self::FAILURE;
            }
            if ($user->password !== null || ! in_array($user->status, ['pending_verification', 'active'], true)) {
                $this->error('Akun sudah memiliki kata sandi atau statusnya tidak memungkinkan undangan; gunakan alur lupa kata sandi atau kelola akun lewat Admin → Pengguna.');

                return self::FAILURE;
            }

            // Undangan hanya bisa diterima oleh akun berstatus pending_verification.
            if ($user->status !== 'pending_verification') {
                $user->forceFill(['status' => 'pending_verification'])->save();
            }

            $roles = $user->roleCodes();
            $role = $roles[0] ?? RoleCode::Participant;
            $users->sendInvitation($user, $role);
            $audit->record('user.invitation_resent', null, 'user', $user->id, ['email' => $email, 'via' => 'cli']);

            $hours = (int) config('security.invitation.ttl_hours');
            $this->info("Undangan {$role->label()} dikirim ulang ke {$email} (berlaku {$hours} jam).");

            return self::SUCCESS;
        });
    }
}
