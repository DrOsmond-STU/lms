<?php

declare(strict_types=1);

namespace App\Modules\Learning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Progres lesson per enrollment (ber-tenant, RLS).
 *
 * @property string $id
 * @property string $enrollment_id
 * @property string $lesson_id
 * @property string $status
 * @property int $watched_seconds
 * @property int $max_position_seconds
 * @property int $time_spent_seconds
 * @property Carbon|null $first_opened_at
 * @property Carbon|null $last_heartbeat_at
 * @property Carbon|null $completed_at
 * @property array<string, mixed>|null $integrity_flags
 */
final class LessonProgress extends Model
{
    use HasUuids;

    protected $table = 'lesson_progress';

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'watched_seconds' => 'integer',
            'max_position_seconds' => 'integer',
            'time_spent_seconds' => 'integer',
            'first_opened_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'completed_at' => 'datetime',
            'integrity_flags' => 'array',
        ];
    }
}
