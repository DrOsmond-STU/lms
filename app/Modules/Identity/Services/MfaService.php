<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Audit\Services\SecurityEventLogger;
use App\Modules\Identity\Models\MfaMethod;
use App\Modules\Identity\Models\RecoveryCode;
use App\Modules\Identity\Models\User;
use App\Support\Security\TokenHasher;
use App\Support\Security\Totp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pendaftaran & verifikasi MFA TOTP serta kode pemulihan (keamanan/02 SEC-AUTH-13..14).
 */
final class MfaService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SecurityEventLogger $securityEvents,
    ) {}

    /** Verifikasi kode TOTP dengan anti-replay yang atomik. */
    public function verifyTotp(User $user, string $code): bool
    {
        $method = $user->totpMethod();
        if ($method === null || $method->secret_encrypted === null) {
            return false;
        }

        $step = Totp::verify($method->secret_encrypted, trim($code), $method->last_totp_step);
        if ($step === null) {
            return false;
        }

        // Update bersyarat: bila dua request memakai kode yang sama bersamaan, hanya satu menang.
        $updated = MfaMethod::query()
            ->whereKey($method->id)
            ->where(fn ($query) => $query->whereNull('last_totp_step')->orWhere('last_totp_step', '<', $step))
            ->update(['last_totp_step' => $step, 'last_used_at' => now()]);

        return $updated === 1;
    }

    /** Memakai kode pemulihan sekali pakai. */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $normalized = strtoupper(str_replace([' ', '-'], '', trim($code)));
        if (preg_match('/^[A-Z0-9]{10}$/', $normalized) !== 1) {
            return false;
        }

        $used = RecoveryCode::query()
            ->where('user_id', $user->id)
            ->where('code_hash', TokenHasher::hash($normalized, 'mfa-recovery'))
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        if ($used !== 1) {
            return false;
        }

        $this->securityEvents->log('authn_mfa_recovery_used', 'warning', $user->id, [
            'remaining' => RecoveryCode::query()->where('user_id', $user->id)->whereNull('used_at')->count(),
        ]);

        return true;
    }

    /**
     * Mengonfirmasi pendaftaran TOTP & menerbitkan kode pemulihan baru.
     *
     * @return list<string>|null kode pemulihan (hanya ditampilkan sekali) atau null bila kode salah
     */
    public function confirmTotp(User $user, string $secret, string $code): ?array
    {
        $step = Totp::verify($secret, trim($code), null);
        if ($step === null) {
            return null;
        }

        $codes = [];
        DB::transaction(function () use ($user, $secret, $step, &$codes): void {
            MfaMethod::query()->where('user_id', $user->id)->where('type', 'totp')->delete();

            $method = new MfaMethod;
            $method->forceFill([
                'user_id' => $user->id,
                'type' => 'totp',
                'secret_encrypted' => $secret,
                'label' => 'Aplikasi autentikator',
                'last_totp_step' => $step,
                'confirmed_at' => now(),
            ])->save();

            $codes = $this->regenerateRecoveryCodes($user);

            $this->audit->record('user.mfa_enabled', $user, 'user', $user->id, ['type' => 'totp']);
        });

        $this->securityEvents->log('authn_mfa_enabled', 'info', $user->id, ['type' => 'totp']);

        return $codes;
    }

    /** @return list<string> */
    public function regenerateRecoveryCodes(User $user): array
    {
        RecoveryCode::query()->where('user_id', $user->id)->delete();

        $alphabet = 'ABCDEFGHJKMNPQRSTVWXYZ23456789';
        $codes = [];
        for ($i = 0; $i < (int) config('security.mfa.recovery_codes', 10); $i++) {
            $raw = '';
            for ($c = 0; $c < 10; $c++) {
                $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $codes[] = substr($raw, 0, 5).'-'.substr($raw, 5);

            $record = new RecoveryCode;
            $record->forceFill([
                'id' => (string) Str::uuid7(),
                'user_id' => $user->id,
                'code_hash' => TokenHasher::hash($raw, 'mfa-recovery'),
                'created_at' => now(),
            ])->save();
        }

        return $codes;
    }
}
