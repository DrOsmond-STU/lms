<?php

declare(strict_types=1);

namespace App\Modules\Learning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Berkas media privat. `storage_key` tidak pernah diekspos ke klien.
 *
 * @property string $id
 * @property string|null $owner_id
 * @property string $kind
 * @property string $storage_key
 * @property string $original_filename
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $sha256
 * @property string $scan_status
 * @property string|null $scanner
 */
final class MediaAsset extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [];

    /** @var list<string> */
    protected $hidden = ['storage_key'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'scanned_at' => 'datetime'];
    }

    public function isServable(): bool
    {
        return $this->scan_status === 'clean';
    }

    public function humanSize(): string
    {
        $size = $this->size_bytes;
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($size < 1024 || $unit === 'GB') {
                return number_format($size, $unit === 'B' ? 0 : 1, ',', '.').' '.$unit;
            }
            $size /= 1024;
        }

        return '';
    }
}
