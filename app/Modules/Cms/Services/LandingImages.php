<?php

declare(strict_types=1);

namespace App\Modules\Cms\Services;

use App\Modules\Audit\Services\SecurityEventLogger;
use App\Modules\Identity\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Gambar beranda (slide & logo mitra). Jenis diperiksa dari isi berkas (magic bytes), lalu
 * gambar DIGAMBAR ULANG dengan GD: metadata (EXIF/GPS) terbuang, muatan tersembunyi
 * (polyglot) tidak ikut, dan ukuran dibatasi. SVG sengaja tidak diterima (dapat memuat skrip).
 * Berkas disimpan di disk privat dan disajikan lewat rute publik khusus.
 */
final class LandingImages
{
    private const MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    private const MAX_BYTES = 5 * 1024 * 1024;

    /** @var array<string, array{0: int, 1: int}> lebar & tinggi maksimum per jenis */
    private const LIMITS = ['slide' => [1920, 1080], 'logo' => [480, 240]];

    public function __construct(private readonly SecurityEventLogger $securityEvents) {}

    /** @throws ValidationException */
    public function store(UploadedFile $file, string $kind, User $actor, string $field): string
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([$field => 'Unggahan gagal. Coba lagi.']);
        }
        $path = (string) $file->getRealPath();
        $detected = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (! in_array($detected, self::MIMES, true)) {
            $this->securityEvents->log('upload_rejected', 'warning', $actor->id, ['reason' => 'type', 'detected' => $detected, 'kind' => 'landing_'.$kind]);

            throw ValidationException::withMessages([$field => 'Gunakan gambar JPG, PNG, atau WebP.']);
        }
        if ((int) $file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([$field => 'Ukuran gambar maksimal 5 MB.']);
        }

        return $this->encode($path, $kind, $field);
    }

    /**
     * Simpan gambar dari berkas lokal tepercaya (mis. slide bawaan aplikasi) lewat pipeline
     * yang sama: dibaca, digambar ulang, dan disimpan di disk privat.
     */
    public function storeFromPath(string $path, string $kind): string
    {
        return $this->encode($path, $kind, 'image');
    }

    /** @throws ValidationException */
    private function encode(string $path, string $kind, string $field): string
    {
        $size = @getimagesize($path);
        if ($size === false || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > 40_000_000) {
            throw ValidationException::withMessages([$field => 'Gambar tidak dapat dibaca atau resolusinya terlalu besar.']);
        }
        $source = @imagecreatefromstring((string) file_get_contents($path));
        if ($source === false) {
            throw ValidationException::withMessages([$field => 'Gambar tidak dapat dibaca.']);
        }

        [$maxWidth, $maxHeight] = self::LIMITS[$kind];
        $scale = min(1, $maxWidth / imagesx($source), $maxHeight / imagesy($source));
        $width = max(1, (int) round(imagesx($source) * $scale));
        $height = max(1, (int) round(imagesy($source) * $scale));

        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, (int) imagecolorallocatealpha($canvas, 255, 255, 255, 127));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        ob_start();
        if (function_exists('imagewebp')) {
            imagewebp($canvas, null, $kind === 'logo' ? 90 : 80);
            $extension = 'webp';
        } elseif ($kind === 'logo') {
            imagepng($canvas, null, 8);
            $extension = 'png';
        } else {
            imagejpeg($canvas, null, 82);
            $extension = 'jpg';
        }
        $binary = (string) ob_get_clean();

        $key = 'landing/'.$kind.'/'.Str::uuid7().'.'.$extension;
        Storage::disk((string) config('media.disk'))->put($key, $binary);

        return $key;
    }

    public function delete(?string $key): void
    {
        if ($key !== null && str_starts_with($key, 'landing/')) {
            Storage::disk((string) config('media.disk'))->delete($key);
        }
    }

    /** Tipe konten berdasarkan ekstensi kunci yang dibuat sendiri oleh store(). */
    public static function mimeFor(string $key): string
    {
        return match (pathinfo($key, PATHINFO_EXTENSION)) {
            'webp' => 'image/webp',
            'png' => 'image/png',
            default => 'image/jpeg',
        };
    }
}
