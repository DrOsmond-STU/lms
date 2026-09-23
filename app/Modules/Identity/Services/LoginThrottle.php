<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Support\Security\TokenHasher;
use Illuminate\Cache\RateLimiter;

/**
 * Rate limit login berlapis per akun & per IP (keamanan/02 SEC-AUTH-03..05).
 * Kunci akun di-hash sehingga email tidak tersimpan mentah di Redis.
 */
final class LoginThrottle
{
    public function __construct(private readonly RateLimiter $limiter) {}

    public function tooManyAttempts(string $email, string $ip): bool
    {
        foreach ($this->limits($email, $ip) as [$key, $max]) {
            if ($this->limiter->tooManyAttempts($key, $max)) {
                return true;
            }
        }

        return false;
    }

    public function hit(string $email, string $ip): void
    {
        foreach ($this->limits($email, $ip) as [$key, , $decay]) {
            $this->limiter->hit($key, $decay);
        }
    }

    /** Berhasil login: reset penghitung jangka pendek akun saja (bukan IP). */
    public function clearAccount(string $email): void
    {
        $this->limiter->clear($this->accountKey($email, 'short'));
    }

    public function availableIn(string $email, string $ip): int
    {
        $seconds = 0;
        foreach ($this->limits($email, $ip) as [$key, $max]) {
            if ($this->limiter->tooManyAttempts($key, $max)) {
                $seconds = max($seconds, $this->limiter->availableIn($key));
            }
        }

        return $seconds;
    }

    /** @return list<array{0: string, 1: int, 2: int}> */
    private function limits(string $email, string $ip): array
    {
        $config = config('security.login_throttle');

        return [
            [$this->accountKey($email, 'short'), $config['per_account_short']['attempts'], $config['per_account_short']['decay_seconds']],
            [$this->accountKey($email, 'long'), $config['per_account_long']['attempts'], $config['per_account_long']['decay_seconds']],
            ['login:ip:'.$ip, $config['per_ip']['attempts'], $config['per_ip']['decay_seconds']],
        ];
    }

    private function accountKey(string $email, string $window): string
    {
        return 'login:acct:'.$window.':'.TokenHasher::hash(mb_strtolower(trim($email)), 'login-throttle');
    }
}
