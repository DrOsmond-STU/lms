<?php

declare(strict_types=1);

namespace App\Modules\Cms\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Perusahaan/universitas pengguna LMS yang ditampilkan di pita logo berjalan.
 *
 * @property string $id
 * @property string $name
 * @property string $type
 * @property string|null $logo_path
 * @property string|null $website_url
 * @property int $position
 * @property bool $is_active
 * @property Carbon|null $updated_at
 */
final class LandingPartner extends Model
{
    use HasUuids;

    public const TYPES = [
        'university' => 'Universitas / Sekolah',
        'company' => 'Perusahaan',
        'government' => 'Instansi Pemerintah',
        'association' => 'Asosiasi / LSP',
        'other' => 'Lainnya',
    ];

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['position' => 'integer', 'is_active' => 'boolean'];
    }

    public function monogram(): string
    {
        $words = collect(preg_split('/\s+/', trim((string) preg_replace('/^(PT|CV|UD)\.?\s+/i', '', $this->name))) ?: [])->filter();

        return $words->take(2)->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    }
}
