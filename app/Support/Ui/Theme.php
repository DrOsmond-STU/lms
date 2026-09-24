<?php

declare(strict_types=1);

namespace App\Support\Ui;

use Illuminate\Http\Request;

/**
 * Preferensi tema tampilan (terang/gelap/ikuti sistem). Disimpan di cookie non-rahasia
 * yang diset skrip klien, dibaca server agar atribut data-theme sudah ada saat halaman
 * pertama digambar — tanpa skrip inline (CSP) dan tanpa kedipan tema.
 */
final class Theme
{
    public const COOKIE = 'stu_theme';

    public const MODES = ['light', 'dark'];

    /** 'light' | 'dark', atau null bila mengikuti sistem. Nilai lain diabaikan. */
    public static function current(?Request $request = null): ?string
    {
        $value = ($request ?? request())->cookie(self::COOKIE);

        return is_string($value) && in_array($value, self::MODES, true) ? $value : null;
    }
}
