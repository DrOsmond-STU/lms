<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\MfaService;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Re-autentikasi sebelum aksi sensitif (keamanan/02 SEC-AUTH-25). Dipakai lewat
 * middleware `password.confirm` bawaan Laravel (batas: auth.password_timeout).
 */
final class ConfirmAccessController
{
    public function show(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('auth.confirm-access', ['needsCode' => $user->hasConfirmedMfa()]);
    }

    public function store(Request $request, Hasher $hasher, MfaService $mfa): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'password' => ['required', 'string', 'max:128'],
            'code' => [$user->hasConfirmedMfa() ? 'required' : 'nullable', 'string', 'max:16'],
        ]);

        if (! $hasher->check($data['password'], (string) $user->password)
            || ($user->hasConfirmedMfa() && ! $mfa->verifyTotp($user, (string) ($data['code'] ?? '')))) {
            throw ValidationException::withMessages(['password' => 'Konfirmasi gagal. Periksa kata sandi dan kode Anda.']);
        }

        $request->session()->regenerate(true);
        $request->session()->put('auth.password_confirmed_at', now()->getTimestamp());

        return redirect()->intended(route('dashboard'));
    }
}
