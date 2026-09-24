<?php

declare(strict_types=1);

use App\Modules\Certification\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

/*
| API publik v1 (docs/06). Tanpa kunci: rate limit ketat per IP (FR-API-001);
| kuota lebih tinggi dengan API key tersedia di Fase 3 (FR-API-003).
*/

Route::prefix('v1')->group(function (): void {
    Route::get('/certificates/verify/{code}', [VerificationController::class, 'api'])
        ->where('code', '[A-Za-z0-9-]{12,16}')
        ->middleware('throttle:verification')
        ->name('api.certificates.verify');
});
