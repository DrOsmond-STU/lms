<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Models;

use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Attempt asesmen (ber-tenant, RLS). Soal & urutan di-snapshot saat mulai.
 *
 * @property string $id
 * @property string|null $organization_id
 * @property string $course_class_id
 * @property string $assessment_id
 * @property string $enrollment_id
 * @property string $user_id
 * @property int $attempt_no
 * @property string $status
 * @property Carbon $started_at
 * @property Carbon $deadline_at
 * @property Carbon|null $submitted_at
 * @property string|null $score
 * @property bool|null $passed
 * @property bool $needs_manual_grading
 * @property list<string> $question_order
 * @property array<string, list<string>> $option_order
 * @property array<string, int> $question_versions
 * @property array<string, mixed>|null $integrity_flags
 * @property string|null $voided_reason
 * @property-read Assessment $assessment
 * @property-read Enrollment $enrollment
 */
final class ExamAttempt extends Model
{
    use HasUuids;

    public const STATUSES = [
        'in_progress' => 'Sedang dikerjakan',
        'submitted' => 'Menunggu penilaian',
        'auto_submitted' => 'Dikumpulkan otomatis',
        'graded' => 'Dinilai',
        'voided' => 'Dibatalkan',
    ];

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'attempt_no' => 'integer',
            'started_at' => 'datetime',
            'deadline_at' => 'datetime',
            'submitted_at' => 'datetime',
            'passed' => 'boolean',
            'needs_manual_grading' => 'boolean',
            'question_order' => 'array',
            'option_order' => 'array',
            'question_versions' => 'array',
            'integrity_flags' => 'array',
        ];
    }

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return HasMany<AttemptAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function secondsLeft(): int
    {
        return max(0, (int) now()->diffInSeconds($this->deadline_at, false));
    }
}
