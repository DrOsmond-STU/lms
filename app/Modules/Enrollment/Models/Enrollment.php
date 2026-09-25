<?php

declare(strict_types=1);

namespace App\Modules\Enrollment\Models;

use App\Modules\Catalog\Models\Program;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Payment\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Enrollment peserta pada kelas (ber-tenant, RLS). Status hanya berubah melalui
 * EnrollmentService (FR-ENR-004).
 *
 * @property string $id
 * @property string|null $organization_id
 * @property string $user_id
 * @property string $course_class_id
 * @property string $program_id
 * @property string $status
 * @property string $source
 * @property int $progress_percent
 * @property string|null $final_score
 * @property Carbon|null $enrolled_at
 * @property Carbon|null $completed_at
 * @property string|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $rejection_reason
 * @property Carbon $created_at
 * @property-read User $user
 * @property-read CourseClass $courseClass
 * @property-read Program $program
 * @property-read PaymentTransaction|null $payment
 */
final class Enrollment extends Model
{
    use HasUuids;

    public const STATUSES = [
        'awaiting_payment' => 'Menunggu Pembayaran',
        'enrolled' => 'Terdaftar',
        'in_progress' => 'Sedang Belajar',
        'pending_approval' => 'Menunggu Approval Sertifikat',
        'passed' => 'Lulus',
        'failed' => 'Tidak Lulus',
        'cancelled' => 'Dibatalkan',
    ];

    /** Nada chip status di UI (critical|high|medium|low|info|neutral). */
    public const STATUS_TONES = [
        'awaiting_payment' => 'high',
        'enrolled' => 'info',
        'in_progress' => 'info',
        'pending_approval' => 'medium',
        'passed' => 'low',
        'failed' => 'critical',
        'cancelled' => 'neutral',
    ];

    public const ACTIVE = ['enrolled', 'in_progress'];

    /** Transisi yang sah (docs/02 §21.1). */
    public const TRANSITIONS = [
        'awaiting_payment' => ['enrolled', 'cancelled'],
        'enrolled' => ['in_progress', 'pending_approval', 'failed', 'cancelled'],
        'in_progress' => ['pending_approval', 'failed', 'cancelled'],
        'pending_approval' => ['passed', 'in_progress'],
        'passed' => [],
        'failed' => ['in_progress'], // hanya lewat pemberian kesempatan tambahan (FR-ASM-009)
        'cancelled' => [],
    ];

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'progress_percent' => 'integer',
            'enrolled_at' => 'datetime',
            'completed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<CourseClass, $this> */
    public function courseClass(): BelongsTo
    {
        return $this->belongsTo(CourseClass::class);
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** Tagihan transfer manual (hanya enrollment berbayar). */
    /** @return HasOne<PaymentTransaction, $this> */
    public function payment(): HasOne
    {
        return $this->hasOne(PaymentTransaction::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }

    /** Peserta masih boleh mengakses materi (termasuk setelah lulus untuk ditinjau). */
    public function canAccessContent(): bool
    {
        return in_array($this->status, ['enrolled', 'in_progress', 'pending_approval', 'passed'], true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
