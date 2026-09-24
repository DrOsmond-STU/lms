<?php

declare(strict_types=1);

namespace App\Support\Media;

final readonly class ScanResult
{
    /**
     * @param  'clean'|'infected'|'error'  $status
     */
    public function __construct(
        public string $status,
        public string $scanner,
        public ?string $signature = null,
    ) {}
}
