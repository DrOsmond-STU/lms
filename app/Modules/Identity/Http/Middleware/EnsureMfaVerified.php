<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\SessionAuthenticator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Peran wajib-MFA hanya boleh mengakses aplikasi setelah MFA terdaftar & diverifikasi
 * pada sesi ini (keamanan/02 SEC-AUTH-10, SEC-AUTH-36). Pengguna yang mengaktifkan MFA
 * secara sukarela juga harus terverifikasi.
 */
final class EnsureMfaVerified
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        $verified = $request->session()->get(SessionAuthenticator::KEY_MFA_VERIFIED_AT) !== null;
        if ($verified) {
            return $next($request);
        }

        if ($user->hasConfirmedMfa()) {
            // Tidak seharusnya terjadi (login MFA selalu memverifikasi). Fail closed.
            app(SessionAuthenticator::class)->logout($request, 'mfa_state_invalid');

            return redirect()->route('login');
        }

        if ($user->requiresMfa()) {
            return redirect()->route('mfa.setup')
                ->with('status', 'Peran Anda mewajibkan autentikasi dua faktor. Aktifkan terlebih dahulu.');
        }

        return $next($request);
    }
}
