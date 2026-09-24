<?php

declare(strict_types=1);

namespace App\Modules\Learning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $course_class_id
 * @property string $title
 * @property int $position
 */
final class Module extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [];

    /** @return BelongsTo<CourseClass, $this> */
    public function courseClass(): BelongsTo
    {
        return $this->belongsTo(CourseClass::class);
    }

    /** @return HasMany<Chapter, $this> */
    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class)->orderBy('position');
    }
}
