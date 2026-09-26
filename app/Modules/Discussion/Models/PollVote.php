<?php

declare(strict_types=1);

namespace App\Modules\Discussion\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $poll_id
 * @property string $user_id
 * @property list<int> $option_indexes
 */
final class PollVote extends Model
{
    use HasUuids;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['option_indexes' => 'array'];
    }
}
