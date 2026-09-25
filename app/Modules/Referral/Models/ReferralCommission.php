<?php

declare(strict_types=1);

namespace App\Modules\Referral\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Payment\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Komisi yang lahir dari satu pembayaran lunas akun yang direferensikan.
 *
 * @property string $id
 * @property string $referrer_id
 * @property string $referred_user_id
 * @property string $payment_transaction_id
 * @property int $base_amount
 * @property int $rate_percent
 * @property int $amount
 * @property string $status
 * @property string|null $payout_id
 * @property string|null $void_reason
 * @property string|null $voided_by
 * @property Carbon|null $voided_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $referrer
 * @property-read User $referredUser
 * @property-read PaymentTransaction $transaction
 * @property-read ReferralPayout|null $payout
 */
final class ReferralCommission extends Model
{
    use HasUuids;

    public const STATUSES = ['pending' => 'Menunggu Pencairan', 'paid' => 'Sudah Dibayar', 'void' => 'Dibatalkan'];

    public const STATUS_TONES = ['pending' => 'high', 'paid' => 'low', 'void' => 'neutral'];

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['base_amount' => 'integer', 'rate_percent' => 'integer', 'amount' => 'integer', 'voided_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    /** @return BelongsTo<PaymentTransaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    /** @return BelongsTo<ReferralPayout, $this> */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(ReferralPayout::class, 'payout_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusTone(): string
    {
        return self::STATUS_TONES[$this->status] ?? 'neutral';
    }
}
