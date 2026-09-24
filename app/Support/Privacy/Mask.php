<?php

declare(strict_types=1);

namespace App\Support\Privacy;

/**
 * Penyamaran data pribadi untuk tampilan (docs/08 §6, keamanan/12): tabel & halaman yang
 * tidak memerlukan nilai lengkap hanya menampilkan versi tersamar.
 */
final class Mask
{
    public static function email(string $email): string
    {
        $at = strrpos($email, '@');
        if ($at === false || $at === 0) {
            return '***';
        }

        return mb_substr($email, 0, 1).'***'.substr($email, $at);
    }

    public static function ip(?string $ip): string
    {
        if ($ip === null || $ip === '') {
            return '—';
        }
        if (str_contains($ip, ':')) {
            return implode(':', array_slice(explode(':', $ip), 0, 3)).':…';
        }

        return preg_replace('/\.\d+$/', '.x', $ip) ?? '—';
    }
}
