<?php

declare(strict_types=1);

namespace App\Modules\Certification\Http\Controllers;

use App\Modules\Certification\Services\VerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Verifikasi publik (FR-CERT-007, FR-API-001, docs/08 PUB-06/BARU-19). Rate limit per IP
 * (limiter `verification`), `noindex`, `Referrer-Policy: no-referrer`, hasil minimal.
 */
final class VerificationController
{
    public function __construct(private readonly VerificationService $verification) {}

    public function form(): Response
    {
        return $this->respond(view('verification.form'));
    }

    public function lookup(Request $request): Response
    {
        $data = $request->validate([
            'mode' => ['required', 'in:code,number'],
            'value' => ['required', 'string', 'max:80'],
            'full_name' => ['nullable', 'string', 'max:120'],
        ]);

        $result = $data['mode'] === 'code'
            ? $this->verification->byCode($data['value'], (string) $request->ip(), $request->userAgent())
            : $this->verification->byNumber($data['value'], $data['full_name'] ?? null, (string) $request->ip(), $request->userAgent());

        return $this->respond(view('verification.result', $result + ['lookup' => $data['mode']]));
    }

    public function show(Request $request, string $code): Response
    {
        $result = $this->verification->byCode($code, (string) $request->ip(), $request->userAgent());

        return $this->respond(view('verification.result', $result + ['lookup' => 'code']));
    }

    public function api(Request $request, string $code): JsonResponse
    {
        $result = $this->verification->byCode($code, (string) $request->ip(), $request->userAgent(), 'api');
        $certificate = $result['certificate'];
        $status = $result['status'] === 'generating' ? 'not_found' : $result['status'];

        $body = $certificate === null || $status === 'not_found' ? ['status' => 'not_found'] : [
            'status' => $status,
            'certificate_number' => $certificate->number,
            'holder_name' => $result['name'],
            'program' => $certificate->program_name,
            'category' => $certificate->category,
            'provider' => $certificate->provider_name,
            'issued_at' => $certificate->issued_at->toDateString(),
            'valid_until' => $certificate->valid_until?->toDateString(),
        ];

        return response()->json(['data' => $body], $status === 'not_found' ? 404 : 200, [
            'Cache-Control' => 'private, max-age=60',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    private function respond(View $view): Response
    {
        return response($view->render(), 200, [
            'Cache-Control' => 'private, max-age=60',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
