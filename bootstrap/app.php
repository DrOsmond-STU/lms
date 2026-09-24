<?php

declare(strict_types=1);

use App\Modules\Access\Http\Middleware\RequireWorkspace;
use App\Modules\Identity\Http\Middleware\EnsureMfaVerified;
use App\Modules\Identity\Http\Middleware\ValidateSessionState;
use App\Support\Security\Middleware\AssignRequestId;
use App\Support\Security\Middleware\RequireRecentAuth;
use App\Support\Security\Middleware\SecurityHeaders;
use App\Support\Tenancy\Middleware\ApplyTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Modules/Access/Console',
        __DIR__.'/../app/Modules/Audit/Console',
        __DIR__.'/../app/Modules/Identity/Console',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->append(SecurityHeaders::class);

        $middleware->web(append: [
            ValidateSessionState::class,
            ApplyTenantContext::class,
        ]);

        $middleware->alias([
            'mfa' => EnsureMfaVerified::class,
            'workspace' => RequireWorkspace::class,
            'reauth' => RequireRecentAuth::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));

        // Hanya host resmi (cegah host header injection — SEC-AUTH-22).
        $middleware->trustHosts(at: fn () => array_filter([parse_url((string) config('app.url'), PHP_URL_HOST)]), subdomains: false);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'code', 'recovery_code']);
    })->create();
