<?php

declare(strict_types=1);

namespace App\Support\Security\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memberi setiap request ID korelasi (keamanan/11 SEC-LOG-02).
 *
 * ID dari klien hanya diterima bila berformat ULID yang valid, agar tidak bisa
 * dipakai untuk menyuntikkan teks ke log.
 */
final class AssignRequestId
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->headers->get('X-Request-Id', '');
        $requestId = Str::isUlid($incoming) ? $incoming : (string) Str::ulid();

        $request->attributes->set('request_id', $requestId);
        Context::add('request_id', $requestId);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
