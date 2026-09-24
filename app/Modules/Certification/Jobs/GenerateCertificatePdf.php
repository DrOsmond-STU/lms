<?php

declare(strict_types=1);

namespace App\Modules\Certification\Jobs;

use App\Modules\Certification\Services\CertificateIssuer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Job pembuatan PDF sertifikat (SEC-CERT-01/20). Konteks tenant sistem disetel eksplisit
 * di awal dan dibersihkan di akhir (SEC-AUTHZ-20).
 */
final class GenerateCertificatePdf implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 300, 900];

    public function __construct(public readonly string $certificateId) {}

    public function handle(CertificateIssuer $issuer, TenantContext $tenant): void
    {
        $tenant->runAsSystem(fn () => $issuer->generatePdf($this->certificateId));
    }
}
