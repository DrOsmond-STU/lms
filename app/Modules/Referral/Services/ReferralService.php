<?php

declare(strict_types=1);

namespace App\Modules\Referral\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Services\Notifier;
use App\Modules\Payment\Models\PaymentTransaction;
use App\Modules\Referral\Models\ReferralCommission;
use App\Modules\Referral\Models\ReferralPayout;
use App\Modules\Referral\Models\ReferralProfile;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Program referral. Aturan:
 * - satu kode per pengguna; akun baru dikaitkan sekali saat registrasi (first-touch) dan tidak
 *   dapat dipindah; referral diri sendiri ditolak;
 * - komisi hanya lahir dari pembayaran yang dikonfirmasi lunas (uang benar-benar diterima), dalam
 *   masa berlaku sejak registrasi, dengan persentase & batas dari Pengaturan Sistem;
 * - pencairan oleh Admin Keuangan per batch dengan referensi transfer; pengaju ≠ penerima.
 */
final class ReferralService
{
    public const COOKIE = 'stu_ref';

    public const COOKIE_DAYS = 30;

    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly Notifier $notifier,
        private readonly TenantContext $tenant,
    ) {}

    public static function isEnabled(): bool
    {
        return (bool) config('lms.referral_enabled');
    }

    public static function isValidCode(?string $code): bool
    {
        return is_string($code) && preg_match('/^[A-Z2-9]{6,12}$/', $code) === 1;
    }

    /** Profil (dibuat saat pertama diminta) dengan kode unik tanpa karakter ambigu. */
    public function profileFor(User $user): ReferralProfile
    {
        $profile = ReferralProfile::query()->whereKey($user->id)->first();
        if ($profile !== null) {
            return $profile;
        }
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $profile = new ReferralProfile;
                $profile->forceFill(['user_id' => $user->id, 'code' => self::generateCode()])->save();

                return $profile;
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        throw new \RuntimeException('Gagal membuat kode referral unik.');
    }

    public function linkFor(ReferralProfile $profile): string
    {
        return rtrim((string) config('app.url'), '/').'/daftar?ref='.$profile->code;
    }

    /** Kunjungan lewat tautan referral (dipanggil middleware, tanpa konteks pengguna). */
    public function recordVisit(string $code): bool
    {
        if (! self::isValidCode($code) || ! self::isEnabled()) {
            return false;
        }

        return $this->tenant->runAsSystem(fn (): bool => ReferralProfile::query()->where('code', $code)
            ->whereHas('user', fn ($query) => $query->where('status', 'active'))
            ->increment('visits') === 1);
    }

    /** Kaitkan akun baru ke pemilik kode (sekali, saat registrasi). */
    public function attribute(User $newUser, ?string $code): bool
    {
        $code = strtoupper(trim((string) $code));
        if (! self::isEnabled() || ! self::isValidCode($code) || $newUser->referred_by !== null) {
            return false;
        }
        $referrer = $this->tenant->runAsSystem(fn (): ?User => ReferralProfile::query()->where('code', $code)->with('user')->first()?->user);
        if ($referrer === null || ! $referrer->isActive() || $referrer->id === $newUser->id || strcasecmp($referrer->email, $newUser->email) === 0) {
            return false;
        }

        $newUser->forceFill(['referred_by' => $referrer->id, 'referred_at' => now()])->save();
        $this->audit->record('referral.attributed', null, 'user', $newUser->id, ['referrer_id' => $referrer->id, 'code' => $code]);
        $this->notifier->send($referrer, 'referral', 'Referral baru', $newUser->name.' mendaftar dengan kode referral Anda. Komisi diberikan saat pembayaran pelatihannya dikonfirmasi lunas.', '/peserta/referral');

        return true;
    }

    /** Dipanggil PaymentService setelah tagihan lunas: hitung & catat komisi bila memenuhi syarat. */
    public function onPaymentSettled(PaymentTransaction $transaction): ?ReferralCommission
    {
        if (! self::isEnabled()) {
            return null;
        }
        $payer = $transaction->user;
        if ($payer->referred_by === null || $payer->referred_at === null || $payer->referred_by === $payer->id) {
            return null;
        }
        $months = max(1, (int) config('lms.referral_validity_months'));
        if ($payer->referred_at->copy()->addMonths($months)->isPast()) {
            return null;
        }
        $referrer = User::query()->whereKey($payer->referred_by)->first();
        if ($referrer === null || ! $referrer->isActive()) {
            return null;
        }
        $percent = max(0, min(100, (int) config('lms.referral_commission_percent')));
        $amount = intdiv($transaction->gross_amount * $percent, 100);
        $cap = (int) config('lms.referral_max_commission');
        if ($cap > 0) {
            $amount = min($amount, $cap);
        }
        if ($amount <= 0) {
            return null;
        }

        try {
            $commission = $this->tenant->runAsSystem(function () use ($transaction, $payer, $referrer, $percent, $amount): ReferralCommission {
                $commission = new ReferralCommission;
                $commission->forceFill([
                    'id' => (string) Str::uuid7(),
                    'referrer_id' => $referrer->id,
                    'referred_user_id' => $payer->id,
                    'payment_transaction_id' => $transaction->id,
                    'base_amount' => $transaction->gross_amount,
                    'rate_percent' => $percent,
                    'amount' => $amount,
                    'status' => 'pending',
                ])->save();

                return $commission;
            });
        } catch (UniqueConstraintViolationException) {
            return null; // pelunasan yang sama tidak menghasilkan komisi ganda
        }

        $this->audit->record('referral.commission_earned', null, 'referral_commission', $commission->id, [
            'referrer_id' => $referrer->id, 'referred_user_id' => $payer->id, 'payment_transaction_id' => $transaction->id, 'amount' => $amount, 'rate_percent' => $percent,
        ]);
        $this->notifier->send($referrer, 'referral', 'Komisi referral diterima', 'Komisi '.PaymentTransaction::rupiah($amount).' dari pembayaran '.$payer->name.' menunggu pencairan.', '/peserta/referral');

        return $commission;
    }

    /** @param  array{bank_name: string, bank_account: string, bank_account_name: string}  $data */
    public function updatePayoutAccount(User $user, array $data): void
    {
        $profile = $this->profileFor($user);
        $profile->forceFill([
            'bank_name' => trim($data['bank_name']),
            'bank_account_encrypted' => Crypt::encryptString(preg_replace('/\s+/', '', $data['bank_account']) ?? ''),
            'bank_account_name' => trim($data['bank_account_name']),
        ])->save();
        $this->audit->record('referral.payout_account_updated', $user, 'user', $user->id, ['bank_name' => $profile->bank_name]);
    }

    /** @return array{visits: int, registered: int, paid_users: int, pending: int, paid: int, void: int} */
    public function stats(User $user): array
    {
        $profile = $this->profileFor($user);
        $byStatus = ReferralCommission::query()->where('referrer_id', $user->id)->selectRaw('status, coalesce(sum(amount), 0) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'visits' => $profile->visits,
            'registered' => User::query()->where('referred_by', $user->id)->count(),
            'paid_users' => ReferralCommission::query()->where('referrer_id', $user->id)->where('status', '<>', 'void')->distinct()->count('referred_user_id'),
            'pending' => (int) ($byStatus['pending'] ?? 0),
            'paid' => (int) ($byStatus['paid'] ?? 0),
            'void' => (int) ($byStatus['void'] ?? 0),
        ];
    }

    /** Cairkan semua komisi tertunda seorang referrer dalam satu batch. */
    public function payout(User $referrer, User $actor, string $reference, ?string $note): ReferralPayout
    {
        if ($actor->id === $referrer->id || ! $actor->hasPermission('referral.pay')) {
            throw ValidationException::withMessages(['reference' => 'Anda tidak berwenang mencairkan komisi ini (pengaju tidak boleh menerima sendiri).']);
        }

        $payout = DB::transaction(function () use ($referrer, $actor, $reference, $note): ReferralPayout {
            $pending = ReferralCommission::query()->where('referrer_id', $referrer->id)->where('status', 'pending')->lockForUpdate()->get();
            if ($pending->isEmpty()) {
                throw ValidationException::withMessages(['reference' => 'Tidak ada komisi tertunda untuk dicairkan.']);
            }
            $payout = new ReferralPayout;
            $payout->forceFill([
                'id' => (string) Str::uuid7(),
                'referrer_id' => $referrer->id,
                'amount' => (int) $pending->sum('amount'),
                'commission_count' => $pending->count(),
                'reference' => trim($reference),
                'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
                'paid_by' => $actor->id,
                'paid_at' => now(),
            ])->save();
            ReferralCommission::query()->whereIn('id', $pending->pluck('id'))->where('status', 'pending')
                ->update(['status' => 'paid', 'payout_id' => $payout->id, 'updated_at' => now()]);
            $this->audit->record('referral.payout_recorded', $actor, 'referral_payout', $payout->id, [
                'referrer_id' => $referrer->id, 'amount' => $payout->amount, 'commission_count' => $payout->commission_count, 'reference' => $payout->reference,
            ], $note);

            return $payout;
        });

        $this->notifier->send($referrer, 'referral', 'Komisi referral dicairkan', 'Komisi '.PaymentTransaction::rupiah($payout->amount).' ('.$payout->commission_count.' transaksi) telah dibayarkan. Referensi: '.$payout->reference.'.', '/peserta/referral', true);

        return $payout;
    }

    /** Batalkan komisi tertunda (mis. pembayaran ternyata dibatalkan/refund). */
    public function void(ReferralCommission $commission, User $actor, string $reason): void
    {
        if (! $actor->hasPermission('referral.pay')) {
            throw ValidationException::withMessages(['reason' => 'Anda tidak berwenang membatalkan komisi.']);
        }
        $updated = ReferralCommission::query()->whereKey($commission->id)->where('status', 'pending')
            ->update(['status' => 'void', 'void_reason' => $reason, 'voided_by' => $actor->id, 'voided_at' => now(), 'updated_at' => now()]);
        if ($updated !== 1) {
            throw ValidationException::withMessages(['reason' => 'Komisi sudah '.$commission->statusLabel().'.']);
        }
        $this->audit->record('referral.commission_voided', $actor, 'referral_commission', $commission->id, ['referrer_id' => $commission->referrer_id], $reason);
    }

    private static function generateCode(): string
    {
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }
}
