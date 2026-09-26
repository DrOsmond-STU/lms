<?php

declare(strict_types=1);

namespace App\Modules\Learning\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\MediaAsset;
use App\Modules\Learning\Services\MediaStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Penyajian media privat (FR-CNT-004): hanya via URL bertanda tangan berumur pendek yang
 * terikat pada pengguna login yang sama; mendukung Range (seek video). Berkas yang belum
 * lolos pindai tidak pernah disajikan.
 */
final class MediaStreamController
{
    public function __invoke(Request $request, MediaAsset $media, MediaStorage $storage): BinaryFileResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($request->query('u') === $user->id && $media->isServable(), 404);

        $download = in_array($media->kind, ['document', 'submission'], true);
        if (! $download && preg_match('/^lesson:([0-9a-f-]{36})$/', (string) $request->query('c'), $match) === 1) {
            $download = (bool) Lesson::query()->whereKey($match[1])->where('media_asset_id', $media->id)->value('allow_download');
        }

        $response = new BinaryFileResponse($storage->absolutePath($media), 200, [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ], true, null, false, true);
        $response->setContentDisposition($download ? ResponseHeaderBag::DISPOSITION_ATTACHMENT : ResponseHeaderBag::DISPOSITION_INLINE, $media->original_filename, 'media');

        return $response;
    }
}
