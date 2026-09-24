<?php

declare(strict_types=1);

namespace App\Support\Database;

/**
 * Meloloskan metakarakter LIKE/ILIKE dari input pengguna agar pencarian tidak dapat
 * dipakai untuk pola wildcard yang mahal (keamanan/04).
 */
final class Like
{
    public static function contains(string $value): string
    {
        return '%'.addcslashes($value, '\\%_').'%';
    }
}
