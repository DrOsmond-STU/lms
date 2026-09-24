<?php

declare(strict_types=1);

namespace App\Modules\Certification\Http\Controllers;

use App\Modules\Certification\Models\Certificate;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Sertifikat Saya" & unduhan PDF (FR-CERT-006): URL bertanda tangan 5 menit terikat
 * pada pengguna; berkas yang sama selalu disajikan (hash konsisten, SEC-CERT-03).
 */
final class MyCertificatesController
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $certificates = Certificate::query()->where('user_id', $user->id)->orderByDesc('issued_at')->get();

        return view('certificates.mine', ['certificates' => $certificates]);
    }

    public function download(Request $request, Certificate $certificate): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($this->mayDownload($user, $certificate), 404);
        abort_unless($certificate->status === 'active' && $certificate->pdf_storage_key !== null, 409, 'PDF belum tersedia.');

        return redirect()->to(URL::temporarySignedRoute('certificates.file', now()->addMinutes(5), ['certificate' => $certificate->id, 'u' => $user->id]));
    }

    public function file(Request $request, Certificate $certificate): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($request->query('u') === $user->id && $this->mayDownload($user, $certificate), 404);
        abort_unless($certificate->pdf_storage_key !== null && $certificate->status === 'active', 404);

        $name = 'Sertifikat-'.preg_replace('/[^A-Za-z0-9-]+/', '-', $certificate->number).'.pdf';

        return Storage::disk('local')->download((string) $certificate->pdf_storage_key, $name, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function mayDownload(User $user, Certificate $certificate): bool
    {
        return $certificate->user_id === $user->id
            || ($user->isPlatformStaff() && $user->hasPermission('certificate.download'));
    }
}
