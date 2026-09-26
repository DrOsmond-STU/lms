<?php

declare(strict_types=1);

namespace App\Modules\Security\Console;

use App\Modules\Security\Services\SecurityMonitor;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/** Pindai event keamanan & audit untuk pola mencurigakan (tiap jam). */
final class SecurityScanCommand extends Command
{
    protected $signature = 'stu:security-scan';

    protected $description = 'Deteksi brute force, akun yang ditarget, anomali sesi, dan ekspor massal';

    public function handle(SecurityMonitor $monitor, TenantContext $tenant): int
    {
        $created = $tenant->runAsSystem(fn (): array => $monitor->scan());
        foreach ($created as $type => $count) {
            $this->line($type.': '.$count);
        }

        return self::SUCCESS;
    }
}
