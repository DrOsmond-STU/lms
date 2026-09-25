<?php

declare(strict_types=1);

namespace App\Modules\Payment\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Kejadian pada transaksi (append-only, dijaga trigger basis data).
 *
 * @property string $id
 * @property string $payment_transaction_id
 * @property string $source
 * @property string $event
 * @property array<string, mixed> $payload
 * @property string|null $actor_id
 * @property Carbon $created_at
 * @property-read User|null $actor
 */
final class PaymentEvent extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public const LABELS = [
        'created' => 'Tagihan dibuat',
        'proof_submitted' => 'Bukti transfer diunggah',
        'proof_rejected' => 'Bukti transfer ditolak',
        'approval_requested' => 'Menunggu persetujuan admin kedua',
        'settled' => 'Dikonfirmasi lunas',
        'failed' => 'Transaksi ditolak/dibatalkan',
        'expired' => 'Kedaluwarsa',
    ];

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function label(): string
    {
        return self::LABELS[$this->event] ?? $this->event;
    }
}
