<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Meminta persetujuan ulang bila versi S&K/Kebijakan Privasi berubah (FR-CMS-003,
 * docs/08 BARU-10). Hasil pemeriksaan disimpan di sesi per kombinasi versi.
 */
final class EnsureConsentIsCurrent
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        // Halaman MFA dikecualikan: kode pemulihan yang tampil sekali tidak boleh hilang karena pengalihan.
        if (! $user instanceof User || $request->routeIs('consent.*', 'logout', 'mfa.*')) {
            return $next($request);
        }

        $versions = config('legal.terms_version').'|'.config('legal.privacy_version');
        if ($request->session()->get('consent.checked_versions') === $versions) {
            return $next($request);
        }

        $accepted = DB::table('consents')->where('user_id', $user->id)
            ->where(fn ($query) => $query
                ->where(fn ($q) => $q->where('document', 'terms')->where('version', config('legal.terms_version')))
                ->orWhere(fn ($q) => $q->where('document', 'privacy')->where('version', config('legal.privacy_version'))))
            ->distinct()->count('document');

        if ($accepted < 2) {
            if ($request->isMethod('GET')) {
                $request->session()->put('url.intended', $request->fullUrl());
            }

            return redirect()->route('consent.show');
        }

        $request->session()->put('consent.checked_versions', $versions);

        return $next($request);
    }
}
