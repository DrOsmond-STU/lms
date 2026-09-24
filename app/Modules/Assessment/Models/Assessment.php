<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Models;

use App\Modules\Learning\Models\CourseClass;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Kuis (formatif) atau ujian akhir (sumatif) per kelas (FR-ASM-002/003).
 *
 * @property string $id
 * @property string $course_class_id
 * @property string $question_bank_id
 * @property string $kind
 * @property string $title
 * @property int $duration_minutes
 * @property int $max_attempts
 * @property int $cooldown_minutes
 * @property Carbon|null $opens_at
 * @property Carbon|null $closes_at
 * @property bool $shuffle_questions
 * @property bool $shuffle_options
 * @property int $question_count
 * @property array<string, int>|null $selection_rules
 * @property string $passing_score
 * @property string $review_policy
 * @property bool $is_required
 * @property bool $requires_prerequisites
 * @property-read CourseClass $courseClass
 * @property-read QuestionBank $bank
 */
final class Assessment extends Model
{
    use HasUuids;

    public const KINDS = ['quiz' => 'Kuis', 'final_exam' => 'Ujian Akhir'];

    public const REVIEW_POLICIES = [
        'never' => 'Tidak ditampilkan',
        'after_submit' => 'Setelah dikumpulkan',
        'after_close' => 'Setelah jendela ujian ditutup',
    ];

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'max_attempts' => 'integer',
            'cooldown_minutes' => 'integer',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
            'question_count' => 'integer',
            'selection_rules' => 'array',
            'is_required' => 'boolean',
            'requires_prerequisites' => 'boolean',
        ];
    }

    /** @return BelongsTo<CourseClass, $this> */
    public function courseClass(): BelongsTo
    {
        return $this->belongsTo(CourseClass::class);
    }

    /** @return BelongsTo<QuestionBank, $this> */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    public function isFinal(): bool
    {
        return $this->kind === 'final_exam';
    }

    public function isWithinWindow(): bool
    {
        return ($this->opens_at === null || $this->opens_at->isPast())
            && ($this->closes_at === null || $this->closes_at->isFuture());
    }

    /** Pembahasan & kunci boleh ditampilkan (SEC-EXAM-03). */
    public function reviewAllowed(): bool
    {
        return match ($this->review_policy) {
            'after_submit' => true,
            'after_close' => $this->closes_at !== null && $this->closes_at->isPast(),
            default => false,
        };
    }
}
