<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Notification\Services\Notifier;
use App\Support\Privacy\Mask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sesi & perangkat (FR-AUTH-009/010, keamanan/02 SEC-AUTH-12): setiap login tercatat di
 * `user_sessions`; ID baris disimpan di sesi sehingga pencabutan berlaku untuk driver sesi
 * apa pun (file/Redis). Login dari perangkat/jaringan baru memicu notifikasi keamanan.
 */
final class DeviceSessions
{
    public const SESSION_KEY = 'auth.user_session_id';

    private const TOUCH_INTERVAL_SECONDS = 300;

    public function __construct(private readonly Notifier $notifier) {}

    public function start(Request $request, User $user): void
    {
        $agent = Str::limit((string) $request->userAgent(), 500, '');
        $label = self::deviceLabel($agent);
        $network = self::network((string) $request->ip());

        $known = DB::table('user_sessions')->where('user_id', $user->id)->where('device_label', $label)
            ->where('created_at', '>', now()->subDays(90))->get(['ip'])
            ->contains(fn (object $row): bool => self::network((string) $row->ip) === $network);
        $hasHistory = DB::table('user_sessions')->where('user_id', $user->id)->exists();

        $id = (string) Str::uuid7();
        DB::table('user_sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'session_hash' => hash('sha256', $id.'|'.config('app.key')),
            'ip' => $request->ip(),
            'user_agent' => $agent,
            'device_label' => $label,
            'created_at' => now(),
            'last_activity_at' => now(),
        ]);
        $request->session()->put(self::SESSION_KEY, $id);

        if ($hasHistory && ! $known) {
            $this->notifier->send($user, 'security', 'Login dari perangkat baru',
                'Akun Anda baru saja masuk dari '.$label.' ('.self::maskedIp((string) $request->ip()).') pada '.now()->timezone(display_tz())->format('d M Y H:i').' '.tz_label().'. Bukan Anda? Segera ubah kata sandi dan keluarkan sesi lain.',
                '/akun/keamanan/sesi', email: true);
        }
    }

    /** Mengembalikan false bila sesi tercatat sudah dicabut (harus logout). */
    public function validate(Request $request, User $user): bool
    {
        $id = $request->session()->get(self::SESSION_KEY);
        if (! is_string($id)) {
            return true; // sesi lama sebelum fitur ini — tetap dibatasi session_version & timeout
        }

        $row = DB::table('user_sessions')->where('id', $id)->where('user_id', $user->id)->first(['revoked_at', 'last_activity_at']);
        if ($row === null || $row->revoked_at !== null) {
            return false;
        }
        if (now()->diffInSeconds($row->last_activity_at, true) > self::TOUCH_INTERVAL_SECONDS) {
            DB::table('user_sessions')->where('id', $id)->update(['last_activity_at' => now(), 'ip' => $request->ip()]);
        }

        return true;
    }

    public function end(Request $request, string $reason): void
    {
        $id = $request->session()->get(self::SESSION_KEY);
        if (is_string($id)) {
            DB::table('user_sessions')->where('id', $id)->whereNull('revoked_at')->update(['revoked_at' => now(), 'revoke_reason' => $reason]);
        }
    }

    public function revoke(User $user, string $sessionId, string $reason): bool
    {
        return DB::table('user_sessions')->where('user_id', $user->id)->where('id', $sessionId)->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'revoke_reason' => $reason]) === 1;
    }

    public function revokeOthers(User $user, ?string $currentId, string $reason): int
    {
        return DB::table('user_sessions')->where('user_id', $user->id)->whereNull('revoked_at')
            ->when($currentId !== null, fn ($query) => $query->where('id', '<>', $currentId))
            ->update(['revoked_at' => now(), 'revoke_reason' => $reason]);
    }

    public static function deviceLabel(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Peramban lain',
        };
        $os = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS X') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'OS lain',
        };

        return $browser.' di '.$os;
    }

    public static function maskedIp(string $ip): string
    {
        return Mask::ip($ip);
    }

    private static function network(string $ip): string
    {
        return str_contains($ip, ':') ? implode(':', array_slice(explode(':', $ip), 0, 4)) : (preg_replace('/\.\d+$/', '', $ip) ?? $ip);
    }
}
