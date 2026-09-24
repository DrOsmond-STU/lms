<?php

declare(strict_types=1);

namespace App\Modules\Learning\Services;

use App\Modules\Audit\Services\SecurityEventLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\MediaAsset;
use App\Support\Media\MalwareScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Pipeline unggah media (FR-CNT-003, keamanan/05): validasi jenis sebenarnya dari isi berkas
 * (magic bytes), batas ukuran per jenis, nama berkas acak di disk privat, hash SHA-256,
 * pemindaian malware; berkas tidak lolos pindai tidak pernah disajikan.
 */
final class MediaStorage
{
    public function __construct(
        private readonly MalwareScanner $scanner,
        private readonly SecurityEventLogger $securityEvents,
    ) {}

    /** @throws ValidationException */
    public function store(UploadedFile $file, string $kind, User $owner, string $field = 'file'): MediaAsset
    {
        /** @var array{mimes: array<string, string>, max_mb: int}|null $rules */
        $rules = config("media.types.{$kind}");
        if ($rules === null || ! $file->isValid()) {
            $message = in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'Ukuran berkas melebihi batas unggah server ('.ini_get('upload_max_filesize').').'
                : 'Unggahan gagal. Coba lagi.';

            throw ValidationException::withMessages([$field => $message]);
        }

        $path = (string) $file->getRealPath();
        $detected = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (! array_key_exists($detected, $rules['mimes'])) {
            $this->securityEvents->log('upload_rejected', 'warning', $owner->id, ['reason' => 'type', 'detected' => $detected, 'kind' => $kind]);

            throw ValidationException::withMessages([$field => 'Jenis berkas tidak diizinkan untuk '.$kind.'.']);
        }
        if ($file->getSize() > $rules['max_mb'] * 1024 * 1024) {
            throw ValidationException::withMessages([$field => "Ukuran berkas maksimal {$rules['max_mb']} MB."]);
        }

        $result = $this->scanner->scan($path);
        if ($result->status === 'infected') {
            $this->securityEvents->log('malware_detected', 'high', $owner->id, ['signature' => $result->signature]);

            throw ValidationException::withMessages([$field => 'Berkas ditolak karena terdeteksi berbahaya.']);
        }

        $key = 'media/'.now()->format('Y/m').'/'.Str::uuid7().'.'.$rules['mimes'][$detected];
        Storage::disk((string) config('media.disk'))->putFileAs(dirname($key), $file, basename($key));

        $asset = new MediaAsset;
        $asset->forceFill([
            'owner_id' => $owner->id,
            'kind' => $kind,
            'storage_key' => $key,
            'original_filename' => self::safeName($file->getClientOriginalName()),
            'mime_type' => $detected,
            'size_bytes' => (int) $file->getSize(),
            'sha256' => (string) hash_file('sha256', $path),
            'scan_status' => $result->status === 'clean' ? 'clean' : 'error',
            'scanner' => $result->scanner,
            'scanned_at' => now(),
        ])->save();

        return $asset;
    }

    /** URL bertanda tangan berumur pendek, terikat pada pengguna yang meminta (FR-CNT-004). */
    public function signedUrl(MediaAsset $asset, User $viewer, string $context): string
    {
        return URL::temporarySignedRoute('media.stream', now()->addMinutes((int) config('media.signed_url_minutes')), [
            'media' => $asset->id,
            'u' => $viewer->id,
            'c' => $context,
        ]);
    }

    public function absolutePath(MediaAsset $asset): string
    {
        return Storage::disk((string) config('media.disk'))->path($asset->storage_key);
    }

    public static function safeName(string $name): string
    {
        $name = preg_replace('/[^\pL\pN ._()-]+/u', '_', basename($name)) ?? 'berkas';

        return Str::limit(trim($name, ' ._') ?: 'berkas', 150, '');
    }
}
