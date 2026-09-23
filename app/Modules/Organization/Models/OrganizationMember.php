<?php

declare(strict_types=1);

namespace App\Modules\Organization\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabel ber-tenant dengan RLS. Query selalu dibatasi konteks tenant di basis data.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $user_id
 * @property string $status
 */
final class OrganizationMember extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
