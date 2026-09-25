<?php

declare(strict_types=1);

namespace App\Modules\Referral\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Pencairan komisi (satu batch untuk satu referrer) yang dicatat Admin Keuangan.
 *
 * @property string $id
 * @property string $referrer_id
 * @property int $amount
 * @property int $commission_count
 * @property string $reference
 * @property string|null $note
 * @property string $paid_by
 * @property Carbon $paid_at
 * @property Carbon $created_at
 * @property-read User $referrer
 * @property-read User $payer
 * @property-read Collection<int, ReferralCommission> $commissions
 */
final class ReferralPayout extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'commission_count' => 'integer', 'paid_at' => 'datetime', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /** @return HasMany<ReferralCommission, $this> */
    public function commissions(): HasMany
    {
        return $this->hasMany(ReferralCommission::class, 'payout_id');
    }
}
