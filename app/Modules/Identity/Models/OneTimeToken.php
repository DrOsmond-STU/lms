<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Token sekali pakai (OTP registrasi, undangan, ubah email). Hanya hash yang disimpan.
 *
 * @property string $id
 * @property string $user_id
 * @property string $purpose
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 * @property int $attempts
 * @property array<string, mixed>|null $metadata
 */
final class OneTimeToken extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [];

    /** @var list<string> */
    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null && $this->expires_at->isFuture();
    }
}
