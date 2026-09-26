<?php

declare(strict_types=1);

namespace App\Modules\Learning\Models;

use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kelompok belajar dalam satu kelas (pembagian peserta, mentor opsional).
 *
 * @property string $id
 * @property string $course_class_id
 * @property string $name
 * @property string|null $description
 * @property string|null $mentor_id
 * @property-read User|null $mentor
 * @property-read Collection<int, Enrollment> $enrollments
 */
final class ClassGroup extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [];

    /** @return BelongsTo<User, $this> */
    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    /** @return HasMany<Enrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'group_id');
    }
}
