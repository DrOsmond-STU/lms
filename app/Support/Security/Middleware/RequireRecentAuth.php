<?php

declare(strict_types=1);

namespace App\Support\Security\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Re-autentikasi sebelum aksi sensitif bila konfirmasi terakhir lebih lama dari
 * auth.password_timeout (FR-AUTH-014, keamanan/02 SEC-AUTH-25).
 *
 * Berbeda dengan `password.confirm` bawaan, untuk request non-GET pengguna diarahkan
 * kembali ke halaman asal (URL sebelumnya dari sesi, bukan header Referer) setelah
 * konfirmasi, lalu mengulangi aksinya — aksi POST tidak pernah diputar ulang otomatis.
 */
final class RequireRecentAuth
{
    public const SESSION_KEY = 'auth.password_confirmed_at';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $confirmedAt = (int) $request->session()->get(self::SESSION_KEY, 0);
        if (now()->getTimestamp() - $confirmedAt <= (int) config('auth.password_timeout', 900)) {
            return $next($request);
        }

        $return = $request->isMethod('GET') ? $request->fullUrl() : $request->session()->previousUrl();
        $base = rtrim((string) config('app.url'), '/').'/';
        if (! is_string($return) || ! str_starts_with($return, $base)) {
            $return = route('dashboard');
        }
        $request->session()->put('url.intended', $return);

        return redirect()->route('password.confirm')
            ->with('status', 'Konfirmasi identitas Anda, lalu ulangi tindakan tersebut.');
    }
}
