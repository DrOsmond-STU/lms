<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Audit\Services\SecurityEventLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\MfaService;
use App\Modules\Identity\Services\SessionAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Langkah kedua login (keamanan/02 SEC-AUTH-13, SEC-AUTH-14, SEC-AUTH-36).
 */
final class MfaChallengeController
{
    public function show(Request $request): View|RedirectResponse
    {
        return $this->pendingUser($request) === null
            ? redirect()->route('login')
            : view('auth.mfa-challenge');
    }

    public function store(
        Request $request,
        MfaService $mfa,
        SessionAuthenticator $authenticator,
        SecurityEventLogger $securityEvents,
    ): RedirectResponse {
        $user = $this->pendingUser($request);
        if ($user === null) {
            return redirect()->route('login')->with('status', 'Sesi verifikasi berakhir. Silakan masuk kembali.');
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:16', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'max:16'],
        ]);

        $valid = isset($data['recovery_code']) && $data['recovery_code'] !== ''
            ? $mfa->useRecoveryCode($user, $data['recovery_code'])
            : $mfa->verifyTotp($user, (string) ($data['code'] ?? ''));

        if (! $valid) {
            $attempts = (int) $request->session()->increment('login.mfa_attempts');
            $securityEvents->log('authn_mfa_fail', 'warning', $user->id, ['attempt' => $attempts]);

            if ($attempts >= (int) config('security.mfa.max_attempts')) {
                $request->session()->forget(['login.pending_user_id', 'login.pending_at', 'login.mfa_attempts']);
                $securityEvents->log('authn_mfa_lockout', 'high', $user->id);

                return redirect()->route('login')->withErrors(['email' => 'Terlalu banyak percobaan kode. Silakan masuk kembali.']);
            }

            throw ValidationException::withMessages(['code' => 'Kode autentikasi tidak valid.']);
        }

        $authenticator->complete($request, $user, mfaVerified: true);

        return redirect()->intended(route('dashboard'));
    }

    private function pendingUser(Request $request): ?User
    {
        $userId = $request->session()->get('login.pending_user_id');
        $startedAt = (int) $request->session()->get('login.pending_at', 0);

        if (! is_string($userId) || now()->getTimestamp() - $startedAt > (int) config('security.mfa.pending_ttl_seconds')) {
            return null;
        }

        $user = User::query()->find($userId);

        return $user instanceof User && $user->isActive() ? $user : null;
    }
}
