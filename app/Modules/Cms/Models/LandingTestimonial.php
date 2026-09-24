<?php

declare(strict_types=1);

namespace App\Modules\Cms\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Testimoni pengguna. Hanya terbit bila persetujuan publikasi dari orangnya sudah
 * dikonfirmasi admin (dijaga juga oleh CHECK di basis data). `is_sample` menandai konten
 * contoh untuk UAT — ditampilkan berlabel "Contoh".
 *
 * @property string $id
 * @property string $name
 * @property string|null $role_title
 * @property string|null $organization_name
 * @property string|null $program_name
 * @property string $quote
 * @property int $rating
 * @property bool $consent_confirmed
 * @property bool $is_published
 * @property bool $is_sample
 * @property int $position
 */
final class LandingTestimonial extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'consent_confirmed' => 'boolean', 'is_published' => 'boolean', 'is_sample' => 'boolean', 'position' => 'integer'];
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)) ?: [])->filter()->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    }
}
