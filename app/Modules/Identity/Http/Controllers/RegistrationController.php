<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Audit\Services\SecurityEventLogger;
use App\Modules\Identity\Services\RegistrationService;
use App\Modules\Identity\Services\SessionAuthenticator;
use App\Support\Privacy\Mask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Registrasi mandiri peserta + verifikasi OTP email (docs/08 PUB-03, PUB-04).
 */
final class RegistrationController
{
    public const SENT_MESSAGE = 'Jika data valid, kode verifikasi 6 digit telah dikirim ke email Anda. Periksa juga folder spam.';

    public const INVALID_CODE = 'Kode tidak valid atau sudah kedaluwarsa.';

    private const SESSION_EMAIL = 'registration.email';

    private const SESSION_EXPIRES = 'registration.expires_at';

    public function __construct(private readonly RegistrationService $registration) {}

    public function create(): View
    {
        self::ensureEnabled();

        return view('auth.register');
    }

    public function store(Request $request, SecurityEventLogger $securityEvents): RedirectResponse
    {
        self::ensureEnabled();

        $ipKey = 'register:ip:'.$request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, (int) config('security.registration.per_ip_per_hour'))) {
            $securityEvents->log('rate_limit_exceeded', 'warning', null, ['scope' => 'registration']);

            throw ValidationException::withMessages(['email' => 'Terlalu banyak percobaan pendaftaran. Coba lagi nanti.'])->status(429);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120', "regex:/^[\\pL\\pM\\s.,'-]+$/u"],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s().]{8,20}$/'],
            'organization_code' => ['nullable', 'string', 'regex:/^[A-Za-z]{2,8}$/'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
            'accept_terms' => ['accepted'],
            'accept_privacy' => ['accepted'],
        ], [
            'name.regex' => 'Nama hanya boleh berisi huruf, spasi, titik, koma, apostrof, dan tanda hubung.',
            'phone.regex' => 'Format nomor HP tidak valid.',
            'organization_code.regex' => 'Kode organisasi terdiri dari 2–8 huruf.',
            'accept_terms.accepted' => 'Anda perlu menyetujui Syarat & Ketentuan.',
            'accept_privacy.accepted' => 'Anda perlu menyetujui Kebijakan Privasi.',
        ]);

        RateLimiter::hit($ipKey, 3600);
        $this->registration->register($data, $request);

        $request->session()->regenerate(true);
        $request->session()->put([
            self::SESSION_EMAIL => $data['email'],
            self::SESSION_EXPIRES => now()->addMinutes(30)->getTimestamp(),
        ]);

        return redirect()->route('register.verify')->with('status', self::SENT_MESSAGE);
    }

    public function showVerify(Request $request): View|RedirectResponse
    {
        self::ensureEnabled();
        $email = $this->pendingEmail($request);
        if ($email === null) {
            return redirect()->route('register');
        }

        return view('auth.register-verify', ['maskedEmail' => Mask::email(mb_strtolower($email))]);
    }

    public function verify(Request $request, SessionAuthenticator $authenticator): RedirectResponse
    {
        self::ensureEnabled();
        $email = $this->pendingEmail($request);
        if ($email === null) {
            return redirect()->route('register');
        }

        $data = $request->validate(['code' => ['required', 'string', 'max:10']]);
        $user = $this->registration->verify($email, preg_replace('/\D/', '', $data['code']) ?? '');
        if ($user === null) {
            throw ValidationException::withMessages(['code' => self::INVALID_CODE]);
        }

        $request->session()->forget([self::SESSION_EMAIL, self::SESSION_EXPIRES]);
        $authenticator->complete($request, $user, mfaVerified: false);

        $message = 'Selamat datang! Akun Anda telah aktif.';
        if ($this->registration->hasPendingMembership($user)) {
            $message .= ' Keanggotaan organisasi Anda menunggu persetujuan Admin Organisasi.';
        }

        // Kembali ke halaman yang dituju sebelum mendaftar (mis. "Ikut Pelatihan" di beranda).
        return redirect()->intended(route('participant.dashboard'))->with('status', $message);
    }

    public function resend(Request $request): RedirectResponse
    {
        self::ensureEnabled();
        $email = $this->pendingEmail($request);
        if ($email === null) {
            return redirect()->route('register');
        }

        $this->registration->resend($email);

        return back()->with('status', 'Jika pendaftaran Anda masih menunggu verifikasi, kode baru telah dikirim. Kode lama tidak berlaku lagi.');
    }

    private function pendingEmail(Request $request): ?string
    {
        $email = $request->session()->get(self::SESSION_EMAIL);
        $expires = (int) $request->session()->get(self::SESSION_EXPIRES, 0);

        return is_string($email) && $expires > now()->getTimestamp() ? $email : null;
    }

    private static function ensureEnabled(): void
    {
        abort_unless((bool) config('security.registration.enabled'), 404);
    }
}
