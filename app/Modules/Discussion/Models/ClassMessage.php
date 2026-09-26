<?php

declare(strict_types=1);

namespace App\Modules\Discussion\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pesan obrolan kelas (teks polos, dibersihkan; disegarkan berkala oleh komponen Livewire).
 *
 * @property string $id
 * @property string $course_class_id
 * @property string $user_id
 * @property string $body
 * @property bool $is_hidden
 * @property Carbon $created_at
 * @property-read User $user
 */
final class ClassMessage extends Model
{
    use HasUuids;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['is_hidden' => 'boolean', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
