<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Audit\Services\SecurityEventLogger;
use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Langkah pertama login: verifikasi kata sandi dengan perlindungan brute force &
 * anti-enumerasi (keamanan/02 SEC-AUTH-01, SEC-AUTH-03..06).
 */
final class LoginService
{
    public const GENERIC_ERROR = 'Email atau kata sandi salah.';

    public const THROTTLED_ERROR = 'Terlalu banyak percobaan. Coba lagi dalam beberapa menit.';

    /**
     * Hash Argon2id tiruan untuk menyamakan waktu respons ketika akun tidak ditemukan.
     */
    private const DUMMY_HASH = '$argon2id$v=19$m=65536,t=3,p=1$akJVYXBudzAwQk9wOHpTVA$57bNESUhusbAYG7qK0TSyF3FEg5llOK4k1+Z3HGVxRk';

    public function __construct(
        private readonly Hasher $hasher,
        private readonly LoginThrottle $throttle,
        private readonly SecurityEventLogger $securityEvents,
        private readonly SessionAuthenticator $authenticator,
    ) {}

    /**
     * @return 'authenticated'|'mfa_challenge'
     *
     * @throws ValidationException
     */
    public function attempt(Request $request, string $email, string $password): string
    {
        $ip = (string) $request->ip();

        if ($this->throttle->tooManyAttempts($email, $ip)) {
            $this->securityEvents->log('rate_limit_exceeded', 'warning', null, [
                'scope' => 'login', 'retry_after' => $this->throttle->availableIn($email, $ip),
            ]);

            throw ValidationException::withMessages(['email' => self::THROTTLED_ERROR])->status(429);
        }

        /** @var User|null $user */
        $user = User::query()->where('email', trim($email))->first();
        $valid = $this->hasher->check($password, $user->password ?? self::DUMMY_HASH);

        if ($user === null || ! $valid || ! $user->isActive()) {
            $this->throttle->hit($email, $ip);
            $this->securityEvents->log('authn_login_fail', 'info', $user?->id, [
                'reason' => $user === null ? 'unknown_account' : (! $valid ? 'bad_password' : 'inactive_account'),
            ]);

            throw ValidationException::withMessages(['email' => self::GENERIC_ERROR]);
        }

        $this->throttle->clearAccount($email);

        if ($this->hasher->needsRehash((string) $user->password)) {
            $user->forceFill(['password' => $password])->saveQuietly();
        }

        if ($user->hasConfirmedMfa()) {
            $request->session()->regenerate(true);
            $request->session()->put([
                'login.pending_user_id' => $user->id,
                'login.pending_at' => now()->getTimestamp(),
                'login.mfa_attempts' => 0,
            ]);

            return 'mfa_challenge';
        }

        // Peran wajib-MFA tanpa faktor terdaftar: sesi dibuat tetapi middleware `mfa`
        // hanya mengizinkan halaman pendaftaran MFA (SEC-AUTH-10).
        $this->authenticator->complete($request, $user, mfaVerified: false);

        return 'authenticated';
    }
}
