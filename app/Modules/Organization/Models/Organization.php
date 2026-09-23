<?php

declare(strict_types=1);

namespace App\Modules\Organization\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Organisasi = batas tenant (docs/07 §6).
 *
 * @property string $id
 * @property string $name
 * @property string $code
 * @property string $type
 * @property string $status
 */
#[UseFactory(OrganizationFactory::class)]
final class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $fillable = ['name', 'city', 'accreditation', 'industry'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    /** @return HasMany<OrganizationMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }
}
