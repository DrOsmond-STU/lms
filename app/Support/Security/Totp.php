<?php

declare(strict_types=1);

namespace App\Support\Security;

/**
 * TOTP RFC 6238 (HMAC-SHA1, 6 digit, periode 30 detik) — keamanan/02 SEC-AUTH-13.
 */
final class Totp
{
    public const PERIOD = 30;

    public const DIGITS = 6;

    /** Secret 160-bit dari CSPRNG, dikodekan Base32. */
    public static function generateSecret(): string
    {
        return Base32::encode(random_bytes(20));
    }

    public static function currentStep(?int $timestamp = null): int
    {
        return intdiv($timestamp ?? time(), self::PERIOD);
    }

    public static function codeAt(string $base32Secret, int $step): string
    {
        $key = Base32::decode($base32Secret);
        $counter = pack('N2', ($step >> 32) & 0xFFFFFFFF, $step & 0xFFFFFFFF);
        $hmac = hash_hmac('sha1', $counter, $key, true);
        $offset = ord($hmac[19]) & 0x0F;
        $binary = ((ord($hmac[$offset]) & 0x7F) << 24)
            | ((ord($hmac[$offset + 1]) & 0xFF) << 16)
            | ((ord($hmac[$offset + 2]) & 0xFF) << 8)
            | (ord($hmac[$offset + 3]) & 0xFF);

        return str_pad((string) ($binary % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Memverifikasi kode dengan toleransi ±1 langkah dan anti-replay.
     *
     * @return int|null langkah waktu yang cocok (untuk disimpan sebagai last_step), null bila gagal
     */
    public static function verify(string $base32Secret, string $code, ?int $lastUsedStep, ?int $timestamp = null): ?int
    {
        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return null;
        }

        $current = self::currentStep($timestamp);
        foreach ([$current - 1, $current, $current + 1] as $step) {
            if ($lastUsedStep !== null && $step <= $lastUsedStep) {
                continue; // anti-replay: kode lama/berulang ditolak
            }
            if (hash_equals(self::codeAt($base32Secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    public static function provisioningUri(string $base32Secret, string $accountName, string $issuer): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($accountName),
            $base32Secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD,
        );
    }
}
