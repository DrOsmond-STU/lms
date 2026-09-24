<?php

declare(strict_types=1);

namespace App\Modules\Access\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Permintaan persetujuan kedua (maker–checker, keamanan/03 SEC-AUTHZ-15).
 *
 * @property string $id
 * @property string $action
 * @property string $subject_type
 * @property string $subject_id
 * @property array<string, mixed> $payload
 * @property string $reason
 * @property string $requested_by
 * @property Carbon $requested_at
 * @property string|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decision
 * @property Carbon $expires_at
 * @property-read User $requester
 */
final class ApprovalRequest extends Model
{
    use HasUuids;

    public $timestamps = false;

    public const ACTIONS = [
        'certificate.revoke' => 'Pencabutan sertifikat',
        'role.assign_super_admin' => 'Penetapan Super Admin',
        'certificate_template.activate' => 'Aktivasi template sertifikat',
    ];

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'requested_at' => 'datetime', 'decided_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isOpen(): bool
    {
        return $this->decision === null && $this->expires_at->isFuture();
    }
}
