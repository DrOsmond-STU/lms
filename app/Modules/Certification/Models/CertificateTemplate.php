<?php

declare(strict_types=1);

namespace App\Modules\Certification\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Template sertifikat berversi (FR-CERT-001, SEC-CERT-04): hanya field teks terstruktur
 * (bukan HTML bebas) sehingga tidak ada injeksi markup; immutable setelah dipakai.
 *
 * @property string $id
 * @property string $name
 * @property string $category
 * @property string|null $program_id
 * @property int $version
 * @property string $title_text
 * @property string $body_text
 * @property string $signatory_name
 * @property string $signatory_title
 * @property string $accent_color
 * @property bool $is_active
 * @property Carbon|null $used_at
 */
final class CertificateTemplate extends Model
{
    use HasUuids;

    /** Placeholder yang diizinkan di body_text (SEC-INPUT-24). */
    public const PLACEHOLDERS = ['{nama}', '{program}', '{penyelenggara}', '{tanggal}', '{nomor}', '{kategori}'];

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['version' => 'integer', 'is_active' => 'boolean', 'used_at' => 'datetime'];
    }

    public function isLocked(): bool
    {
        return $this->used_at !== null;
    }
}
