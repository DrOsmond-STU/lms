<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\OneTimeToken;
use App\Modules\Identity\Models\User;
use App\Support\Security\TokenHasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Penerbitan & verifikasi token sekali pakai (docs/05 §4.1 `one_time_tokens`,
 * keamanan/02 SEC-AUTH-09, SEC-AUTH-21):
 *
 * - OTP 6 digit dari CSPRNG, di-hash HMAC bersama ID token (unik per penerbitan),
 *   maks. N percobaan per kode, kedaluwarsa singkat.
 * - Tautan: 256 bit acak (base64url), di-hash HMAC, sekali pakai.
 * - Penerbitan baru membatalkan token lama dengan tujuan yang sama.
 * - Konsumsi atomik (UPDATE … WHERE consumed_at IS NULL) mencegah pemakaian ganda paralel.
 */
final class OneTimeTokens
{
    public const PURPOSE_EMAIL_VERIFICATION = 'email_verification';

    public const PURPOSE_INVITATION = 'invitation';

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function issueOtp(User $user, string $purpose, int $ttlMinutes, array $metadata = []): string
    {
        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
        $id = (string) Str::uuid7();

        $this->store($user, $purpose, $id, self::otpHash($id, $code), now()->addMinutes($ttlMinutes), $metadata);

        return $code;
    }

    /**
     * Memverifikasi OTP terbaru pengguna. Mengembalikan token yang telah dikonsumsi bila
     * cocok; null bila salah/kedaluwarsa/percobaan habis (tanpa membedakan alasan).
     */
    public function verifyOtp(User $user, string $purpose, string $code, int $maxAttempts): ?OneTimeToken
    {
        /** @var OneTimeToken|null $token */
        $token = OneTimeToken::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest('created_at')
            ->first();

        if ($token === null || ! $token->isUsable() || $token->attempts >= $maxAttempts) {
            return null;
        }

        if (preg_match('/^\d{6}$/', $code) !== 1 || ! hash_equals($token->token_hash, self::otpHash($token->id, $code))) {
            OneTimeToken::query()->whereKey($token->id)->increment('attempts');

            return null;
        }

        return $this->consume($token) ? $token : null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function issueLink(User $user, string $purpose, int $ttlMinutes, array $metadata = []): string
    {
        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $this->store($user, $purpose, (string) Str::uuid7(), self::linkHash($purpose, $secret), now()->addMinutes($ttlMinutes), $metadata);

        return $secret;
    }

    /** Token tautan yang masih berlaku, tanpa mengonsumsinya (untuk menampilkan formulir). */
    public function findLink(string $purpose, string $secret): ?OneTimeToken
    {
        if (preg_match('/^[A-Za-z0-9_-]{43}$/', $secret) !== 1) {
            return null;
        }

        /** @var OneTimeToken|null $token */
        $token = OneTimeToken::query()
            ->where('purpose', $purpose)
            ->where('token_hash', self::linkHash($purpose, $secret))
            ->first();

        return $token !== null && $token->isUsable() ? $token : null;
    }

    public function consume(OneTimeToken $token): bool
    {
        $updated = OneTimeToken::query()
            ->whereKey($token->id)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->update(['consumed_at' => now()]);

        return $updated === 1;
    }

    public function revokeAll(User $user, ?string $purpose = null): void
    {
        OneTimeToken::query()
            ->where('user_id', $user->id)
            ->when($purpose !== null, fn ($query) => $query->where('purpose', $purpose))
            ->whereNull('consumed_at')
            ->delete();
    }

    public function hasPending(User $user, string $purpose): bool
    {
        return OneTimeToken::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function store(User $user, string $purpose, string $id, string $hash, \DateTimeInterface $expiresAt, array $metadata): void
    {
        DB::transaction(function () use ($user, $purpose, $id, $hash, $expiresAt, $metadata): void {
            $this->revokeAll($user, $purpose);

            $token = new OneTimeToken;
            $token->forceFill([
                'id' => $id,
                'user_id' => $user->id,
                'purpose' => $purpose,
                'token_hash' => $hash,
                'expires_at' => $expiresAt,
                'metadata' => $metadata === [] ? null : $metadata,
            ])->save();
        });
    }

    private static function otpHash(string $tokenId, string $code): string
    {
        return TokenHasher::hash($tokenId.':'.$code, 'one-time-otp');
    }

    private static function linkHash(string $purpose, string $secret): string
    {
        return TokenHasher::hash($secret, 'one-time-link:'.$purpose);
    }
}
