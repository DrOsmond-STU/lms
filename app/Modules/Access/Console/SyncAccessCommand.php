<?php

declare(strict_types=1);

namespace App\Modules\Access\Console;

use App\Modules\Access\Services\AccessSynchronizer;
use Illuminate\Console\Command;

final class SyncAccessCommand extends Command
{
    protected $signature = 'stu:access-sync';

    protected $description = 'Menyelaraskan peran & izin di basis data dengan katalog di kode';

    public function handle(AccessSynchronizer $synchronizer): int
    {
        $synchronizer->sync();
        $this->info('Peran & izin telah diselaraskan.');

        return self::SUCCESS;
    }
}
