<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\MfaService;
use App\Modules\Identity\Services\SessionAuthenticator;
use App\Support\Security\Totp;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Pendaftaran TOTP (keamanan/02 SEC-AUTH-13): butuh konfirmasi kata sandi & kode pertama.
 * QR dibuat di server sebagai SVG inline — tidak ada layanan QR pihak ketiga.
 */
final class MfaSetupController
{
    private const SESSION_SECRET = 'mfa.setup_secret';

    public function show(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $secret = $request->session()->get(self::SESSION_SECRET);
        if (! is_string($secret)) {
            $secret = Totp::generateSecret();
            $request->session()->put(self::SESSION_SECRET, $secret);
        }

        $uri = Totp::provisioningUri($secret, $user->email, (string) config('security.mfa.issuer'));
        $qrSvg = (new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd)))->writeString($uri);

        return view('auth.mfa-setup', [
            'qrDataUri' => 'data:image/svg+xml;base64,'.base64_encode($qrSvg),
            'secret' => trim(chunk_split($secret, 4, ' ')),
            'alreadyEnabled' => $user->hasConfirmedMfa(),
        ]);
    }

    public function store(Request $request, MfaService $mfa, Hasher $hasher, SessionAuthenticator $authenticator): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'password' => ['required', 'string', 'max:128'],
            'code' => ['required', 'digits:6'],
        ], ['code.digits' => 'Kode harus 6 digit angka.']);

        if (! $hasher->check($data['password'], (string) $user->password)) {
            throw ValidationException::withMessages(['password' => 'Kata sandi tidak sesuai.']);
        }

        $secret = $request->session()->get(self::SESSION_SECRET);
        if (! is_string($secret)) {
            return redirect()->route('mfa.setup');
        }

        $codes = $mfa->confirmTotp($user, $secret, $data['code']);
        if ($codes === null) {
            throw ValidationException::withMessages(['code' => 'Kode autentikasi tidak valid. Pastikan jam perangkat Anda tepat.']);
        }

        $request->session()->forget(self::SESSION_SECRET);
        $authenticator->markMfaVerified($request);
        $request->session()->flash('mfa.recovery_codes', $codes);

        return redirect()->route('mfa.recovery-codes');
    }

    public function recoveryCodes(Request $request): View|RedirectResponse
    {
        $codes = $request->session()->get('mfa.recovery_codes');

        return is_array($codes)
            ? view('auth.mfa-recovery-codes', ['codes' => $codes])
            : redirect()->route('dashboard');
    }
}
