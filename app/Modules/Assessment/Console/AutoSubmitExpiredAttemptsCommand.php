<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Console;

use App\Modules\Assessment\Services\AttemptService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Mengumpulkan otomatis attempt yang melewati batas waktu (FR-ASM-006, SEC-EXAM-05).
 * Dijalankan penjadwal setiap menit dalam konteks sistem.
 */
final class AutoSubmitExpiredAttemptsCommand extends Command
{
    protected $signature = 'stu:exams-auto-submit';

    protected $description = 'Kumpulkan otomatis attempt ujian yang waktunya habis';

    public function handle(AttemptService $attempts, TenantContext $tenant): int
    {
        $count = $tenant->runAsSystem(fn (): int => $attempts->autoSubmitExpired());
        if ($count > 0) {
            $this->info("Attempt dikumpulkan otomatis: {$count}");
        }

        return self::SUCCESS;
    }
}
