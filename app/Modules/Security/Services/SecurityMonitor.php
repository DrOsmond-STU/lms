<?php

declare(strict_types=1);

namespace App\Modules\Security\Services;

use App\Modules\Access\RoleCode;
use App\Modules\Audit\Services\SecurityEventLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Services\Notifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pemantauan aktivitas mencurigakan: agregasi event keamanan & audit menjadi peringatan
 * (brute force per IP, akun yang ditarget, sesi dari banyak IP, ekspor massal). Peringatan
 * dicatat sebagai security_events berprioritas tinggi dan Super Admin diberi tahu.
 */
final class SecurityMonitor
{
    public const ALERT_TYPES = ['brute_force_suspected', 'account_targeted', 'session_anomaly', 'mass_export'];

    private const EXPORT_ACTIONS = ['report.exported', 'gradebook.exported', 'audit_log.exported', 'certificate.exported', 'attendance.exported'];

    public function __construct(private readonly SecurityEventLogger $events, private readonly Notifier $notifier) {}

    /** @return array<string, int> jumlah peringatan baru per jenis */
    public function scan(): array
    {
        $loginThreshold = max(3, (int) setting('monitor.failed_login_threshold'));
        $exportThreshold = max(3, (int) setting('monitor.export_threshold'));
        $created = array_fill_keys(self::ALERT_TYPES, 0);

        // 1. Brute force per IP (1 jam terakhir).
        DB::table('security_events')->where('type', 'authn_login_fail')->where('occurred_at', '>=', now()->subHour())->whereNotNull('ip')
            ->selectRaw('ip::text as ip, count(*) as total')->groupBy('ip')->havingRaw('count(*) >= ?', [$loginThreshold])->get()
            ->each(function (object $row) use (&$created): void {
                if ($this->alreadyAlerted('brute_force_suspected', 'ip', (string) $row->ip)) {
                    return;
                }
                $this->events->log('brute_force_suspected', 'high', null, ['ip' => (string) $row->ip, 'failed_logins_1h' => (int) $row->total]);
                $this->notifyAdmins('Dugaan brute force', (int) $row->total.' percobaan login gagal dari IP '.(string) $row->ip.' dalam 1 jam.');
                $created['brute_force_suspected']++;
            });

        // 2. Akun yang ditarget (banyak kegagalan pada satu akun).
        DB::table('security_events')->where('type', 'authn_login_fail')->where('occurred_at', '>=', now()->subHour())->whereNotNull('user_id')
            ->selectRaw('user_id, count(*) as total, count(distinct ip) as ips')->groupBy('user_id')->havingRaw('count(*) >= ?', [max(3, intdiv($loginThreshold, 2))])->get()
            ->each(function (object $row) use (&$created): void {
                $userId = (string) $row->user_id;
                if ($this->alreadyAlerted('account_targeted', 'user_id', $userId)) {
                    return;
                }
                $this->events->log('account_targeted', 'warning', $userId, ['failed_logins_1h' => (int) $row->total, 'distinct_ips' => (int) $row->ips]);
                $user = User::query()->find($userId);
                if ($user instanceof User) {
                    $this->notifier->send($user, 'security', 'Percobaan masuk mencurigakan', 'Ada '.(int) $row->total.' percobaan masuk gagal ke akun Anda dalam 1 jam. Bila bukan Anda, ganti kata sandi dan periksa sesi aktif.', '/akun/keamanan/sesi', email: true);
                }
                $created['account_targeted']++;
            });

        // 3. Sesi dari ≥3 IP berbeda dalam 1 jam (indikasi berbagi akun/pembajakan).
        DB::table('user_sessions')->where('created_at', '>=', now()->subHour())->whereNull('revoked_at')
            ->selectRaw('user_id, count(distinct ip) as ips')->groupBy('user_id')->havingRaw('count(distinct ip) >= 3')->get()
            ->each(function (object $row) use (&$created): void {
                $userId = (string) $row->user_id;
                if ($this->alreadyAlerted('session_anomaly', 'user_id', $userId)) {
                    return;
                }
                $this->events->log('session_anomaly', 'warning', $userId, ['distinct_ips_1h' => (int) $row->ips]);
                $this->notifyAdmins('Anomali sesi', 'Satu akun membuka sesi dari '.(int) $row->ips.' alamat IP berbeda dalam 1 jam.');
                $created['session_anomaly']++;
            });

        // 4. Ekspor massal (24 jam) oleh satu aktor.
        DB::table('audit_logs')->whereIn('action', self::EXPORT_ACTIONS)->where('occurred_at', '>=', now()->subDay())->whereNotNull('actor_id')
            ->selectRaw('actor_id, count(*) as total')->groupBy('actor_id')->havingRaw('count(*) >= ?', [$exportThreshold])->get()
            ->each(function (object $row) use (&$created): void {
                $userId = (string) $row->actor_id;
                if ($this->alreadyAlerted('mass_export', 'user_id', $userId, 24)) {
                    return;
                }
                $this->events->log('mass_export', 'high', $userId, ['exports_24h' => (int) $row->total]);
                $this->notifyAdmins('Ekspor data massal', 'Seorang pengguna melakukan '.(int) $row->total.' ekspor data dalam 24 jam.');
                $created['mass_export']++;
            });

        return $created;
    }

    /** @return array<string, mixed> ringkasan untuk halaman pemantauan */
    public function summary(): array
    {
        $day = now()->subDay();

        return [
            'failedLogins24h' => DB::table('security_events')->where('type', 'authn_login_fail')->where('occurred_at', '>=', $day)->count(),
            'rateLimited24h' => DB::table('security_events')->where('type', 'rate_limit_exceeded')->where('occurred_at', '>=', $day)->count(),
            'alerts7d' => DB::table('security_events')->whereIn('type', self::ALERT_TYPES)->where('occurred_at', '>=', now()->subDays(7))->count(),
            'activeSessions' => DB::table('user_sessions')->whereNull('revoked_at')->where('last_activity_at', '>=', now()->subHours(2))->count(),
            'topIps' => DB::table('security_events')->where('type', 'authn_login_fail')->where('occurred_at', '>=', $day)->whereNotNull('ip')
                ->selectRaw('ip::text as ip, count(*) as total, max(occurred_at) as last_at')->groupBy('ip')->orderByDesc('total')->limit(8)->get(),
            'alerts' => DB::table('security_events')->whereIn('type', self::ALERT_TYPES)->orderByDesc('occurred_at')->limit(20)->get(),
            'byType7d' => DB::table('security_events')->where('occurred_at', '>=', now()->subDays(7))->selectRaw('type, severity, count(*) as total')->groupBy('type', 'severity')->orderByDesc('total')->get(),
        ];
    }

    private function alreadyAlerted(string $type, string $key, string $value, int $hours = 6): bool
    {
        return DB::table('security_events')->where('type', $type)->where('occurred_at', '>=', now()->subHours($hours))
            ->when($key === 'user_id', fn ($q) => $q->where('user_id', $value), fn ($q) => $q->whereRaw("details->>'ip' = ?", [$value]))->exists();
    }

    private function notifyAdmins(string $title, string $body): void
    {
        $this->superAdmins()->each(fn (User $admin) => $this->notifier->send($admin, 'security', $title, $body, '/admin/keamanan/pemantauan', email: true));
    }

    /** @return Collection<int, User> */
    private function superAdmins(): Collection
    {
        $roleId = DB::table('roles')->where('code', RoleCode::SuperAdmin->value)->value('id');

        return User::query()->where('status', 'active')->whereIn('id', DB::table('role_user')->where('role_id', $roleId)->select('user_id'))->get();
    }
}
