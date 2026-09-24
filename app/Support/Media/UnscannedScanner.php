<?php

declare(strict_types=1);

namespace App\Support\Media;

/**
 * Hanya untuk lokal/staging tanpa ClamAV: menandai berkas bersih TANPA pemindaian dan
 * mencatat pemindai `none` agar terlihat di data. ProductionGuard menolak driver ini di
 * produksi.
 */
final class UnscannedScanner implements MalwareScanner
{
    public function scan(string $absolutePath): ScanResult
    {
        return new ScanResult('clean', 'none');
    }
}
