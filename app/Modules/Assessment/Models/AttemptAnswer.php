<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $exam_attempt_id
 * @property string $question_id
 * @property list<string>|null $selected_option_ids
 * @property string|null $text_answer
 * @property bool|null $is_correct
 * @property string|null $points_awarded
 * @property string|null $graded_by
 * @property Carbon|null $answered_at
 */
final class AttemptAnswer extends Model
{
    use HasUuids;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'selected_option_ids' => 'array',
            'is_correct' => 'boolean',
            'answered_at' => 'datetime',
            'graded_at' => 'datetime',
        ];
    }
}
