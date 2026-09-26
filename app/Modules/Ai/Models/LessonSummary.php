<?php

declare(strict_types=1);

namespace App\Modules\Ai\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Rangkuman materi buatan AI (dibagikan ke semua peserta materi tersebut).
 *
 * @property string $lesson_id
 * @property string $summary_md
 * @property string $summary_html
 * @property string $model
 * @property Carbon $generated_at
 */
final class LessonSummary extends Model
{
    protected $primaryKey = 'lesson_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['generated_at' => 'datetime'];
    }
}
