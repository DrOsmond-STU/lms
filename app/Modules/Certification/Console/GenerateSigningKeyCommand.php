<?php

declare(strict_types=1);

namespace App\Modules\Certification\Console;

use App\Modules\Certification\Services\CertificateSigner;
use App\Modules\Cms\Models\SiteProfile;
use Illuminate\Console\Command;

/**
 * Membuat sertifikat penandatangan UJI (staging) — produksi memakai PSrE/KMS (SEC-CERT-07/08).
 */
final class GenerateSigningKeyCommand extends Command
{
    protected $signature = 'stu:signing-key {--force : Ganti kunci yang sudah ada}';

    protected $description = 'Buat kunci & sertifikat uji untuk tanda tangan PDF sertifikat';

    public function handle(CertificateSigner $signer): int
    {
        if (app()->isProduction()) {
            $this->error('Produksi wajib memakai sertifikat PSrE/KMS, bukan sertifikat uji.');

            return self::FAILURE;
        }
        if ($signer->isConfigured() && ! $this->option('force')) {
            $this->info('Kunci penandatangan sudah ada. Fingerprint: '.$signer->fingerprint());

            return self::SUCCESS;
        }

        $signer->generate(config('app.name').' Test Signer ('.config('app.env').')', SiteProfile::current()->company_name);
        $this->info('Kunci penandatangan uji dibuat. Fingerprint: '.$signer->fingerprint());

        return self::SUCCESS;
    }
}
