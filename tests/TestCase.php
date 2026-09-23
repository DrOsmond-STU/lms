<?php

declare(strict_types=1);

namespace Tests;

use App\Modules\Access\Services\AccessSynchronizer;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    private static bool $schemaReady = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Kunci uji dibangkitkan per proses — tidak ada kunci statis di repositori.
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'security.pepper' => base64_encode(random_bytes(32)),
        ]);

        if (! self::$schemaReady) {
            // Skema dibuat oleh pemilik (stu_migrator); uji berjalan sebagai stu_app.
            Artisan::call('migrate:fresh', ['--database' => 'pgsql_migrator', '--force' => true]);

            // Katalog peran/izin di-commit lewat koneksi migrator (di luar transaksi uji).
            $default = DB::getDefaultConnection();
            DB::setDefaultConnection('pgsql_migrator');
            try {
                app(AccessSynchronizer::class)->sync();
            } finally {
                DB::setDefaultConnection($default);
            }
            self::$schemaReady = true;
        }
    }
}
