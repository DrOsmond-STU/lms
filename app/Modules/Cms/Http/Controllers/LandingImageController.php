<?php

declare(strict_types=1);

namespace App\Modules\Cms\Http\Controllers;

use App\Modules\Cms\Models\LandingPartner;
use App\Modules\Cms\Models\LandingSlide;
use App\Modules\Cms\Services\LandingImages;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menyajikan gambar beranda dari disk privat. Hanya kunci yang dibuat LandingImages
 * (prefiks landing/) — tidak ada path dari masukan pengguna.
 */
final class LandingImageController
{
    public function slide(LandingSlide $slide): Response
    {
        return $this->serve($slide->image_path);
    }

    public function partner(LandingPartner $partner): Response
    {
        abort_unless($partner->is_active, 404);

        return $this->serve($partner->logo_path);
    }

    private function serve(?string $key): Response
    {
        $disk = Storage::disk((string) config('media.disk'));
        abort_if($key === null || ! str_starts_with($key, 'landing/') || ! $disk->exists($key), 404);

        return response((string) $disk->get($key), 200, [
            'Content-Type' => LandingImages::mimeFor($key),
            'Cache-Control' => 'public, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
