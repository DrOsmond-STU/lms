<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LegalController;
use App\Modules\Identity\Http\Controllers\AccountSecurityController;
use App\Modules\Identity\Http\Controllers\ConfirmAccessController;
use App\Modules\Identity\Http\Controllers\InvitationController;
use App\Modules\Identity\Http\Controllers\LoginController;
use App\Modules\Identity\Http\Controllers\MfaChallengeController;
use App\Modules\Identity\Http\Controllers\MfaSetupController;
use App\Modules\Identity\Http\Controllers\PasswordResetController;
use App\Modules\Identity\Http\Controllers\RegistrationController;
use App\Modules\Identity\Http\Controllers\UserAdminController;
use App\Modules\Organization\Http\Controllers\OrganizationAdminController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute web — URL halaman berbahasa Indonesia (docs/06 §5).
| Semua rute di luar grup `guest`/publik WAJIB auth + mfa (deny by default).
|--------------------------------------------------------------------------
*/

Route::view('/', 'welcome')->name('home');
Route::get('/syarat-ketentuan', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/kebijakan-privasi', [LegalController::class, 'privacy'])->name('legal.privacy');

Route::middleware('guest')->group(function (): void {
    Route::get('/masuk', [LoginController::class, 'show'])->name('login');
    Route::post('/masuk', [LoginController::class, 'store'])->name('login.store');

    Route::get('/masuk/mfa', [MfaChallengeController::class, 'show'])->name('mfa.challenge');
    Route::post('/masuk/mfa', [MfaChallengeController::class, 'store'])->middleware('throttle:10,1')->name('mfa.challenge.store');

    Route::get('/daftar', [RegistrationController::class, 'create'])->name('register');
    Route::post('/daftar', [RegistrationController::class, 'store'])->name('register.store');
    Route::get('/verifikasi-email', [RegistrationController::class, 'showVerify'])->name('register.verify');
    Route::post('/verifikasi-email', [RegistrationController::class, 'verify'])->middleware('throttle:10,1')->name('register.verify.store');
    Route::post('/verifikasi-email/kirim-ulang', [RegistrationController::class, 'resend'])->middleware('throttle:5,1')->name('register.resend');

    Route::get('/lupa-kata-sandi', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/lupa-kata-sandi', [PasswordResetController::class, 'email'])->middleware('throttle:10,1')->name('password.email');
    Route::get('/reset-kata-sandi/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-kata-sandi', [PasswordResetController::class, 'update'])->middleware('throttle:10,1')->name('password.update');

    Route::get('/undangan/{token}', [InvitationController::class, 'show'])->middleware('throttle:30,1')->name('invitation.show');
    Route::post('/undangan/{token}', [InvitationController::class, 'accept'])->middleware('throttle:10,1')->name('invitation.accept');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/keluar', [LoginController::class, 'destroy'])->name('logout');

    // Pendaftaran MFA dapat diakses sebelum MFA terverifikasi (untuk peran wajib-MFA).
    Route::get('/akun/mfa/aktifkan', [MfaSetupController::class, 'show'])->name('mfa.setup');
    Route::post('/akun/mfa/aktifkan', [MfaSetupController::class, 'store'])->middleware('throttle:10,1')->name('mfa.setup.store');

    Route::middleware('mfa')->group(function (): void {
        Route::get('/akun/mfa/kode-pemulihan', [MfaSetupController::class, 'recoveryCodes'])->name('mfa.recovery-codes');
        Route::post('/akun/mfa/kode-pemulihan', [MfaSetupController::class, 'regenerateRecoveryCodes'])
            ->middleware(['reauth', 'throttle:5,1'])->name('mfa.recovery-codes.regenerate');

        Route::get('/akun/keamanan', [AccountSecurityController::class, 'show'])->name('account.security');
        Route::post('/akun/keamanan/kata-sandi', [AccountSecurityController::class, 'updatePassword'])
            ->middleware('throttle:5,1')->name('account.password.update');
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

        // Master data admin platform (docs/08 ADM-06, ADM-07). Aksi sensitif memakai `reauth`.
        Route::prefix('admin')->name('admin.')->middleware('workspace:admin')->group(function (): void {
            Route::controller(OrganizationAdminController::class)->prefix('organisasi')->name('organizations.')->group(function (): void {
                Route::get('/', 'index')->middleware('can:organization.view_any')->name('index');
                Route::get('/baru', 'create')->middleware('can:organization.create')->name('create');
                Route::post('/', 'store')->middleware('can:organization.create')->name('store');
                Route::get('/{organization}', 'show')->middleware('can:organization.view')->name('show');
                Route::put('/{organization}', 'update')->middleware('can:organization.update')->name('update');
                Route::post('/{organization}/status', 'toggleStatus')->middleware(['can:organization.archive', 'reauth'])->name('status');
                Route::post('/{organization}/domain', 'addDomain')->middleware('can:organization.update')->name('domains.store');
                Route::delete('/{organization}/domain/{domain}', 'removeDomain')->middleware('can:organization.update')->name('domains.destroy');
            });

            Route::controller(UserAdminController::class)->prefix('pengguna')->name('users.')->group(function (): void {
                Route::get('/', 'index')->middleware('can:user.view_any')->name('index');
                Route::get('/undang', 'create')->middleware(['can:user.create', 'can:user.assign_role'])->name('create');
                Route::post('/', 'store')->middleware(['can:user.create', 'can:user.assign_role', 'throttle:30,1'])->name('store');
                Route::get('/{user}', 'show')->middleware('can:user.view')->name('show');
                Route::put('/{user}', 'update')->middleware('can:user.update')->name('update');
                Route::post('/{user}/undangan', 'resendInvitation')->middleware(['can:user.create', 'throttle:10,1'])->name('invitation');
                Route::post('/{user}/status', 'toggleStatus')->middleware(['can:user.deactivate', 'reauth'])->name('status');
                Route::post('/{user}/peran', 'assignRole')->middleware(['can:user.assign_role', 'reauth'])->name('roles.store');
                Route::delete('/{user}/peran/{assignment}', 'revokeRole')->middleware(['can:user.assign_role', 'reauth'])->name('roles.destroy');
                Route::post('/{user}/reset-mfa', 'resetMfa')->middleware(['can:user.reset_mfa', 'reauth'])->name('mfa-reset');
            });
        });
    });
});
