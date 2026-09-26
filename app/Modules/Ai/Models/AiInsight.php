<?php

declare(strict_types=1);

namespace App\Modules\Ai\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Keluaran AI yang di-cache per subjek: rekomendasi belajar (enrollment), jalur belajar (user),
 * analisis kelas (class).
 *
 * @property string $id
 * @property string $kind
 * @property string $subject_id
 * @property string $body_md
 * @property string $body_html
 * @property array<string, mixed>|null $data
 * @property Carbon $generated_at
 */
final class AiInsight extends Model
{
    use HasUuids;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['data' => 'array', 'generated_at' => 'datetime'];
    }

    public static function find(string $kind, string $subjectId): ?self
    {
        return self::query()->where('kind', $kind)->where('subject_id', $subjectId)->first();
    }

    /** @param array<string, mixed>|null $data */
    public static function put(string $kind, string $subjectId, string $markdown, string $html, ?array $data = null): self
    {
        $insight = self::find($kind, $subjectId) ?? new self;
        $insight->forceFill(['kind' => $kind, 'subject_id' => $subjectId, 'body_md' => $markdown, 'body_html' => $html, 'data' => $data, 'generated_at' => now()])->save();

        return $insight;
    }

    public function isFresh(int $hours): bool
    {
        return $this->generated_at->gt(now()->subHours($hours));
    }
}
