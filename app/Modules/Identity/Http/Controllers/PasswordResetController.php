<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Audit\Services\SecurityEventLogger;
use App\Modules\Identity\Models\User;
use App\Support\Security\TokenHasher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Lupa & atur ulang kata sandi (keamanan/02 SEC-AUTH-06, SEC-AUTH-21..23).
 */
final class PasswordResetController
{
    public const GENERIC_SENT = 'Jika email tersebut terdaftar, kami telah mengirimkan tautan untuk mengatur ulang kata sandi.';

    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request, SecurityEventLogger $securityEvents): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email:rfc', 'max:254']]);
        $email = mb_strtolower(trim($data['email']));

        $emailKey = 'pwreset:email:'.TokenHasher::hash($email, 'pwreset-throttle');
        $ipKey = 'pwreset:ip:'.$request->ip();

        if (RateLimiter::tooManyAttempts($emailKey, 3) || RateLimiter::tooManyAttempts($ipKey, 10)) {
            $securityEvents->log('rate_limit_exceeded', 'warning', null, ['scope' => 'password_reset']);

            // Respons tetap generik agar tidak menjadi oracle keberadaan akun.
            return back()->with('status', self::GENERIC_SENT);
        }
        RateLimiter::hit($emailKey, 3600);
        RateLimiter::hit($ipKey, 3600);

        $status = Password::sendResetLink(['email' => $email]);
        $securityEvents->log('authn_password_reset_requested', 'info', null, ['broker_status' => $status]);

        return back()->with('status', self::GENERIC_SENT);
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function update(Request $request, AuditLogger $audit, SecurityEventLogger $securityEvents): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:128'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            ['email' => User::normalizeEmail($data['email']), 'password' => $data['password'], 'password_confirmation' => $request->input('password_confirmation'), 'token' => $data['token']],
            function (User $user, string $password) use ($audit, $securityEvents): void {
                $minimum = (int) config($user->requiresMfa() ? 'security.password.min_privileged' : 'security.password.min_participant');
                if (mb_strlen($password) < $minimum) {
                    throw ValidationException::withMessages(['password' => "Kata sandi minimal {$minimum} karakter untuk peran Anda."]);
                }

                DB::transaction(function () use ($user, $password, $audit): void {
                    $user->forceFill([
                        'password' => $password,
                        'password_changed_at' => now(),
                        'remember_token' => Str::random(60),
                        'session_version' => $user->session_version + 1, // cabut semua sesi (SEC-AUTH-19)
                    ])->save();
                    $audit->record('user.password_reset', $user, 'user', $user->id);
                });
                $securityEvents->log('authn_password_changed', 'info', $user->id, ['via' => 'reset']);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'Tautan atur ulang tidak valid atau sudah kedaluwarsa.']);
        }

        // Tidak ada login otomatis setelah reset (SEC-AUTH-23).
        return redirect()->route('login')->with('status', 'Kata sandi berhasil diperbarui. Silakan masuk.');
    }
}
