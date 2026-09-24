<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Audit\Services\SecurityEventLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\PasswordChangedNotification;
use App\Modules\Identity\Services\DeviceSessions;
use App\Modules\Identity\Services\SessionAuthenticator;
use App\Support\Privacy\Mask;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Keamanan akun (docs/08 BARU-06): status MFA, riwayat masuk, ubah kata sandi
 * (FR-AUTH-008 — wajib kata sandi lama, mencabut semua sesi lain).
 */
final class AccountSecurityController
{
    public function show(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $logins = DB::table('security_events')
            ->where('user_id', $user->id)
            ->whereIn('type', ['authn_login_success', 'authn_login_fail'])
            ->orderByDesc('occurred_at')
            ->limit(8)
            ->get(['type', 'occurred_at', 'ip', 'user_agent'])
            ->map(fn (object $row): array => [
                'success' => $row->type === 'authn_login_success',
                'at' => Carbon::parse($row->occurred_at)->timezone('Asia/Jakarta'),
                'ip' => Mask::ip($row->ip),
                'agent' => Str::limit((string) $row->user_agent, 80),
            ]);

        return view('account.security', [
            'user' => $user,
            'workspace' => $user->defaultWorkspace() ?? 'participant',
            'hasMfa' => $user->hasConfirmedMfa(),
            'logins' => $logins,
            'minPassword' => (int) config($user->requiresMfa() ? 'security.password.min_privileged' : 'security.password.min_participant'),
        ]);
    }

    public function updatePassword(Request $request, Hasher $hasher, AuditLogger $audit, SecurityEventLogger $securityEvents, DeviceSessions $devices): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $minimum = (int) config($user->requiresMfa() ? 'security.password.min_privileged' : 'security.password.min_participant');

        $data = $request->validate([
            'current_password' => ['required', 'string', 'max:128'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', PasswordRule::defaults(), 'min:'.$minimum],
        ], [
            'password.different' => 'Kata sandi baru harus berbeda dari kata sandi saat ini.',
        ]);

        if (! $hasher->check($data['current_password'], (string) $user->password)) {
            $securityEvents->log('authn_password_change_fail', 'warning', $user->id, ['reason' => 'bad_current_password']);

            throw ValidationException::withMessages(['current_password' => 'Kata sandi saat ini salah.']);
        }

        DB::transaction(function () use ($user, $data, $audit): void {
            $user->forceFill([
                'password' => $data['password'],
                'password_changed_at' => now(),
                'remember_token' => Str::random(60),
                'session_version' => $user->session_version + 1, // cabut sesi lain (SEC-AUTH-19)
            ])->save();
            $audit->record('user.password_changed', $user, 'user', $user->id);
        });

        // Sesi ini tetap berlaku: versinya diperbarui & ID sesi diregenerasi.
        $request->session()->regenerate(true);
        $request->session()->put(SessionAuthenticator::KEY_VERSION, $user->session_version);
        $current = $request->session()->get(DeviceSessions::SESSION_KEY);
        $devices->revokeOthers($user, is_string($current) ? $current : null, 'password_changed');

        $securityEvents->log('authn_password_changed', 'info', $user->id, ['via' => 'account']);
        $user->notify(new PasswordChangedNotification);

        return redirect()->route('account.security')->with('status', 'Kata sandi berhasil diubah. Sesi di perangkat lain telah dikeluarkan.');
    }
}
