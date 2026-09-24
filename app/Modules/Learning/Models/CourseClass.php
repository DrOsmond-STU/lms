<?php

declare(strict_types=1);

namespace App\Modules\Learning\Models;

use App\Modules\Catalog\Models\Program;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Kelas/batch sebuah program (FR-CLS-001).
 *
 * @property string $id
 * @property string $program_id
 * @property string $batch_name
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property Carbon|null $enroll_opens_at
 * @property Carbon|null $enroll_closes_at
 * @property int $quota
 * @property int $enrolled_count
 * @property string $mode
 * @property string|null $location
 * @property string|null $restricted_organization_id
 * @property array<string, mixed> $completion_rules
 * @property string $status
 * @property-read Program $program
 */
final class CourseClass extends Model
{
    use HasUuids;

    public const STATUSES = ['draft' => 'Draf', 'open' => 'Pendaftaran Dibuka', 'running' => 'Berjalan', 'closed' => 'Selesai', 'archived' => 'Diarsipkan'];

    /** Transisi status yang sah (FR-CLS-001). */
    public const TRANSITIONS = [
        'draft' => ['open'],
        'open' => ['running', 'closed'],
        'running' => ['closed'],
        'closed' => ['archived'],
        'archived' => [],
    ];

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'enroll_opens_at' => 'datetime',
            'enroll_closes_at' => 'datetime',
            'quota' => 'integer',
            'enrolled_count' => 'integer',
            'completion_rules' => 'array',
        ];
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function restrictedOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'restricted_organization_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function trainers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'class_trainers')->withPivot('role')->orderBy('name');
    }

    /** @return HasMany<Module, $this> */
    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('position');
    }

    public function label(): string
    {
        return $this->program->name.' — '.$this->batch_name;
    }

    public function seatsLeft(): int
    {
        return max(0, $this->quota - $this->enrolled_count);
    }

    /** Pendaftaran terbuka menurut status & jendela waktu (kuota diperiksa atomik saat mendaftar). */
    public function isEnrollmentOpen(): bool
    {
        return $this->status === 'open'
            && ($this->enroll_opens_at === null || $this->enroll_opens_at->isPast())
            && ($this->enroll_closes_at === null || $this->enroll_closes_at->isFuture());
    }

    public function minimumScore(): float
    {
        return (float) ($this->completion_rules['min_final_score'] ?? $this->program->passing_score);
    }

    public function requiresFinalExam(): bool
    {
        return (bool) ($this->completion_rules['require_final_exam'] ?? true);
    }
}
