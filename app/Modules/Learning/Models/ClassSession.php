<?php

declare(strict_types=1);

namespace App\Modules\Learning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Sesi kelas: pertemuan tatap muka atau live class (video conference) dengan presensi.
 *
 * @property string $id
 * @property string $course_class_id
 * @property string $title
 * @property string|null $description
 * @property string $type
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string|null $meeting_url
 * @property string|null $location
 * @property string $attendance_mode
 * @property string|null $checkin_code
 * @property int $checkin_opens_before
 * @property int $checkin_closes_after
 * @property string|null $created_by
 * @property-read CourseClass $courseClass
 */
final class ClassSession extends Model
{
    use HasUuids;

    public const TYPES = ['online' => 'Live class (daring)', 'offline' => 'Tatap muka'];

    public const ATTENDANCE_MODES = ['self' => 'Peserta cek-in sendiri', 'manual' => 'Dicatat trainer', 'none' => 'Tanpa presensi'];

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'checkin_opens_before' => 'integer', 'checkin_closes_after' => 'integer'];
    }

    /** @return BelongsTo<CourseClass, $this> */
    public function courseClass(): BelongsTo
    {
        return $this->belongsTo(CourseClass::class);
    }

    /** @return HasMany<AttendanceRecord, $this> */
    public function attendance(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function isLive(): bool
    {
        return $this->type === 'online' && $this->meeting_url !== null;
    }

    /** Jendela cek-in mandiri: sebelum mulai s.d. beberapa menit setelah mulai. */
    public function checkinOpen(): bool
    {
        $now = now();

        return $this->attendance_mode === 'self'
            && $now->greaterThanOrEqualTo($this->starts_at->copy()->subMinutes($this->checkin_opens_before))
            && $now->lessThanOrEqualTo($this->starts_at->copy()->addMinutes($this->checkin_closes_after));
    }

    /** Tautan meeting hanya dibagikan menjelang dan selama sesi (bukan jauh hari sebelumnya). */
    public function joinOpen(): bool
    {
        return $this->isLive() && now()->greaterThanOrEqualTo($this->starts_at->copy()->subMinutes(30)) && now()->lessThanOrEqualTo($this->ends_at);
    }

    public function isPast(): bool
    {
        return $this->ends_at->isPast();
    }
}
