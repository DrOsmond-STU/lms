<?php

declare(strict_types=1);

namespace App\Modules\Learning\Models;

use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Presensi per sesi per enrollment (ber-tenant, RLS).
 *
 * @property string $id
 * @property string $class_session_id
 * @property string $enrollment_id
 * @property string $user_id
 * @property string $status
 * @property string $method
 * @property Carbon|null $checked_in_at
 * @property string|null $note
 * @property-read Enrollment $enrollment
 */
final class AttendanceRecord extends Model
{
    use HasUuids;

    public const STATUSES = ['present' => 'Hadir', 'late' => 'Terlambat', 'absent' => 'Tidak hadir', 'excused' => 'Izin'];

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime'];
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
