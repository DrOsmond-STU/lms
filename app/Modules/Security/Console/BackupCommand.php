<?php

declare(strict_types=1);

namespace App\Modules\Security\Console;

use App\Modules\Security\Services\BackupService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/** Backup terjadwal: basis data setiap hari (+ media bila diaktifkan), lalu pangkas yang kedaluwarsa. */
final class BackupCommand extends Command
{
    protected $signature = 'stu:backup {--media : Sertakan arsip media (storage/app/private)} {--force : Jalankan walau backup dinonaktifkan di pengaturan}';

    protected $description = 'Backup basis data (dan media) ke storage/app/backups, pangkas sesuai masa simpan';

    public function handle(BackupService $backups, TenantContext $tenant): int
    {
        if (! setting('backup.enabled') && ! $this->option('force')) {
            $this->warn('Backup otomatis dinonaktifkan di Pengaturan → Keamanan.');

            return self::SUCCESS;
        }

        return $tenant->runAsSystem(function () use ($backups): int {
            $db = $backups->database();
            $this->line('DB: '.$db->status.' '.$db->filename.' ('.$db->humanSize().', '.($db->method ?? '-').')'.($db->error !== null ? ' — '.$db->error : ''));
            if ($this->option('media') || setting('backup.include_media')) {
                $media = $backups->media();
                $this->line('Media: '.$media->status.' '.$media->filename.' ('.$media->humanSize().')'.($media->error !== null ? ' — '.$media->error : ''));
            }
            $this->line('Dipangkas: '.$backups->prune((int) setting('backup.keep_days')));

            return $db->status === 'ok' ? self::SUCCESS : self::FAILURE;
        });
    }
}
