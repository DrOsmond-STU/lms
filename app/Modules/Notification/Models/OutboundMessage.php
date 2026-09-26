<?php

declare(strict_types=1);

namespace App\Modules\Notification\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Log pesan keluar kanal eksternal (push / WhatsApp) untuk pemantauan pengiriman.
 *
 * @property string $id
 * @property string|null $user_id
 * @property string $channel
 * @property string $target
 * @property string $title
 * @property string $status
 * @property string|null $error
 */
final class OutboundMessage extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public static function log(?string $userId, string $channel, string $target, string $title): self
    {
        $message = new self;
        $message->forceFill(['user_id' => $userId, 'channel' => $channel, 'target' => mb_substr($target, 0, 120), 'title' => mb_substr($title, 0, 160), 'status' => 'queued'])->save();

        return $message;
    }

    public function markSent(): void
    {
        $this->forceFill(['status' => 'sent', 'sent_at' => now(), 'error' => null])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill(['status' => 'failed', 'error' => mb_substr($error, 0, 500)])->save();
    }
}
