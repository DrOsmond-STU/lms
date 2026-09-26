<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pengumuman untuk seluruh platform, satu organisasi, atau satu kelas.
 *
 * @property string $id
 * @property string $scope
 * @property string|null $organization_id
 * @property string|null $course_class_id
 * @property string $title
 * @property string $body
 * @property string $body_html
 * @property bool $is_pinned
 * @property Carbon $publish_at
 * @property Carbon|null $expires_at
 * @property string|null $created_by
 * @property Carbon $created_at
 * @property-read Organization|null $organization
 * @property-read CourseClass|null $courseClass
 * @property-read User|null $author
 */
final class Announcement extends Model
{
    use HasUuids;

    public const SCOPES = ['platform' => 'Seluruh platform', 'organization' => 'Organisasi', 'class' => 'Kelas'];

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['is_pinned' => 'boolean', 'publish_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<CourseClass, $this> */
    public function courseClass(): BelongsTo
    {
        return $this->belongsTo(CourseClass::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isLive(): bool
    {
        return $this->publish_at->isPast() && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function scopeLabel(): string
    {
        return self::SCOPES[$this->scope] ?? $this->scope;
    }
}
