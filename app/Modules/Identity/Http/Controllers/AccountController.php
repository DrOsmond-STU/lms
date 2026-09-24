<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Access\RoleCode;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\ConsentRecorder;
use App\Modules\Identity\Services\DeviceSessions;
use App\Modules\Identity\Services\RegistrationService;
use App\Support\Privacy\Mask;
use App\Support\Security\TokenHasher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Akun pengguna: profil (FR-USER-005), sesi & perangkat (FR-AUTH-009), privasi & persetujuan
 * opsional (FR-PRV-001), persetujuan ulang saat dokumen hukum berubah (FR-CMS-003).
 */
final class AccountController
{
    public const OPTIONAL_CONSENTS = [
        'marketing' => 'Informasi program & promosi via email',
        'whatsapp' => 'Notifikasi melalui WhatsApp',
        'public_leaderboard' => 'Tampil di papan peringkat publik',
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    public function profile(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('account.profile', [
            'user' => $user,
            'workspace' => $user->defaultWorkspace() ?? 'participant',
            'profile' => DB::table('participant_profiles')->where('user_id', $user->id)->first(),
            'isParticipant' => $user->hasRole(RoleCode::Participant),
            'phone' => $user->getAttribute('phone_encrypted'),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $profile = DB::table('participant_profiles')->where('user_id', $user->id)->first();
        $locked = $profile !== null && $profile->source !== 'self';

        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120', "regex:/^[\\pL\\pM\\s.,'-]+$/u"],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s().]{8,20}$/'],
            'participant_number' => ['nullable', 'string', 'max:40'],
            'study_program' => ['nullable', 'string', 'max:120'],
            'semester' => ['nullable', 'integer', 'between:1,14'],
            'department' => ['nullable', 'string', 'max:120'],
        ]);

        DB::transaction(function () use ($user, $data, $locked): void {
            $changes = [];
            if ($user->name !== trim($data['name'])) {
                $changes['name'] = ['from' => $user->name, 'to' => trim($data['name'])];
                $user->name = trim($data['name']);
            }
            $phone = RegistrationService::normalizePhone($data['phone'] ?? null);
            if (($data['phone'] ?? '') !== '' && $phone === null) {
                throw ValidationException::withMessages(['phone' => 'Format nomor HP tidak valid.']);
            }
            if ($phone !== $user->getAttribute('phone_encrypted')) {
                $index = $phone === null ? null : TokenHasher::hash($phone, 'phone-bidx');
                if ($index !== null && DB::table('users')->where('phone_bidx', $index)->where('id', '<>', $user->id)->exists()) {
                    throw ValidationException::withMessages(['phone' => 'Nomor HP ini tidak dapat dipakai.']);
                }
                $user->forceFill(['phone_encrypted' => $phone, 'phone_bidx' => $index]);
                $changes['phone'] = 'updated';
            }
            $user->save();

            if ($user->hasRole(RoleCode::Participant) && ! $locked) {
                DB::table('participant_profiles')->upsert([[
                    'user_id' => $user->id,
                    'participant_number' => $data['participant_number'] ?? null,
                    'study_program' => $data['study_program'] ?? null,
                    'semester' => $data['semester'] ?? null,
                    'department' => $data['department'] ?? null,
                    'source' => 'self',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]], ['user_id'], ['participant_number', 'study_program', 'semester', 'department', 'updated_at']);
            }
            if ($changes !== []) {
                $this->audit->record('user.profile_updated', $user, 'user', $user->id, $changes);
            }
        });

        return back()->with('status', 'Profil disimpan.');
    }

    public function sessions(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $current = $request->session()->get(DeviceSessions::SESSION_KEY);
        $sessions = DB::table('user_sessions')->where('user_id', $user->id)->whereNull('revoked_at')
            ->where('last_activity_at', '>', now()->subMinutes((int) config('security.session.absolute_minutes.participant')))
            ->orderByDesc('last_activity_at')->get()
            ->map(fn (object $row): array => [
                'id' => $row->id, 'device' => $row->device_label, 'ip' => Mask::ip($row->ip),
                'created' => Carbon::parse($row->created_at)->timezone('Asia/Jakarta'),
                'last' => Carbon::parse($row->last_activity_at)->timezone('Asia/Jakarta'),
                'current' => $row->id === $current,
            ]);

        return view('account.sessions', ['sessions' => $sessions, 'workspace' => $user->defaultWorkspace() ?? 'participant']);
    }

    public function revokeSession(Request $request, string $session, DeviceSessions $devices): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(Str::isUuid($session) && $session !== $request->session()->get(DeviceSessions::SESSION_KEY), 404);
        $devices->revoke($user, $session, 'user_revoked');
        $this->audit->record('user.session_revoked', $user, 'user', $user->id, ['session_id' => $session]);

        return back()->with('status', 'Sesi dikeluarkan.');
    }

    public function revokeOtherSessions(Request $request, DeviceSessions $devices): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $current = $request->session()->get(DeviceSessions::SESSION_KEY);
        $count = $devices->revokeOthers($user, is_string($current) ? $current : null, 'user_revoked_others');
        $this->audit->record('user.sessions_revoked_others', $user, 'user', $user->id, ['count' => $count]);

        return back()->with('status', "{$count} sesi lain dikeluarkan.");
    }

    public function privacy(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('account.privacy', [
            'workspace' => $user->defaultWorkspace() ?? 'participant',
            'optional' => self::OPTIONAL_CONSENTS,
            'granted' => self::grantedOptional($user),
            'history' => DB::table('consents')->where('user_id', $user->id)->orderByDesc('accepted_at')->limit(30)->get(),
        ]);
    }

    public function updatePrivacy(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $wanted = array_intersect(array_keys(self::OPTIONAL_CONSENTS), array_keys(array_filter((array) $request->input('consents', []))));
        $current = self::grantedOptional($user);

        DB::transaction(function () use ($user, $wanted, $current, $request): void {
            foreach (array_keys(self::OPTIONAL_CONSENTS) as $purpose) {
                $want = in_array($purpose, $wanted, true);
                $has = in_array($purpose, $current, true);
                if ($want && ! $has) {
                    DB::table('consents')->insert([
                        'id' => (string) Str::uuid7(), 'user_id' => $user->id, 'document' => $purpose, 'version' => '1',
                        'accepted_at' => now(), 'channel' => 'preferences', 'ip' => $request->ip(),
                        'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                    ]);
                } elseif (! $want && $has) {
                    DB::table('consents')->where('user_id', $user->id)->where('document', $purpose)->whereNull('withdrawn_at')->update(['withdrawn_at' => now()]);
                }
            }
            $this->audit->record('user.consents_updated', $user, 'user', $user->id, ['optional' => array_values($wanted)]);
        });

        return back()->with('status', 'Preferensi privasi disimpan.');
    }

    public function showReconsent(Request $request): View
    {
        return view('account.reconsent');
    }

    public function storeReconsent(Request $request, ConsentRecorder $consents): RedirectResponse
    {
        $request->validate(['accept_terms' => ['accepted'], 'accept_privacy' => ['accepted']], [
            'accept_terms.accepted' => 'Anda perlu menyetujui Syarat & Ketentuan untuk melanjutkan.',
            'accept_privacy.accepted' => 'Anda perlu menyetujui Kebijakan Privasi untuk melanjutkan.',
        ]);
        /** @var User $user */
        $user = $request->user();
        $consents->record($user, 'reconsent', $request);
        $request->session()->forget('consent.checked_versions');

        return redirect()->intended(route('dashboard'));
    }

    /** @return list<string> */
    private static function grantedOptional(User $user): array
    {
        /** @var list<string> */
        return DB::table('consents')->where('user_id', $user->id)->whereIn('document', array_keys(self::OPTIONAL_CONSENTS))
            ->whereNull('withdrawn_at')->distinct()->pluck('document')->all();
    }
}
