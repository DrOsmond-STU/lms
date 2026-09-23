<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Modules\Identity\Models\User;
use Illuminate\Database\DatabaseManager;

/**
 * Menyetel variabel sesi PostgreSQL yang dibaca kebijakan Row-Level Security
 * (docs/05 §5, keamanan/03 SEC-AUTHZ-11).
 *
 * Aplikasi memakai koneksi non-persisten (satu koneksi per request PHP-FPM), sehingga
 * nilai disetel di awal request dan dibersihkan di akhir. Worker antrian yang berumur
 * panjang WAJIB memanggil applyFor()/applySystem() di awal setiap job dan clear()
 * di akhir (SEC-AUTHZ-20). Jangan memakai PgBouncer mode transaksi tanpa mengganti
 * mekanisme ini ke SET LOCAL.
 */
final class TenantContext
{
    private ?string $userId = null;

    /** @var list<string> */
    private array $organizationIds = [];

    private bool $platformStaff = false;

    public function __construct(private readonly DatabaseManager $db) {}

    public function applyFor(User $user): void
    {
        $this->userId = $user->id;
        $this->organizationIds = $user->tenantOrganizationIds();
        $this->platformStaff = $user->isPlatformStaff();
        $this->push();
    }

    /** Konteks proses sistem (seeder, job terjadwal tanpa aktor) — melihat semua tenant. */
    public function applySystem(): void
    {
        $this->userId = null;
        $this->organizationIds = [];
        $this->platformStaff = true;
        $this->push();
    }

    public function clear(): void
    {
        $this->userId = null;
        $this->organizationIds = [];
        $this->platformStaff = false;
        $this->push();
    }

    /** @return list<string> */
    public function organizationIds(): array
    {
        return $this->organizationIds;
    }

    public function includes(?string $organizationId): bool
    {
        return $this->platformStaff || ($organizationId !== null && in_array($organizationId, $this->organizationIds, true));
    }

    public function isPlatformStaff(): bool
    {
        return $this->platformStaff;
    }

    private function push(): void
    {
        $connection = $this->db->connection();
        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        $connection->select(
            'select set_config(\'app.user_id\', ?, false), set_config(\'app.org_ids\', ?, false), '
            .'set_config(\'app.trainer_class_ids\', ?, false), set_config(\'app.is_platform_staff\', ?, false)',
            [$this->userId ?? '', implode(',', $this->organizationIds), '', $this->platformStaff ? 'on' : ''],
        );
    }
}
