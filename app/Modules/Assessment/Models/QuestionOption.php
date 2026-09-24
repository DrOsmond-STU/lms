<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $question_id
 * @property string $body_html
 * @property bool $is_correct
 * @property int $position
 */
final class QuestionOption extends Model
{
    use HasUuids;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [];

    /** @var list<string> */
    protected $hidden = ['is_correct'];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean', 'position' => 'integer'];
    }
}
