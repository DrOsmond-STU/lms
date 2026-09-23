<?php

declare(strict_types=1);

namespace App\Support\Security;

use RuntimeException;

/**
 * HMAC-SHA256 dengan pepper untuk token acak, OTP, kode pemulihan, dan blind index
 * (keamanan/06 §2). Bukan untuk kata sandi — kata sandi memakai Argon2id.
 */
final class TokenHasher
{
    public static function hash(string $value, string $purpose = 'token'): string
    {
        return hash_hmac('sha256', $purpose."\0".$value, self::pepper());
    }

    public static function equals(string $knownHash, string $value, string $purpose = 'token'): bool
    {
        return hash_equals($knownHash, self::hash($value, $purpose));
    }

    private static function pepper(): string
    {
        $pepper = base64_decode((string) config('security.pepper'), true);

        if ($pepper === false || strlen($pepper) < 32) {
            throw new RuntimeException('SECURITY_PEPPER belum dikonfigurasi dengan benar.');
        }

        return $pepper;
    }
}
