<?php

declare(strict_types=1);

namespace App\Modules\Certification\Services;

/**
 * Kode verifikasi 60 bit CSPRNG, Crockford Base32 (12 karakter, tanpa I/L/O/U) — SEC-CERT-10.
 */
final class VerificationCode
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function generate(): string
    {
        $code = '';
        for ($i = 0; $i < 12; $i++) {
            $code .= self::ALPHABET[random_int(0, 31)];
        }

        return $code;
    }

    /** Normalisasi masukan: tidak peka huruf & tanda hubung; I/L→1, O→0 (Crockford). */
    public static function normalize(string $input): string
    {
        $value = strtoupper(preg_replace('/[\s-]+/', '', $input) ?? '');

        return strtr($value, ['I' => '1', 'L' => '1', 'O' => '0']);
    }

    public static function isWellFormed(string $code): bool
    {
        return preg_match('/^[0-9A-HJKMNP-TV-Z]{12}$/', $code) === 1;
    }
}
