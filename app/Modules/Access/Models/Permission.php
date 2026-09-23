<?php

declare(strict_types=1);

namespace App\Modules\Access\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $code
 */
final class Permission extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [];
}
