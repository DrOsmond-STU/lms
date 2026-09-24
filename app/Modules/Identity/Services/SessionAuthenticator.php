<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Audit\Services\SecurityEventLogger;
use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;

/**
 * Menyelesaikan login & mengelola status sesi terautentikasi (keamanan/02 §5).
 */
final class SessionAuthenticator
{
    public const KEY_VERSION = 'auth.session_version';

    public const KEY_LOGIN_AT = 'auth.login_at';

    public const KEY_LAST_ACTIVITY = 'auth.last_activity';

    public const KEY_MFA_VERIFIED_AT = 'auth.mfa_verified_at';

    public function __construct(
        private readonly AuthFactory $auth,
        private readonly SecurityEventLogger $securityEvents,
        private readonly DeviceSessions $devices,
    ) {}

    public function complete(Request $request, User $user, bool $mfaVerified): void
    {
        $request->session()->forget(['login.pending_user_id', 'login.pending_at', 'login.mfa_attempts']);

        // Tidak ada "ingat saya" untuk rilis ini (SEC-AUTH-28).
        $this->guard()->login($user, false);

        // Cegah session fixation (SEC-AUTH-17).
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();

        $now = now()->getTimestamp();
        $request->session()->put([
            self::KEY_VERSION => $user->session_version,
            self::KEY_LOGIN_AT => $now,
            self::KEY_LAST_ACTIVITY => $now,
            self::KEY_MFA_VERIFIED_AT => $mfaVerified ? $now : null,
        ]);

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();
        $this->devices->start($request, $user);

        $this->securityEvents->log('authn_login_success', 'info', $user->id, ['mfa' => $mfaVerified]);
    }

    public function markMfaVerified(Request $request): void
    {
        $request->session()->regenerate(true);
        $request->session()->put(self::KEY_MFA_VERIFIED_AT, now()->getTimestamp());
    }

    public function logout(Request $request, string $reason = 'user_logout'): void
    {
        $userId = $this->guard()->id();
        if ($request->hasSession()) {
            $this->devices->end($request, $reason);
        }
        $this->guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($userId !== null) {
            $this->securityEvents->log('authn_logout', 'info', (string) $userId, ['reason' => $reason]);
        }
    }

    private function guard(): StatefulGuard
    {
        $guard = $this->auth->guard('web');
        assert($guard instanceof StatefulGuard);

        return $guard;
    }
}
