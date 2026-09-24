<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console;

use App\Modules\Audit\Services\SecurityEventLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Minimisasi data (keamanan/12): menghapus registrasi mandiri yang tidak pernah
 * diverifikasi dan token sekali pakai yang sudah lama kedaluwarsa. Akun undangan admin
 * (memiliki peran) tidak disentuh.
 */
final class PruneUnverifiedAccountsCommand extends Command
{
    protected $signature = 'stu:prune-unverified';

    protected $description = 'Hapus registrasi yang tidak diverifikasi & token sekali pakai kedaluwarsa';

    public function handle(SecurityEventLogger $securityEvents): int
    {
        $days = (int) config('security.registration.prune_unverified_after_days');

        $users = DB::table('users')
            ->where('status', 'pending_verification')
            ->whereNull('email_verified_at')
            ->where('created_at', '<', now()->subDays($days))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('role_user')->whereColumn('role_user.user_id', 'users.id'))
            ->delete();

        $tokens = DB::table('one_time_tokens')
            ->where(fn ($query) => $query->where('expires_at', '<', now()->subDays(7))->orWhere('consumed_at', '<', now()->subDays(7)))
            ->delete();

        $securityEvents->log('data_pruned', 'info', null, ['unverified_users' => $users, 'one_time_tokens' => $tokens]);
        $this->info("Registrasi tidak terverifikasi dihapus: {$users}. Token kedaluwarsa dihapus: {$tokens}.");

        return self::SUCCESS;
    }
}
