<?php

declare(strict_types=1);

namespace App\Modules\Cms\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Filter program di beranda: jenis kompetensi, topik, harga, jadwal mulai, durasi. Nilai
 * setiap program dihitung ke "ember" yang sama dengan yang dipakai skrip klien
 * (atribut data-*), sehingga filter bekerja identik dengan maupun tanpa JavaScript.
 */
final class ProgramFilter
{
    public const PRICES = ['gratis' => 'Gratis', 'lt1jt' => '< Rp1 juta', '1-5jt' => 'Rp1–5 juta', 'gt5jt' => '> Rp5 juta'];

    public const SCHEDULES = ['bulan-ini' => 'Mulai bulan ini', 'bulan-depan' => 'Mulai bulan depan', '3-bulan' => 'Dalam 3 bulan'];

    public const DURATIONS = ['singkat' => 'Singkat (≤ 8 jam)', 'menengah' => 'Menengah (9–40 jam)', 'panjang' => 'Panjang (> 40 jam)'];

    /** @var array<string, string> */
    public array $active = [];

    /** @param list<string> $topics */
    public static function fromRequest(Request $request, array $topics): self
    {
        $filter = new self;
        $allowed = [
            'jenis' => ['international', 'bnsp'],
            'topik' => $topics,
            'harga' => array_keys(self::PRICES),
            'jadwal' => array_keys(self::SCHEDULES),
            'durasi' => array_keys(self::DURATIONS),
        ];
        foreach ($allowed as $key => $values) {
            $value = $request->query($key);
            if (is_string($value) && in_array($value, $values, true)) {
                $filter->active[$key] = $value;
            }
        }

        return $filter;
    }

    public static function priceBucket(int $price): string
    {
        return match (true) {
            $price <= 0 => 'gratis',
            $price < 1_000_000 => 'lt1jt',
            $price <= 5_000_000 => '1-5jt',
            default => 'gt5jt',
        };
    }

    public static function durationBucket(int $hours): string
    {
        return match (true) {
            $hours <= 8 => 'singkat',
            $hours <= 40 => 'menengah',
            default => 'panjang',
        };
    }

    /** Selisih bulan kalender antara bulan ini dan tanggal mulai kelas terdekat; null bila tidak ada. */
    public static function monthOffset(?Carbon $start): ?int
    {
        if ($start === null) {
            return null;
        }
        $now = now()->timezone(display_tz())->startOfMonth();

        return max(0, ($start->year - $now->year) * 12 + $start->month - $now->month);
    }

    /**
     * @param  array{jenis: string, topik: list<string>, harga: string, jadwal: int|null, durasi: string}  $buckets
     */
    public function matches(array $buckets): bool
    {
        foreach ($this->active as $key => $value) {
            $ok = match ($key) {
                'jenis' => $buckets['jenis'] === $value,
                'topik' => in_array($value, $buckets['topik'], true),
                'harga' => $buckets['harga'] === $value,
                'durasi' => $buckets['durasi'] === $value,
                'jadwal' => $buckets['jadwal'] !== null && match ($value) {
                    'bulan-ini' => $buckets['jadwal'] === 0,
                    'bulan-depan' => $buckets['jadwal'] === 1,
                    default => $buckets['jadwal'] <= 2,
                },
                default => true,
            };
            if (! $ok) {
                return false;
            }
        }

        return true;
    }
}
