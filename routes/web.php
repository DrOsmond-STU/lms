<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use App\Modules\Identity\Http\Controllers\ConfirmAccessController;
use App\Modules\Identity\Http\Controllers\LoginController;
use App\Modules\Identity\Http\Controllers\MfaChallengeController;
use App\Modules\Identity\Http\Controllers\MfaSetupController;
use App\Modules\Identity\Http\Controllers\PasswordResetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute web — URL halaman berbahasa Indonesia (docs/06 §5).
| Semua rute di luar grup `guest`/publik WAJIB auth + mfa (deny by default).
|--------------------------------------------------------------------------
*/

Route::view('/', 'welcome')->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/masuk', [LoginController::class, 'show'])->name('login');
    Route::post('/masuk', [LoginController::class, 'store'])->name('login.store');

    Route::get('/masuk/mfa', [MfaChallengeController::class, 'show'])->name('mfa.challenge');
    Route::post('/masuk/mfa', [MfaChallengeController::class, 'store'])->middleware('throttle:10,1')->name('mfa.challenge.store');

    Route::get('/lupa-kata-sandi', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/lupa-kata-sandi', [PasswordResetController::class, 'email'])->middleware('throttle:10,1')->name('password.email');
    Route::get('/reset-kata-sandi/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-kata-sandi', [PasswordResetController::class, 'update'])->middleware('throttle:10,1')->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/keluar', [LoginController::class, 'destroy'])->name('logout');

    // Pendaftaran MFA dapat diakses sebelum MFA terverifikasi (untuk peran wajib-MFA).
    Route::get('/akun/mfa/aktifkan', [MfaSetupController::class, 'show'])->name('mfa.setup');
    Route::post('/akun/mfa/aktifkan', [MfaSetupController::class, 'store'])->middleware('throttle:10,1')->name('mfa.setup.store');

    Route::middleware('mfa')->group(function (): void {
        Route::get('/akun/mfa/kode-pemulihan', [MfaSetupController::class, 'recoveryCodes'])->name('mfa.recovery-codes');
        Route::get('/konfirmasi-akses', [ConfirmAccessController::class, 'show'])->name('password.confirm');
        Route::post('/konfirmasi-akses', [ConfirmAccessController::class, 'store'])->middleware('throttle:10,1')->name('password.confirm.store');

        Route::get('/dasbor', [DashboardController::class, 'redirect'])->name('dashboard');

        Route::get('/peserta', [DashboardController::class, 'show'])->defaults('workspace', 'participant')
            ->middleware('workspace:participant')->name('participant.dashboard');
        Route::get('/trainer', [DashboardController::class, 'show'])->defaults('workspace', 'trainer')
            ->middleware('workspace:trainer')->name('trainer.dashboard');
        Route::get('/organisasi', [DashboardController::class, 'show'])->defaults('workspace', 'organization')
            ->middleware('workspace:organization')->name('organization.dashboard');
        Route::get('/admin', [DashboardController::class, 'show'])->defaults('workspace', 'admin')
            ->middleware('workspace:admin')->name('admin.dashboard');
    });
});
