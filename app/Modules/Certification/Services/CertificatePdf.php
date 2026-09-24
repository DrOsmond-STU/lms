<?php

declare(strict_types=1);

namespace App\Modules\Certification\Services;

use TCPDF;

/**
 * TCPDF tanpa teks promosi tersembunyi "Powered by TCPDF" di lapisan teks dokumen.
 */
final class CertificatePdf extends TCPDF
{
    public function __construct(string $orientation, string $unit, string $format)
    {
        parent::__construct($orientation, $unit, $format, true, 'UTF-8', false);
        $this->tcpdflink = false;
    }
}
