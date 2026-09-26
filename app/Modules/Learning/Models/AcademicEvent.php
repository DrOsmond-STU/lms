<?php

declare(strict_types=1);

namespace App\Modules\Learning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Kalender akademik: libur, periode ujian, pendaftaran, acara — lingkup platform,
 * organisasi, atau kelas.
 *
 * @property string $id
 * @property string $title
 * @property string|null $description
 * @property string $kind
 * @property string $scope
 * @property string|null $organization_id
 * @property string|null $course_class_id
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 */
final class AcademicEvent extends Model
{
    use HasUuids;

    public const KINDS = ['holiday' => 'Libur', 'exam' => 'Periode ujian', 'registration' => 'Pendaftaran', 'event' => 'Acara', 'other' => 'Lainnya'];

    public const SCOPES = ['platform' => 'Seluruh platform', 'organization' => 'Organisasi tertentu', 'class' => 'Kelas tertentu'];

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }
}
