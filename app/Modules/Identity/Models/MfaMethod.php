<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $type
 * @property string|null $secret_encrypted
 * @property int|null $last_totp_step
 * @property Carbon|null $confirmed_at
 */
final class MfaMethod extends Model
{
    use HasUuids;

    protected $table = 'user_mfa_methods';

    /** @var list<string> */
    protected $fillable = [];

    /** @var list<string> */
    protected $hidden = ['secret_encrypted'];

    protected function casts(): array
    {
        return [
            'secret_encrypted' => 'encrypted',
            'confirmed_at' => 'datetime',
            'last_used_at' => 'datetime',
            'last_totp_step' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
