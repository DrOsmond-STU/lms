<?php

declare(strict_types=1);

namespace App\Modules\Payment\Console;

use App\Modules\Payment\Services\PaymentService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/** Terjadwal tiap jam: tagihan transfer manual tanpa bukti yang melewati batas waktu. */
final class PaymentsExpireCommand extends Command
{
    protected $signature = 'stu:payments-expire';

    protected $description = 'Kedaluwarsakan tagihan pembayaran manual yang melewati batas waktu tanpa bukti transfer';

    public function handle(PaymentService $payments, TenantContext $tenant): int
    {
        $count = $tenant->runAsSystem(fn (): int => $payments->expire());
        $this->info("Tagihan kedaluwarsa: {$count}.");

        return self::SUCCESS;
    }
}
