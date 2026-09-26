<?php

declare(strict_types=1);

namespace App\Modules\Security\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Permintaan hak subjek data (UU PDP): ekspor atau penghapusan akun.
 *
 * @property string $id
 * @property string $user_id
 * @property string $kind
 * @property string $status
 * @property string|null $note
 * @property string|null $decision_note
 * @property string|null $processed_by
 * @property Carbon|null $processed_at
 * @property Carbon $created_at
 * @property-read User $user
 */
final class PrivacyRequest extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public const KINDS = ['export' => 'Salinan data', 'delete' => 'Penghapusan akun'];

    public const STATUSES = ['pending' => 'Menunggu', 'processed' => 'Diproses', 'rejected' => 'Ditolak'];

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
