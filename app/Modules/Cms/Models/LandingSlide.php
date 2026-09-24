<?php

declare(strict_types=1);

namespace App\Modules\Cms\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Slide beranda: gambar (opsional) di bawah lapisan gradasi biru, judul & tombol ajakan.
 *
 * @property string $id
 * @property string|null $eyebrow
 * @property string $title
 * @property string|null $subtitle
 * @property string|null $cta_label
 * @property string|null $cta_url
 * @property string|null $image_path
 * @property int $position
 * @property bool $is_active
 * @property Carbon|null $updated_at
 */
final class LandingSlide extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['position' => 'integer', 'is_active' => 'boolean'];
    }
}
