<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Models;

use App\Modules\Catalog\Models\Program;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $program_id
 * @property string $name
 * @property-read Program $program
 */
final class QuestionBank extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [];

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
