<?php

declare(strict_types=1);

namespace App\Modules\Learning\Http\Controllers;

use App\Modules\Assessment\Models\AssignmentSubmission;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\MediaAsset;
use App\Modules\Learning\Services\ClassAccess;
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
    public function __invoke(Request $request, MediaAsset $media, MediaStorage $storage, ClassAccess $access): BinaryFileResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($request->query('u') === $user->id && $media->isServable(), 404);

        // Hak akses diperiksa ulang pada setiap permintaan, bukan hanya saat URL diterbitkan:
        // enrollment yang dibatalkan atau trainer yang dilepas tidak lagi dapat memakai URL yang masih berlaku.
        $download = in_array($media->kind, ['document', 'submission'], true);
        $context = (string) $request->query('c');
        if (preg_match('/^lesson:([0-9a-f-]{36})$/', $context, $match) === 1) {
            $lesson = Lesson::query()->whereKey($match[1])->where('media_asset_id', $media->id)->first();
            abort_if($lesson === null, 404);
            $classId = $lesson->courseClassId();
            $enrolled = Enrollment::query()->where('user_id', $user->id)->where('course_class_id', $classId)
                ->whereIn('status', ['enrolled', 'in_progress', 'pending_approval', 'passed'])->exists();
            abort_unless($enrolled || $this->staffCanView($access, $user, $classId), 404);
            $download = $download || (bool) $lesson->allow_download;
        } elseif (preg_match('/^submission:([0-9a-f-]{36})$/', $context, $match) === 1) {
            $submission = AssignmentSubmission::query()->with('enrollment:id,user_id,course_class_id')->whereKey($match[1])->where('media_asset_id', $media->id)->first();
            abort_if($submission === null, 404);
            abort_unless($submission->enrollment->user_id === $user->id || $this->staffCanView($access, $user, $submission->enrollment->course_class_id), 404);
        } else {
            abort(404);
        }

        $response = new BinaryFileResponse($storage->absolutePath($media), 200, [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ], true, null, false, true);
        $response->setContentDisposition($download ? ResponseHeaderBag::DISPOSITION_ATTACHMENT : ResponseHeaderBag::DISPOSITION_INLINE, $media->original_filename, 'media');

        return $response;
    }

    private function staffCanView(ClassAccess $access, User $user, string $classId): bool
    {
        $class = CourseClass::query()->find($classId);

        return $class instanceof CourseClass && $access->canView($user, $class);
    }
}
