<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Access\Services\AccessSynchronizer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(AccessSynchronizer $access, TenantContext $tenant): void
    {
        $tenant->applySystem();
        $access->sync();

        // Akun demo HANYA untuk lingkungan lokal (keamanan/02 SEC-AUTH-32).
        if (app()->isLocal()) {
            $this->call(LocalDemoSeeder::class);
        }

        $tenant->clear();
    }
}
