<?php

declare(strict_types=1);

namespace App\Support\Security\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan & Content Security Policy berbasis nonce (keamanan/13 SEC-INFRA-12,
 * keamanan/04 SEC-INPUT-04).
 */
final class SecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(18));
        Vite::useCspNonce($nonce);
        $request->attributes->set('csp_nonce', $nonce);

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('Content-Security-Policy', $this->contentSecurityPolicy($nonce));
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', $request->routeIs('password.reset', 'password.request', 'invitation.*')
            ? 'no-referrer'
            : 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $headers->remove('X-Powered-By');

        // Lingkungan non-produksi tidak boleh diindeks mesin pencari (SEC-INFRA-34).
        if (! app()->isProduction()) {
            $headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        if ($request->isSecure() && ! app()->isLocal()) {
            $headers->set('Strict-Transport-Security', (string) config('security.headers.hsts'));
        }

        // Halaman HTML dinamis (memuat token CSRF/nonce/data pengguna) tidak boleh di-cache
        // proxy/CDN (SEC-AUTHZ-12). `no-transform` mencegah optimizer proxy hosting
        // (mis. PageSpeed) menulis ulang HTML sehingga nonce CSP & SRI tetap utuh.
        $contentType = (string) $headers->get('Content-Type', '');
        if ($request->user() !== null || str_starts_with($contentType, 'text/html')
            || ($request->hasSession() && $request->session()->has('login.pending_user_id'))) {
            $headers->set('Cache-Control', 'no-store, no-transform, private');
        }

        return $response;
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        $scriptSrc = ["'self'", "'nonce-{$nonce}'", "'strict-dynamic'"];
        $styleSrc = ["'self'", "'nonce-{$nonce}'"];
        $connectSrc = ["'self'"];

        // Server dev Vite (HMR) hanya di lingkungan lokal.
        if (app()->isLocal() && Vite::isRunningHot()) {
            $hot = rtrim((string) file_get_contents(public_path('hot')));
            $scriptSrc[] = $hot;
            $styleSrc[] = $hot;
            $connectSrc[] = $hot;
            $connectSrc[] = preg_replace('#^http#', 'ws', $hot);
        }

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => $scriptSrc,
            'style-src' => $styleSrc,
            'img-src' => ["'self'", 'data:'],
            'font-src' => ["'self'"],
            'connect-src' => $connectSrc,
            'media-src' => ["'self'"],
            'frame-src' => ["'none'"],
            'frame-ancestors' => ["'none'"],
            'form-action' => ["'self'"],
            'base-uri' => ["'none'"],
            'object-src' => ["'none'"],
        ];

        $policy = collect($directives)
            ->map(fn (array $sources, string $name): string => $name.' '.implode(' ', $sources))
            ->implode('; ');

        if (! app()->isLocal()) {
            $policy .= '; upgrade-insecure-requests';
        }

        if ($reportUri = config('security.headers.csp_report_uri')) {
            $policy .= '; report-uri '.$reportUri;
        }

        return $policy;
    }
}
