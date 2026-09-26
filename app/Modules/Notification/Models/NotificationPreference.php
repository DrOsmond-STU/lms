<?php

declare(strict_types=1);

namespace App\Modules\Notification\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Preferensi kanal notifikasi per pengguna. In-app selalu aktif; email/push/WhatsApp dapat
 * dimatikan, dan kategori tertentu dapat dibisukan (kecuali `security`).
 *
 * @property string $user_id
 * @property bool $email_enabled
 * @property bool $push_enabled
 * @property bool $whatsapp_enabled
 * @property list<string> $muted_categories
 */
final class NotificationPreference extends Model
{
    protected $primaryKey = 'user_id';

    protected $keyType = 'string';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['email_enabled' => 'boolean', 'push_enabled' => 'boolean', 'whatsapp_enabled' => 'boolean', 'muted_categories' => 'array'];
    }

    public static function for(User $user): self
    {
        $preference = self::query()->find($user->id);
        if ($preference instanceof self) {
            return $preference;
        }
        $preference = new self;
        $preference->forceFill(['user_id' => $user->id, 'email_enabled' => true, 'push_enabled' => true, 'whatsapp_enabled' => false, 'muted_categories' => []]);

        return $preference;
    }

    public function allows(string $channel, string $category): bool
    {
        if ($category !== 'security' && in_array($category, $this->muted_categories, true)) {
            return false;
        }

        return match ($channel) {
            'email' => $this->email_enabled,
            'push' => $this->push_enabled,
            'whatsapp' => $this->whatsapp_enabled,
            default => true,
        };
    }
}
