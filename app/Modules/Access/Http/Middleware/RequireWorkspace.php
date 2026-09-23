<?php

declare(strict_types=1);

namespace App\Modules\Access\Http\Middleware;

use App\Modules\Access\RoleCode;
use App\Modules\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi area (peserta/trainer/organisasi/admin) ke peran yang sesuai.
 * Ditolak sebagai 404 agar keberadaan area tidak terungkap (keamanan/03 SEC-AUTHZ-03).
 */
final class RequireWorkspace
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $workspace): Response
    {
        $user = $request->user();

        $allowed = $user instanceof User && collect($user->roleCodes())
            ->contains(fn (RoleCode $role): bool => $role->workspace() === $workspace);

        abort_unless($allowed, 404);

        return $next($request);
    }
}
