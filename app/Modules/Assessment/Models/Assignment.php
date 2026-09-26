<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Models;

use App\Modules\Learning\Models\CourseClass;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Tugas (assignment): peserta mengumpulkan teks dan/atau berkas; dinilai trainer dengan skor,
 * umpan balik, dan rubrik opsional. Tugas wajib menjadi syarat kelulusan.
 *
 * @property string $id
 * @property string $course_class_id
 * @property string $title
 * @property string|null $instructions_md
 * @property string|null $instructions_html
 * @property Carbon|null $due_at
 * @property string $max_score
 * @property string|null $passing_score
 * @property bool $is_required
 * @property bool $allow_late
 * @property bool $allow_text
 * @property bool $allow_file
 * @property list<array{name: string, max: float, description?: string}>|null $rubric
 * @property int $position
 * @property-read CourseClass $courseClass
 */
final class Assignment extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'is_required' => 'boolean', 'allow_late' => 'boolean', 'allow_text' => 'boolean', 'allow_file' => 'boolean', 'rubric' => 'array', 'position' => 'integer'];
    }

    /** @return BelongsTo<CourseClass, $this> */
    public function courseClass(): BelongsTo
    {
        return $this->belongsTo(CourseClass::class);
    }

    /** @return HasMany<AssignmentSubmission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null && $this->due_at->isPast();
    }

    /** Pengumpulan masih diterima (belum lewat tenggat, atau terlambat diizinkan). */
    public function acceptsSubmissions(): bool
    {
        return ! $this->isOverdue() || $this->allow_late;
    }

    public function rubricMax(): float
    {
        return array_sum(array_map(fn (array $c): float => (float) $c['max'], $this->rubric ?? []));
    }
}
