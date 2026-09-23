<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Audit\Services\SecurityEventLogger;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Services\LoginService;
use App\Modules\Identity\Services\SessionAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class LoginController
{
    /** Parameter yang tidak boleh memengaruhi login (PROTO-01). */
    private const SUSPICIOUS_PARAMETERS = ['role', 'peran', 'is_admin', 'user_id', 'organization_id'];

    public function show(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, LoginService $login, SecurityEventLogger $securityEvents): RedirectResponse
    {
        $suspicious = array_values(array_intersect(self::SUSPICIOUS_PARAMETERS, array_keys($request->all())));
        if ($suspicious !== []) {
            $securityEvents->log('authz_suspicious_parameter', 'warning', null, ['parameters' => $suspicious, 'route' => 'login']);
        }

        $result = $login->attempt($request, $request->string('email')->toString(), $request->string('password')->toString());

        return $result === 'mfa_challenge'
            ? redirect()->route('mfa.challenge')
            : redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, SessionAuthenticator $authenticator): RedirectResponse
    {
        $authenticator->logout($request);

        return redirect()->route('login')->with('status', 'Anda telah keluar.');
    }
}
