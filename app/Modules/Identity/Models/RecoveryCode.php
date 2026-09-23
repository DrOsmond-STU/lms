<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $code_hash
 * @property Carbon|null $used_at
 */
final class RecoveryCode extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'mfa_recovery_codes';

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['used_at' => 'datetime'];
    }
}
