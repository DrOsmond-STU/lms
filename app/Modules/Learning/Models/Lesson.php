<?php

declare(strict_types=1);

namespace App\Modules\Learning\Models;

use App\Modules\Assessment\Models\Assessment;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lesson: video, PDF, teks tersanitasi, tautan eksternal (allowlist), atau kuis (FR-CNT-002).
 *
 * @property string $id
 * @property string $chapter_id
 * @property string $type
 * @property string $title
 * @property int $position
 * @property bool $is_required
 * @property string|null $media_asset_id
 * @property string|null $assessment_id
 * @property string|null $body_md
 * @property string|null $body_html
 * @property string|null $external_url
 * @property bool $allow_download
 * @property int|null $duration_seconds
 * @property int $version
 * @property Carbon|null $unlock_at
 * @property int|null $unlock_after_days
 * @property string|null $prerequisite_lesson_id
 * @property-read Chapter $chapter
 * @property-read MediaAsset|null $media
 * @property-read Assessment|null $assessment
 */
final class Lesson extends Model
{
    use HasUuids;

    public const TYPES = ['video' => 'Video', 'audio' => 'Audio', 'pdf' => 'PDF', 'document' => 'Dokumen', 'text' => 'Teks', 'link' => 'Tautan', 'quiz' => 'Kuis'];

    /** Lesson berbasis berkas media yang diunggah. */
    public const MEDIA_TYPES = ['video', 'audio', 'pdf', 'document'];

    /** Lesson yang progresnya dihitung dari durasi diputar (heartbeat). */
    public const TIMED_TYPES = ['video', 'audio'];

    /** Lesson yang diselesaikan dengan tombol "Tandai selesai". */
    public const MANUAL_COMPLETE_TYPES = ['pdf', 'document', 'text', 'link'];

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'allow_download' => 'boolean', 'duration_seconds' => 'integer', 'version' => 'integer', 'unlock_at' => 'datetime', 'unlock_after_days' => 'integer'];
    }

    /** @return BelongsTo<Chapter, $this> */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /** @return BelongsTo<Lesson, $this> */
    public function prerequisite(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'prerequisite_lesson_id');
    }

    public function isTimed(): bool
    {
        return in_array($this->type, self::TIMED_TYPES, true);
    }

    public function courseClassId(): string
    {
        return $this->chapter->module->course_class_id;
    }
}
