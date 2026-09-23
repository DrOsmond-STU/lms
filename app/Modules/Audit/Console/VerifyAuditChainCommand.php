<?php

declare(strict_types=1);

namespace App\Modules\Audit\Console;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Audit\Services\SecurityEventLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Verifikasi harian rantai hash audit (keamanan/11 SEC-LOG-13, aturan deteksi DET-13).
 */
final class VerifyAuditChainCommand extends Command
{
    protected $signature = 'stu:audit-verify';

    protected $description = 'Memverifikasi integritas rantai hash jejak audit';

    public function handle(SecurityEventLogger $securityEvents): int
    {
        $previous = AuditLogger::GENESIS_HASH;
        $checked = 0;
        $broken = null;

        DB::table('audit_logs')->orderBy('seq')->chunk(1000, function ($rows) use (&$previous, &$checked, &$broken): bool {
            foreach ($rows as $row) {
                $entry = (array) $row;
                $entry['changes'] = $entry['changes'] === null ? null : json_decode((string) $entry['changes'], true, 512, JSON_THROW_ON_ERROR);
                $entry['occurred_at'] = Carbon::parse((string) $entry['occurred_at'])->utc()->format('Y-m-d\TH:i:s.u\Z');

                if ($entry['prev_hash'] !== $previous || AuditLogger::computeHash($previous, $entry) !== $entry['hash']) {
                    $broken = (int) $entry['seq'];

                    return false;
                }

                $previous = (string) $entry['hash'];
                $checked++;
            }

            return true;
        });

        if ($broken !== null) {
            $securityEvents->log('audit_chain_broken', 'critical', null, ['seq' => $broken, 'checked' => $checked]);
            $this->error("Rantai audit PUTUS pada seq {$broken} (setelah {$checked} entri valid).");

            return self::FAILURE;
        }

        $this->info("Rantai audit valid: {$checked} entri.");

        return self::SUCCESS;
    }
}
