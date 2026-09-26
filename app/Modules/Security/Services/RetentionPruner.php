<?php

declare(strict_types=1);

namespace App\Modules\Security\Services;

use Illuminate\Support\Facades\DB;

/**
 * Retensi data (minimisasi, UU PDP): menghapus data operasional yang melewati masa simpan
 * yang diatur di Pengaturan → Keamanan. Jejak audit & sertifikat tidak disentuh; event keamanan
 * dipangkas lewat koneksi migrator karena tabelnya append-only untuk peran aplikasi.
 */
final class RetentionPruner
{
    /** @return array<string, int> jumlah baris terhapus per kategori */
    public function run(): array
    {
        $deleted = [];
        $deleted['notifikasi'] = DB::table('notifications')->where('created_at', '<', now()->subDays((int) setting('retention.notifications_days')))->delete();
        $deleted['sesi'] = DB::table('user_sessions')->where(fn ($q) => $q->whereNotNull('revoked_at')->orWhere('last_activity_at', '<', now()->subDays(30)))
            ->where('created_at', '<', now()->subDays((int) setting('retention.sessions_days')))->delete();
        $aiDays = (int) setting('retention.ai_days');
        $deleted['percakapan_ai'] = DB::table('ai_conversations')->where('updated_at', '<', now()->subDays($aiDays))->delete();
        $deleted['pemakaian_ai'] = DB::table('ai_usages')->where('created_at', '<', now()->subDays($aiDays))->delete();
        $outboundDays = (int) setting('retention.outbound_days');
        $deleted['pesan_keluar'] = DB::table('outbound_messages')->where('created_at', '<', now()->subDays($outboundDays))->delete();
        $deleted['pengingat'] = DB::table('notification_reminders')->where('sent_at', '<', now()->subDays(90))->delete();
        $deleted['event_keamanan'] = DB::connection('pgsql_migrator')->table('security_events')->where('occurred_at', '<', now()->subDays((int) setting('retention.security_events_days')))->delete();

        return $deleted;
    }
}
