<?php

declare(strict_types=1);

namespace App\Modules\Notification\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Notifikasi in-app (FR-NTF-001). `action_url` hanya path internal (constraint DB).
 *
 * @property string $id
 * @property string $user_id
 * @property string $category
 * @property string $title
 * @property string $body
 * @property string|null $action_url
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 */
final class InAppNotification extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'notifications';

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'created_at' => 'datetime'];
    }
}
