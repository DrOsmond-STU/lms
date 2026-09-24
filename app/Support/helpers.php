<?php

declare(strict_types=1);

use App\Modules\Settings\Services\SystemSettings;

if (! function_exists('setting')) {
    /** Nilai efektif Pengaturan Sistem (lihat SystemSettings::DEFINITIONS). */
    function setting(string $key): mixed
    {
        return SystemSettings::get($key);
    }
}

if (! function_exists('display_tz')) {
    /** Zona waktu tampilan yang diatur di Pengaturan Sistem → Umum. */
    function display_tz(): string
    {
        return (string) config('lms.display_timezone', 'Asia/Jakarta');
    }
}

if (! function_exists('tz_label')) {
    /** Singkatan zona waktu tampilan (WIB/WITA/WIT). */
    function tz_label(): string
    {
        return match (display_tz()) {
            'Asia/Makassar' => 'WITA',
            'Asia/Jayapura' => 'WIT',
            default => 'WIB',
        };
    }
}

if (! function_exists('fmt_score')) {
    /** Format skor/poin untuk tampilan: 2 desimal maksimum, koma desimal, tanpa nol berlebih (0 → "0"). */
    function fmt_score(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    }
}
