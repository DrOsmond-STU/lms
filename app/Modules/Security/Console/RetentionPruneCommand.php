<?php

declare(strict_types=1);

namespace App\Modules\Security\Console;

use App\Modules\Security\Services\RetentionPruner;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/** Hapus data operasional yang melewati masa simpan (Pengaturan → Keamanan → Retensi). */
final class RetentionPruneCommand extends Command
{
    protected $signature = 'stu:retention-prune';

    protected $description = 'Pangkas notifikasi, sesi, percakapan AI, pesan keluar, dan event keamanan sesuai retensi';

    public function handle(RetentionPruner $pruner, TenantContext $tenant): int
    {
        $deleted = $tenant->runAsSystem(fn (): array => $pruner->run());
        foreach ($deleted as $label => $count) {
            $this->line($label.': '.$count);
        }

        return self::SUCCESS;
    }
}
