<?php

declare(strict_types=1);

namespace App\Modules\Referral\Http\Middleware;

use App\Modules\Referral\Services\ReferralService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tautan referral (`?ref=KODE`) pada halaman publik mana pun: kode disimpan di cookie
 * (terenkripsi, 30 hari) dan diusulkan otomatis pada formulir pendaftaran. Hanya untuk tamu;
 * pengguna yang sudah punya akun tidak dapat dikaitkan ulang.
 */
final class CaptureReferral
{
    public function __construct(private readonly ReferralService $referrals) {}

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $code = strtoupper(trim((string) $request->query('ref', '')));
        $capture = $code !== '' && $request->isMethod('GET') && $request->user() === null
            && ReferralService::isValidCode($code) && $request->cookie(ReferralService::COOKIE) !== $code;

        $response = $next($request);

        if ($capture && $this->referrals->recordVisit($code)) {
            $response->headers->setCookie(new Cookie(
                ReferralService::COOKIE, $code, now()->addDays(ReferralService::COOKIE_DAYS), '/', null,
                (bool) config('session.secure'), true, false, 'lax',
            ));
        }

        return $response;
    }
}
