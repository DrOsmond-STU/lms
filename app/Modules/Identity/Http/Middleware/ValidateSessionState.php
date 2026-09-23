<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\SessionAuthenticator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menegakkan validitas sesi pada setiap request (keamanan/02 SEC-AUTH-18, SEC-AUTH-19,
 * SEC-AUTH-29): akun harus aktif, versi sesi harus cocok (pencabutan massal), dan batas
 * waktu idle/absolut dipatuhi.
 */
final class ValidateSessionState
{
    public function __construct(private readonly SessionAuthenticator $authenticator) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        $session = $request->session();
        $reason = null;

        if (! $user->isActive()) {
            $reason = 'account_inactive';
        } elseif ((int) $session->get(SessionAuthenticator::KEY_VERSION) !== $user->session_version) {
            $reason = 'session_revoked';
        } else {
            $privileged = $user->requiresMfa() ? 'privileged' : 'participant';
            $now = now()->getTimestamp();
            $idle = (int) config("security.session.idle_minutes.{$privileged}") * 60;
            $absolute = (int) config("security.session.absolute_minutes.{$privileged}") * 60;

            if ($now - (int) $session->get(SessionAuthenticator::KEY_LAST_ACTIVITY, 0) > $idle) {
                $reason = 'idle_timeout';
            } elseif ($now - (int) $session->get(SessionAuthenticator::KEY_LOGIN_AT, 0) > $absolute) {
                $reason = 'absolute_timeout';
            } else {
                $session->put(SessionAuthenticator::KEY_LAST_ACTIVITY, $now);
            }
        }

        if ($reason !== null) {
            $this->authenticator->logout($request, $reason);

            return redirect()->route('login')->with('status', 'Sesi Anda telah berakhir. Silakan masuk kembali.');
        }

        return $next($request);
    }
}
