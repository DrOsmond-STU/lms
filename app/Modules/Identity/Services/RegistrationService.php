<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Access\RoleCode;
use App\Modules\Access\Services\RoleAssigner;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Audit\Services\SecurityEventLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\AccountAlreadyExistsNotification;
use App\Modules\Identity\Notifications\RegistrationCodeNotification;
use App\Modules\Organization\Models\Organization;
use App\Modules\Referral\Services\ReferralService;
use App\Support\Security\TokenHasher;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Registrasi mandiri peserta dengan verifikasi OTP email (FR-AUTH-001..003, docs/07 §7).
 *
 * Anti-enumerasi (SEC-AUTH-06): email baru, email tertunda, dan email yang sudah aktif
 * menghasilkan respons HTTP yang identik; pemilik akun yang sudah ada menerima email
 * pemberitahuan, bukan kode.
 */
final class RegistrationService
{
    public function __construct(
        private readonly OneTimeTokens $tokens,
        private readonly TenantContext $tenant,
        private readonly Hasher $hasher,
        private readonly ConsentRecorder $consents,
        private readonly RoleAssigner $roles,
        private readonly AuditLogger $audit,
        private readonly SecurityEventLogger $securityEvents,
        private readonly ReferralService $referrals,
    ) {}

    /**
     * @param  array{name: string, email: string, phone?: string|null, organization_code?: string|null, referral_code?: string|null, password: string}  $data
     *
     * @throws ValidationException
     */
    public function register(array $data, Request $request): void
    {
        $email = User::normalizeEmail($data['email']);
        [$organizationId, $membership] = $this->resolveOrganization($data['organization_code'] ?? null, $email);
        $metadata = ['organization_id' => $organizationId, 'membership' => $membership];

        /** @var User|null $existing */
        $existing = User::query()->where('email', $email)->first();

        if ($existing === null) {
            $user = DB::transaction(function () use ($data, $email, $request): User {
                $user = new User(['name' => trim($data['name']), 'email' => $email]);
                $user->forceFill(['password' => $data['password'], 'status' => 'pending_verification']);
                $this->applyPhone($user, $data['phone'] ?? null);
                $user->save();
                $this->consents->record($user, 'registration', $request);

                return $user;
            });

            $cookie = $request->cookie(ReferralService::COOKIE);
            $this->referrals->attribute($user, $data['referral_code'] ?? (is_string($cookie) ? $cookie : null));
            $this->sendCode($user, $metadata);
            $this->securityEvents->log('authn_registration_started', 'info', $user->id);

            return;
        }

        // Samakan beban kerja dengan jalur akun baru (hash Argon2id) agar waktu respons tidak membocorkan keberadaan akun.
        $this->hasher->make($data['password']);

        if ($this->isSelfRegistrationPending($existing)) {
            $this->sendCode($existing, $metadata);
        } elseif (RateLimiter::attempt('register:exists:'.$existing->id, 1, static fn () => true, 3600)) {
            $existing->notify(new AccountAlreadyExistsNotification);
        }

        $this->securityEvents->log('authn_registration_duplicate', 'info', $existing->id);
    }

    /** Kirim ulang kode; respons pemanggil selalu generik. */
    public function resend(string $email): void
    {
        $user = $this->pendingUser($email);
        if ($user === null) {
            return;
        }

        $current = DB::table('one_time_tokens')
            ->where('user_id', $user->id)
            ->where('purpose', OneTimeTokens::PURPOSE_EMAIL_VERIFICATION)
            ->whereNull('consumed_at')
            ->orderByDesc('created_at')
            ->value('metadata');

        /** @var array<string, mixed> $metadata */
        $metadata = is_string($current) ? (array) json_decode($current, true) : [];
        $this->sendCode($user, $metadata);
    }

    /**
     * Memverifikasi OTP & mengaktifkan akun. Null bila kode tidak valid (alasan tidak dibedakan).
     */
    public function verify(string $email, string $code): ?User
    {
        $user = $this->pendingUser($email);
        $token = $user === null ? null : $this->tokens->verifyOtp(
            $user,
            OneTimeTokens::PURPOSE_EMAIL_VERIFICATION,
            $code,
            (int) config('security.registration.otp_max_attempts'),
        );

        if ($user === null || $token === null) {
            $this->securityEvents->log('authn_otp_fail', 'info', $user?->id, ['scope' => 'registration']);

            return null;
        }

        $organizationId = $token->metadata['organization_id'] ?? null;
        $membership = $token->metadata['membership'] ?? null;
        if (is_string($organizationId) && ! Organization::query()->whereKey($organizationId)->where('status', 'active')->exists()) {
            $organizationId = null;
        }

        DB::transaction(function () use ($user, $organizationId, $membership): void {
            $user->forceFill([
                'status' => 'active',
                'email_verified_at' => now(),
                'primary_organization_id' => is_string($organizationId) && $membership === 'active' ? $organizationId : null,
            ])->save();

            $this->tenant->runAsSystem(function () use ($user, $organizationId, $membership): void {
                if (is_string($organizationId)) {
                    DB::table('organization_members')->insertOrIgnore([
                        'id' => (string) Str::uuid7(),
                        'organization_id' => $organizationId,
                        'user_id' => $user->id,
                        'status' => $membership === 'active' ? 'active' : 'pending',
                        'approved_at' => $membership === 'active' ? now() : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $this->roles->assign($user, RoleCode::Participant, $membership === 'active' && is_string($organizationId) ? $organizationId : null, null);
            });

            $this->audit->record('user.registered', $user, 'user', $user->id, [
                'organization_id' => $organizationId,
                'membership' => $organizationId === null ? null : $membership,
            ], organizationId: is_string($organizationId) ? $organizationId : null);
        });

        $this->securityEvents->log('authn_registration_verified', 'info', $user->id);

        return $user->fresh();
    }

    public function hasPendingMembership(User $user): bool
    {
        return $this->tenant->runAsSystem(fn (): bool => DB::table('organization_members')
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists());
    }

    /**
     * Menentukan organisasi yang dipilih & status keanggotaannya (FR-AUTH-003): aktif hanya
     * bila domain email cocok dengan domain terverifikasi organisasi, selain itu tertunda.
     *
     * @return array{0: string|null, 1: 'active'|'pending'|null}
     *
     * @throws ValidationException
     */
    public function resolveOrganization(?string $code, string $email): array
    {
        $domain = Str::after($email, '@');
        $domainOrganizationId = DB::table('organization_domains')
            ->join('organizations', 'organizations.id', '=', 'organization_domains.organization_id')
            ->where('organization_domains.domain', $domain)
            ->whereNotNull('organization_domains.verified_at')
            ->where('organizations.status', 'active')
            ->value('organization_domains.organization_id');

        $code = $code === null ? '' : strtoupper(trim($code));
        if ($code !== '') {
            $organizationId = Organization::query()->where('code', $code)->where('status', 'active')->value('id');
            if (! is_string($organizationId)) {
                throw ValidationException::withMessages(['organization_code' => 'Kode organisasi tidak ditemukan.']);
            }

            return [$organizationId, $domainOrganizationId === $organizationId ? 'active' : 'pending'];
        }

        return is_string($domainOrganizationId) ? [$domainOrganizationId, 'active'] : [null, null];
    }

    public static function resendKey(string $email): string
    {
        return 'register:otp:'.TokenHasher::hash(User::normalizeEmail($email), 'register-otp-throttle');
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function sendCode(User $user, array $metadata): void
    {
        $limit = (int) config('security.registration.otp_resend_per_hour');
        if (RateLimiter::tooManyAttempts(self::resendKey($user->email), $limit)) {
            $this->securityEvents->log('rate_limit_exceeded', 'warning', $user->id, ['scope' => 'registration_otp']);

            return;
        }
        RateLimiter::hit(self::resendKey($user->email), 3600);

        $ttl = (int) config('security.registration.otp_ttl_minutes');
        $code = $this->tokens->issueOtp($user, OneTimeTokens::PURPOSE_EMAIL_VERIFICATION, $ttl, $metadata);
        $user->notify(new RegistrationCodeNotification($code, $ttl));
    }

    private function pendingUser(string $email): ?User
    {
        /** @var User|null $user */
        $user = User::query()->where('email', User::normalizeEmail($email))->first();

        return $user !== null && $this->isSelfRegistrationPending($user) ? $user : null;
    }

    /** Akun hasil registrasi mandiri yang belum diverifikasi (bukan akun undangan admin). */
    private function isSelfRegistrationPending(User $user): bool
    {
        return $user->status === 'pending_verification'
            && $user->password !== null
            && ! DB::table('role_user')->where('user_id', $user->id)->exists();
    }

    private function applyPhone(User $user, ?string $phone): void
    {
        $normalized = self::normalizePhone($phone);
        if ($normalized === null) {
            return;
        }

        $index = TokenHasher::hash($normalized, 'phone-bidx');
        // Nomor yang sudah dipakai akun lain diabaikan diam-diam agar tidak menjadi oracle.
        if (DB::table('users')->where('phone_bidx', $index)->exists()) {
            return;
        }

        $user->forceFill(['phone_encrypted' => $normalized, 'phone_bidx' => $index]);
    }

    /** Normalisasi ke E.164 (default kode negara Indonesia). */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/[\s\-().]/', '', (string) $phone) ?? '';
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '0')) {
            $digits = '+62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '62')) {
            $digits = '+'.$digits;
        }

        return preg_match('/^\+[1-9]\d{7,14}$/', $digits) === 1 ? $digits : null;
    }
}
