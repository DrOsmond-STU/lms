<?php

declare(strict_types=1);

namespace App\Support\Tenancy\Middleware;

use App\Modules\Identity\Models\User;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memasang konteks tenant untuk setiap request web (keamanan/03 SEC-AUTHZ-10..11).
 * Tamu mendapat konteks kosong sehingga tabel ber-tenant tidak mengembalikan baris.
 */
final class ApplyTenantContext
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $user instanceof User ? $this->context->applyFor($user) : $this->context->clear();

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->context->clear();
    }
}
