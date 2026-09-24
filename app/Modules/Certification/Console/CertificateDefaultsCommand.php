<?php

declare(strict_types=1);

namespace App\Modules\Certification\Console;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Certification\Models\CertificateTemplate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Template sertifikat awal per kategori (sekali jalan, idempoten). Perubahan/aktivasi
 * berikutnya melalui UI dengan persetujuan kedua (SEC-CERT-04).
 */
final class CertificateDefaultsCommand extends Command
{
    protected $signature = 'stu:certificate-defaults';

    protected $description = 'Buat template sertifikat default bila belum ada template aktif';

    public function handle(AuditLogger $audit, TenantContext $tenant): int
    {
        $tenant->runAsSystem(function () use ($audit): void {
            foreach (['international' => 'Sertifikat Pelatihan', 'bnsp' => 'Sertifikat Pelatihan Berbasis Kompetensi'] as $category => $title) {
                if (CertificateTemplate::query()->where('category', $category)->where('is_active', true)->exists()) {
                    continue;
                }
                $template = new CertificateTemplate;
                $template->forceFill([
                    'name' => 'Default '.($category === 'bnsp' ? 'BNSP' : 'Internasional'),
                    'category' => $category,
                    'version' => (int) CertificateTemplate::query()->where('category', $category)->whereNull('program_id')->max('version') + 1,
                    'title_text' => $title,
                    'body_text' => 'telah menyelesaikan dan dinyatakan lulus {kategori} program berikut yang diselenggarakan oleh {penyelenggara}.',
                    'signatory_name' => 'Direktur Akademik',
                    'signatory_title' => 'Semesta Teknologi Utama',
                    'accent_color' => $category === 'bnsp' ? '#0c766e' : '#0e3a63',
                    'is_active' => true,
                ])->save();
                $audit->record('certificate_template.bootstrapped', null, 'certificate_template', $template->id, ['category' => $category]);
                $this->info("Template default {$category} dibuat.");
            }
        });

        return self::SUCCESS;
    }
}
