<?php

declare(strict_types=1);

namespace App\Modules\Learning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $module_id
 * @property string $title
 * @property int $position
 * @property-read Module $module
 */
final class Chapter extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [];

    /** @return BelongsTo<Module, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /** @return HasMany<Lesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position');
    }
}
