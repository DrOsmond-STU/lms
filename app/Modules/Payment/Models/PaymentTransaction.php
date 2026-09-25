<?php

declare(strict_types=1);

namespace App\Modules\Payment\Models;

use App\Modules\Catalog\Models\Program;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Learning\Models\MediaAsset;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * @property string $id
 * @property string|null $organization_id
 * @property string $user_id
 * @property string $program_id
 * @property string $course_class_id
 * @property string $enrollment_id
 * @property string $order_id
 * @property string|null $invoice_number
 * @property int $list_price
 * @property int $discount_amount
 * @property int $gross_amount
 * @property string $currency
 * @property string $status
 * @property string $payment_method
 * @property string $gateway
 * @property bool $needs_review
 * @property string|null $proof_media_asset_id
 * @property string|null $proof_note
 * @property Carbon|null $proof_submitted_at
 * @property string|null $review_note
 * @property string|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $settled_by
 * @property Carbon|null $settled_at
 * @property string|null $approval_request_id
 * @property string|null $billing_name
 * @property string|null $billing_tax_id_encrypted
 * @property string|null $billing_address_encrypted
 * @property Carbon $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read Program $program
 * @property-read CourseClass $courseClass
 * @property-read Enrollment $enrollment
 * @property-read MediaAsset|null $proof
 * @property-read User|null $settler
 * @property-read Collection<int, PaymentEvent> $events
 */
final class PaymentTransaction extends Model
{
    use HasUuids;

    public const STATUSES = [
        'pending' => 'Menunggu Pembayaran',
        'settled' => 'Lunas',
        'failed' => 'Ditolak',
        'expired' => 'Kedaluwarsa',
        'refund_pending' => 'Refund Diproses',
        'refunded' => 'Dikembalikan',
    ];

    /** Nada chip status di UI (critical|high|medium|low|info|neutral). */
    public const STATUS_TONES = [
        'pending' => 'high',
        'settled' => 'low',
        'failed' => 'critical',
        'expired' => 'neutral',
        'refund_pending' => 'medium',
        'refunded' => 'neutral',
    ];

    public const METHODS = ['manual_transfer' => 'Transfer bank (manual)'];

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'list_price' => 'integer',
            'discount_amount' => 'integer',
            'gross_amount' => 'integer',
            'needs_review' => 'boolean',
            'proof_submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'settled_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return BelongsTo<CourseClass, $this> */
    public function courseClass(): BelongsTo
    {
        return $this->belongsTo(CourseClass::class);
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function proof(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'proof_media_asset_id');
    }

    /** @return BelongsTo<User, $this> */
    public function settler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    /** @return HasMany<PaymentEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class)->orderBy('created_at');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSettled(): bool
    {
        return $this->status === 'settled';
    }

    /** Masih boleh mengunggah/mengganti bukti: belum lunas, belum lewat batas waktu. */
    public function acceptsProof(): bool
    {
        return $this->isPending() && $this->expires_at->isFuture();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusTone(): string
    {
        return self::STATUS_TONES[$this->status] ?? 'neutral';
    }

    public function amountLabel(): string
    {
        return self::rupiah($this->gross_amount);
    }

    public static function rupiah(int $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }

    public function billingTaxId(): ?string
    {
        return $this->billing_tax_id_encrypted === null ? null : Crypt::decryptString($this->billing_tax_id_encrypted);
    }

    public function billingAddress(): ?string
    {
        return $this->billing_address_encrypted === null ? null : Crypt::decryptString($this->billing_address_encrypted);
    }
}
