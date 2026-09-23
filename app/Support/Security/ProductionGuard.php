<?php

declare(strict_types=1);

namespace App\Support\Security;

use RuntimeException;

/**
 * Menolak boot aplikasi bila konfigurasi produksi tidak aman
 * (keamanan/13 SEC-INFRA-22, keamanan/04 SEC-INPUT-27).
 */
final class ProductionGuard
{
    /**
     * @return list<string> daftar pelanggaran
     */
    public static function violations(): array
    {
        $violations = [];

        if (config('app.debug') === true) {
            $violations[] = 'APP_DEBUG harus false di produksi.';
        }
        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $violations[] = 'APP_URL harus memakai https:// di produksi.';
        }
        if (config('session.secure') !== true) {
            $violations[] = 'SESSION_SECURE_COOKIE harus true di produksi.';
        }
        if (! str_starts_with((string) config('session.cookie'), '__Host-')) {
            $violations[] = 'Nama cookie sesi harus berawalan __Host-.';
        }
        if (config('hashing.driver') !== 'argon2id') {
            $violations[] = 'HASH_DRIVER harus argon2id.';
        }
        if (strlen((string) base64_decode((string) config('security.pepper'), true)) < 32) {
            $violations[] = 'SECURITY_PEPPER wajib diisi (≥ 32 byte, base64).';
        }
        if (class_exists('Laravel\\Telescope\\TelescopeServiceProvider')) {
            $violations[] = 'Laravel Telescope tidak boleh terpasang di produksi.';
        }

        return $violations;
    }

    public static function enforce(): void
    {
        $violations = self::violations();

        if ($violations !== []) {
            throw new RuntimeException('Konfigurasi produksi tidak aman: '.implode(' ', $violations));
        }
    }
}
