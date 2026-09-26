<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Models;

use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\MediaAsset;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pengumpulan tugas per enrollment (ber-tenant, RLS). Satu baris per tugas per enrollment;
 * pengumpulan ulang menaikkan `version` sampai dinilai.
 *
 * @property string $id
 * @property string $assignment_id
 * @property string $enrollment_id
 * @property string $user_id
 * @property string|null $media_asset_id
 * @property string|null $text_answer
 * @property Carbon $submitted_at
 * @property bool $is_late
 * @property string $status
 * @property string|null $score
 * @property string|null $feedback
 * @property array<string, float>|null $rubric_scores
 * @property string|null $graded_by
 * @property Carbon|null $graded_at
 * @property int $version
 * @property-read Assignment $assignment
 * @property-read Enrollment $enrollment
 * @property-read MediaAsset|null $media
 * @property-read User|null $grader
 */
final class AssignmentSubmission extends Model
{
    use HasUuids;

    public const STATUSES = ['submitted' => 'Menunggu penilaian', 'graded' => 'Dinilai', 'returned' => 'Dikembalikan untuk revisi'];

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'is_late' => 'boolean', 'rubric_scores' => 'array', 'graded_at' => 'datetime', 'version' => 'integer'];
    }

    /** @return BelongsTo<Assignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    /** @return BelongsTo<User, $this> */
    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
