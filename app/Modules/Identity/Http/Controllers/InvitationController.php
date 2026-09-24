<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Audit\Services\SecurityEventLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\ConsentRecorder;
use App\Modules\Identity\Services\OneTimeTokens;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Menerima undangan & mengatur kata sandi pertama (docs/08 BARU-08, FR-USER-003).
 * Token sekali pakai 72 jam; pesan token tidak valid generik. Tanpa login otomatis:
 * pengguna masuk sendiri lalu (untuk peran wajib) mendaftarkan MFA.
 */
final class InvitationController
{
    public const INVALID = 'Tautan undangan tidak valid atau sudah kedaluwarsa. Minta administrator mengirim ulang undangan.';

    public function __construct(private readonly OneTimeTokens $tokens) {}

    public function show(string $token): View
    {
        $invitation = $this->tokens->findLink(OneTimeTokens::PURPOSE_INVITATION, $token);
        $user = $invitation?->user;

        return view('auth.invitation', [
            'token' => $token,
            'user' => $user instanceof User && $user->status === 'pending_verification' ? $user : null,
            'minPassword' => $user instanceof User ? self::minimumFor($user) : 0,
        ]);
    }

    public function accept(Request $request, string $token, ConsentRecorder $consents, AuditLogger $audit, SecurityEventLogger $securityEvents): RedirectResponse
    {
        $invitation = $this->tokens->findLink(OneTimeTokens::PURPOSE_INVITATION, $token);
        $user = $invitation?->user;
        if ($invitation === null || ! $user instanceof User || $user->status !== 'pending_verification') {
            $securityEvents->log('authn_invitation_invalid', 'info');

            return redirect()->route('invitation.show', $token);
        }

        $minimum = self::minimumFor($user);
        $data = $request->validate([
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults(), 'min:'.$minimum],
            'accept_terms' => ['accepted'],
            'accept_privacy' => ['accepted'],
        ], [
            'accept_terms.accepted' => 'Anda perlu menyetujui Syarat & Ketentuan.',
            'accept_privacy.accepted' => 'Anda perlu menyetujui Kebijakan Privasi.',
        ]);

        $accepted = DB::transaction(function () use ($invitation, $user, $data, $request, $consents, $audit): bool {
            if (! $this->tokens->consume($invitation)) {
                return false;
            }

            $user->forceFill([
                'password' => $data['password'],
                'password_changed_at' => now(),
                'status' => 'active',
                'email_verified_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();
            $consents->record($user, 'invitation', $request);
            $audit->record('user.invitation_accepted', $user, 'user', $user->id);

            return true;
        });

        if (! $accepted) {
            throw ValidationException::withMessages(['password' => self::INVALID]);
        }

        $securityEvents->log('authn_invitation_accepted', 'info', $user->id);
        $message = 'Kata sandi tersimpan. Silakan masuk.'.($user->requiresMfa() ? ' Anda akan diminta mengaktifkan autentikasi dua faktor.' : '');

        return redirect()->route('login')->with('status', $message);
    }

    private static function minimumFor(User $user): int
    {
        return (int) config($user->requiresMfa() ? 'security.password.min_privileged' : 'security.password.min_participant');
    }
}
