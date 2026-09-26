<?php

declare(strict_types=1);

namespace App\Modules\Notification\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Langganan Web Push satu peramban (RFC 8030). Kunci p256dh/auth milik peramban.
 *
 * @property string $id
 * @property string $user_id
 * @property string $endpoint
 * @property string $endpoint_hash
 * @property string $p256dh
 * @property string $auth
 * @property string $content_encoding
 * @property string|null $user_agent
 * @property int $failures
 * @property Carbon|null $last_used_at
 */
final class PushSubscription extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['failures' => 'integer', 'last_used_at' => 'datetime'];
    }
}
