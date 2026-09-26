<?php

declare(strict_types=1);

namespace App\Modules\Security\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Catatan satu berkas backup (DB atau media).
 *
 * @property string $id
 * @property string $kind
 * @property string $filename
 * @property int $size_bytes
 * @property string|null $checksum
 * @property string|null $method
 * @property bool $encrypted
 * @property string $status
 * @property string|null $error
 * @property string|null $created_by
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 */
final class Backup extends Model
{
    use HasUuids;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'encrypted' => 'boolean', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function humanSize(): string
    {
        $bytes = (int) ($this->getAttribute('size_bytes') ?? 0);
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024 || $unit === 'GB') {
                return round($bytes, $unit === 'B' ? 0 : 1).' '.$unit;
            }
            $bytes /= 1024;
        }

        return (string) $bytes;
    }
}
