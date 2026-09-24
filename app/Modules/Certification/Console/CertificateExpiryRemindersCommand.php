<?php

declare(strict_types=1);

namespace App\Modules\Certification\Console;

use App\Modules\Certification\Models\Certificate;
use App\Modules\Notification\Services\Notifier;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Pengingat N hari sebelum sertifikat kedaluwarsa (FR-CERT-012; N di Pengaturan Sistem → Sertifikat). Status kedaluwarsa sendiri
 * diturunkan dari `valid_until` saat verifikasi, sehingga tidak perlu job pengubah status.
 */
final class CertificateExpiryRemindersCommand extends Command
{
    protected $signature = 'stu:certificates-expiry-reminders';

    protected $description = 'Kirim pengingat sertifikat yang akan segera kedaluwarsa (lihat Pengaturan Sistem)';

    public function handle(Notifier $notifier, TenantContext $tenant): int
    {
        $count = $tenant->runAsSystem(function () use ($notifier): int {
            $sent = 0;
            Certificate::query()->with('user')->where('status', 'active')->whereNull('expiry_reminded_at')
                ->whereNotNull('valid_until')->whereBetween('valid_until', [now()->toDateString(), now()->addDays((int) config('lms.certificate_expiry_reminder_days'))->toDateString()])
                ->limit(1000)->get()
                ->each(function (Certificate $certificate) use ($notifier, &$sent): void {
                    $notifier->send($certificate->user, 'certificate', 'Sertifikat segera kedaluwarsa',
                        'Sertifikat '.$certificate->program_name.' berlaku hingga '.$certificate->valid_until?->translatedFormat('d F Y').'.', '/peserta/sertifikat', email: true);
                    $certificate->forceFill(['expiry_reminded_at' => now()])->save();
                    $sent++;
                });

            return $sent;
        });

        $this->info("Pengingat dikirim: {$count}");

        return self::SUCCESS;
    }
}
