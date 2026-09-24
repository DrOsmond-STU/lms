<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Learning\Models\CourseClass;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Program pelatihan/sertifikasi (FR-CAT-001).
 *
 * @property string $id
 * @property string $category
 * @property string $name
 * @property string $slug
 * @property string $provider_name
 * @property string|null $scheme_code
 * @property string $short_code
 * @property string|null $level
 * @property int $duration_hours
 * @property string $language
 * @property string $default_mode
 * @property string|null $description_md
 * @property string|null $description_html
 * @property string $passing_score
 * @property int $certificate_validity_months
 * @property int $price
 * @property string $status
 * @property string|null $created_by
 * @property string|null $submitted_by
 * @property string|null $reviewed_by
 * @property Carbon|null $published_at
 */
final class Program extends Model
{
    use HasUuids;

    public const CATEGORIES = ['international' => 'Internasional', 'bnsp' => 'BNSP'];

    public const MODES = ['online' => 'Online', 'offline' => 'Offline', 'hybrid' => 'Hybrid'];

    public const LEVELS = ['dasar' => 'Dasar', 'menengah' => 'Menengah', 'lanjut' => 'Lanjut'];

    public const STATUSES = ['draft' => 'Draf', 'in_review' => 'Menunggu Review', 'published' => 'Terbit', 'archived' => 'Diarsipkan'];

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'duration_hours' => 'integer',
            'certificate_validity_months' => 'integer',
            'price' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /** @return HasMany<CourseClass, $this> */
    public function classes(): HasMany
    {
        return $this->hasMany(CourseClass::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isFree(): bool
    {
        return $this->price === 0;
    }

    public function priceLabel(): string
    {
        return $this->isFree() ? 'Gratis' : 'Rp'.number_format($this->price, 0, ',', '.');
    }

    /** @return list<string> */
    public function tags(): array
    {
        /** @var list<string> */
        return DB::table('program_tags')->where('program_id', $this->id)->orderBy('tag')->pluck('tag')->all();
    }

    /** @param list<string> $tags */
    public function syncTags(array $tags): void
    {
        DB::table('program_tags')->where('program_id', $this->id)->delete();
        $rows = array_map(fn (string $tag): array => ['program_id' => $this->id, 'tag' => $tag], array_values(array_unique($tags)));
        if ($rows !== []) {
            DB::table('program_tags')->insert($rows);
        }
    }
}
