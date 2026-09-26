<?php

declare(strict_types=1);

namespace App\Modules\Discussion\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Learning\Models\Lesson;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Utas diskusi / pertanyaan (Q&A) / komentar materi dalam satu kelas.
 *
 * @property string $id
 * @property string $course_class_id
 * @property string|null $lesson_id
 * @property string $author_id
 * @property string $kind
 * @property string|null $title
 * @property string $body
 * @property string $body_html
 * @property bool $is_pinned
 * @property bool $is_locked
 * @property bool $is_resolved
 * @property bool $is_hidden
 * @property int $replies_count
 * @property Carbon|null $last_post_at
 * @property Carbon $created_at
 * @property-read User $author
 * @property-read CourseClass $courseClass
 * @property-read Lesson|null $lesson
 */
final class DiscussionThread extends Model
{
    use HasUuids;

    public const KINDS = ['discussion' => 'Diskusi', 'question' => 'Tanya Jawab', 'comment' => 'Komentar materi'];

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['is_pinned' => 'boolean', 'is_locked' => 'boolean', 'is_resolved' => 'boolean', 'is_hidden' => 'boolean', 'replies_count' => 'integer', 'last_post_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<CourseClass, $this> */
    public function courseClass(): BelongsTo
    {
        return $this->belongsTo(CourseClass::class);
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /** @return HasMany<DiscussionPost, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(DiscussionPost::class, 'thread_id')->orderBy('created_at');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }
}
