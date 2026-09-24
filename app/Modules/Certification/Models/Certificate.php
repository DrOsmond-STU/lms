<?php

declare(strict_types=1);

namespace App\Modules\Certification\Models;

use App\Modules\Catalog\Models\Program;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Sertifikat terbit (ber-tenant, RLS). Status publik: valid/expired/revoked diturunkan dari
 * `status` + `valid_until` (FR-CERT-012).
 *
 * @property string $id
 * @property string|null $organization_id
 * @property string $enrollment_id
 * @property string $user_id
 * @property string $program_id
 * @property string $template_id
 * @property string $number
 * @property string $verification_code
 * @property string $holder_name
 * @property string $holder_name_masked
 * @property string $program_name
 * @property string $provider_name
 * @property string $category
 * @property Carbon $issued_at
 * @property Carbon|null $valid_until
 * @property string $status
 * @property string|null $pdf_storage_key
 * @property string|null $pdf_sha256
 * @property string|null $signature_cert_fingerprint
 * @property Carbon|null $signed_at
 * @property string $approved_by
 * @property-read User $user
 * @property-read Program $program
 * @property-read Enrollment $enrollment
 */
final class Certificate extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [];

    /** @var list<string> */
    protected $hidden = ['pdf_storage_key'];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'valid_until' => 'date',
            'signed_at' => 'datetime',
            'expiry_reminded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return 'valid'|'expired'|'revoked'|'superseded'|'generating' */
    public function publicStatus(): string
    {
        return match (true) {
            $this->status === 'revoked' => 'revoked',
            $this->status === 'superseded' => 'superseded',
            in_array($this->status, ['generating', 'generation_failed'], true) => 'generating',
            $this->valid_until !== null && $this->valid_until->endOfDay()->isPast() => 'expired',
            default => 'valid',
        };
    }

    public static function statusLabel(string $status): string
    {
        return [
            'valid' => 'Valid', 'expired' => 'Kedaluwarsa', 'revoked' => 'Dicabut', 'superseded' => 'Digantikan',
            'generating' => 'Sedang diproses', 'not_found' => 'Tidak ditemukan',
        ][$status] ?? $status;
    }

    /** Kode verifikasi berkelompok untuk tampilan (SEC-CERT-10). */
    public function formattedCode(): string
    {
        return implode('-', str_split($this->verification_code, 4));
    }
}
