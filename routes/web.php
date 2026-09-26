<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LegalController;
use App\Modules\Access\Http\Controllers\ApprovalRequestController;
use App\Modules\Assessment\Http\Controllers\AssessmentManageController;
use App\Modules\Assessment\Http\Controllers\ExamController;
use App\Modules\Assessment\Http\Controllers\QuestionBankController;
use App\Modules\Audit\Http\Controllers\AuditLogController;
use App\Modules\Catalog\Http\Controllers\CatalogController;
use App\Modules\Catalog\Http\Controllers\ProgramAdminController;
use App\Modules\Certification\Http\Controllers\CertificateAdminController;
use App\Modules\Certification\Http\Controllers\CertificateApprovalController;
use App\Modules\Certification\Http\Controllers\CertificateTemplateController;
use App\Modules\Certification\Http\Controllers\MyCertificatesController;
use App\Modules\Certification\Http\Controllers\VerificationController;
use App\Modules\Cms\Http\Controllers\LandingController;
use App\Modules\Cms\Http\Controllers\LandingImageController;
use App\Modules\Cms\Http\Controllers\LandingPartnerController;
use App\Modules\Cms\Http\Controllers\LandingSlideController;
use App\Modules\Cms\Http\Controllers\LandingTestimonialController;
use App\Modules\Cms\Http\Controllers\SiteProfileController;
use App\Modules\Enrollment\Http\Controllers\LearningController;
use App\Modules\Identity\Http\Controllers\AccountController;
use App\Modules\Identity\Http\Controllers\AccountSecurityController;
use App\Modules\Identity\Http\Controllers\ConfirmAccessController;
use App\Modules\Identity\Http\Controllers\InvitationController;
use App\Modules\Identity\Http\Controllers\LoginController;
use App\Modules\Identity\Http\Controllers\MfaChallengeController;
use App\Modules\Identity\Http\Controllers\MfaSetupController;
use App\Modules\Identity\Http\Controllers\PasswordResetController;
use App\Modules\Identity\Http\Controllers\RegistrationController;
use App\Modules\Identity\Http\Controllers\UserAdminController;
use App\Modules\Learning\Http\Controllers\AcademicCalendarController;
use App\Modules\Learning\Http\Controllers\ClassAdminController;
use App\Modules\Learning\Http\Controllers\ClassListController;
use App\Modules\Learning\Http\Controllers\ClassManageController;
use App\Modules\Learning\Http\Controllers\ClassSessionController;
use App\Modules\Learning\Http\Controllers\ContentController;
use App\Modules\Learning\Http\Controllers\MediaStreamController;
use App\Modules\Learning\Http\Controllers\ScheduleController;
use App\Modules\Notification\Http\Controllers\NotificationController;
use App\Modules\Organization\Http\Controllers\OrganizationAdminController;
use App\Modules\Organization\Http\Controllers\OrgPortalController;
use App\Modules\Payment\Http\Controllers\PaymentAdminController;
use App\Modules\Payment\Http\Controllers\PaymentController;
use App\Modules\Referral\Http\Controllers\ReferralController;
use App\Modules\Reporting\Http\Controllers\ReportController;
use App\Modules\Settings\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute web — URL halaman berbahasa Indonesia (docs/06 §5).
| Semua rute di luar grup `guest`/publik WAJIB auth + mfa (deny by default).
|--------------------------------------------------------------------------
*/

Route::get('/', [LandingController::class, 'show'])->name('home');
Route::get('/beranda/gambar/slide/{slide}', [LandingImageController::class, 'slide'])->middleware('throttle:240,1,landing-image')->name('landing.image.slide');
Route::get('/beranda/gambar/mitra/{partner}', [LandingImageController::class, 'partner'])->middleware('throttle:240,1,landing-image')->name('landing.image.partner');
Route::get('/syarat-ketentuan', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/kebijakan-privasi', [LegalController::class, 'privacy'])->name('legal.privacy');

// Katalog publik & verifikasi sertifikat (docs/08 BARU-13/14, PUB-06, BARU-19).
Route::get('/program', [CatalogController::class, 'publicIndex'])->name('catalog.public');
Route::get('/program/{slug}', [CatalogController::class, 'publicShow'])->where('slug', '[a-z0-9-]{1,220}')->name('catalog.public.show');
Route::get('/verifikasi', [VerificationController::class, 'form'])->name('verification.form');
Route::post('/verifikasi', [VerificationController::class, 'lookup'])->middleware('throttle:verification')->name('verification.lookup');
Route::get('/verifikasi/{code}', [VerificationController::class, 'show'])->where('code', '[A-Za-z0-9-]{12,16}')->middleware('throttle:verification')->name('verification.show');
Route::permanentRedirect('/cek-sertifikat', '/verifikasi');

Route::middleware('guest')->group(function (): void {
    Route::get('/masuk', [LoginController::class, 'show'])->name('login');
    Route::post('/masuk', [LoginController::class, 'store'])->name('login.store');

    Route::get('/masuk/mfa', [MfaChallengeController::class, 'show'])->name('mfa.challenge');
    Route::post('/masuk/mfa', [MfaChallengeController::class, 'store'])->middleware('throttle:10,1,mfa-challenge-store')->name('mfa.challenge.store');

    Route::get('/daftar', [RegistrationController::class, 'create'])->name('register');
    Route::post('/daftar', [RegistrationController::class, 'store'])->name('register.store');
    Route::get('/verifikasi-email', [RegistrationController::class, 'showVerify'])->name('register.verify');
    Route::post('/verifikasi-email', [RegistrationController::class, 'verify'])->middleware('throttle:10,1,register-verify-store')->name('register.verify.store');
    Route::post('/verifikasi-email/kirim-ulang', [RegistrationController::class, 'resend'])->middleware('throttle:5,1,register-resend')->name('register.resend');

    Route::get('/lupa-kata-sandi', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/lupa-kata-sandi', [PasswordResetController::class, 'email'])->middleware('throttle:10,1,password-email')->name('password.email');
    Route::get('/reset-kata-sandi/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-kata-sandi', [PasswordResetController::class, 'update'])->middleware('throttle:10,1,password-update')->name('password.update');

    Route::get('/undangan/{token}', [InvitationController::class, 'show'])->middleware('throttle:30,1,invitation-show')->name('invitation.show');
    Route::post('/undangan/{token}', [InvitationController::class, 'accept'])->middleware('throttle:10,1,invitation-accept')->name('invitation.accept');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/keluar', [LoginController::class, 'destroy'])->name('logout');

    // Pendaftaran MFA dapat diakses sebelum MFA terverifikasi (untuk peran wajib-MFA).
    Route::get('/akun/mfa/aktifkan', [MfaSetupController::class, 'show'])->name('mfa.setup');
    Route::post('/akun/mfa/aktifkan', [MfaSetupController::class, 'store'])->middleware('throttle:10,1,mfa-setup-store')->name('mfa.setup.store');

    Route::middleware(['mfa', 'consent'])->group(function (): void {
        Route::get('/persetujuan', [AccountController::class, 'showReconsent'])->name('consent.show');
        Route::post('/persetujuan', [AccountController::class, 'storeReconsent'])->name('consent.store');

        Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifikasi/tandai-semua', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::post('/notifikasi/{notification}', [NotificationController::class, 'open'])->name('notifications.open');

        Route::get('/akun/profil', [AccountController::class, 'profile'])->name('account.profile');
        Route::put('/akun/profil', [AccountController::class, 'updateProfile'])->middleware('throttle:10,1,account-profile-update')->name('account.profile.update');
        Route::get('/akun/keamanan/sesi', [AccountController::class, 'sessions'])->name('account.sessions');
        Route::post('/akun/keamanan/sesi/keluar-semua', [AccountController::class, 'revokeOtherSessions'])->middleware('reauth')->name('account.sessions.revoke-others');
        Route::post('/akun/keamanan/sesi/{session}', [AccountController::class, 'revokeSession'])->name('account.sessions.revoke');
        Route::get('/akun/privasi', [AccountController::class, 'privacy'])->name('account.privacy');
        Route::post('/akun/privasi', [AccountController::class, 'updatePrivacy'])->middleware('throttle:10,1,account-privacy-update')->name('account.privacy.update');

        // Media & berkas sertifikat — URL bertanda tangan berumur pendek (FR-CNT-004, FR-CERT-006).
        Route::get('/media/{media}', MediaStreamController::class)->middleware('signed')->name('media.stream');
        Route::get('/sertifikat/{certificate}/unduh', [MyCertificatesController::class, 'download'])->name('certificates.download');
        Route::get('/sertifikat/{certificate}/berkas', [MyCertificatesController::class, 'file'])->middleware('signed')->name('certificates.file');

        Route::get('/akun/mfa/kode-pemulihan', [MfaSetupController::class, 'recoveryCodes'])->name('mfa.recovery-codes');
        Route::post('/akun/mfa/kode-pemulihan', [MfaSetupController::class, 'regenerateRecoveryCodes'])
            ->middleware(['reauth', 'throttle:5,1,mfa-recovery-codes-regenerate'])->name('mfa.recovery-codes.regenerate');

        Route::get('/akun/keamanan', [AccountSecurityController::class, 'show'])->name('account.security');
        Route::post('/akun/keamanan/kata-sandi', [AccountSecurityController::class, 'updatePassword'])
            ->middleware('throttle:5,1,account-password-update')->name('account.password.update');
        Route::get('/konfirmasi-akses', [ConfirmAccessController::class, 'show'])->name('password.confirm');
        Route::post('/konfirmasi-akses', [ConfirmAccessController::class, 'store'])->middleware('throttle:10,1,password-confirm-store')->name('password.confirm.store');

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
                Route::post('/', 'store')->middleware(['can:user.create', 'can:user.assign_role', 'throttle:30,1,store'])->name('store');
                Route::get('/{user}', 'show')->middleware('can:user.view')->name('show');
                Route::put('/{user}', 'update')->middleware('can:user.update')->name('update');
                Route::post('/{user}/undangan', 'resendInvitation')->middleware(['can:user.create', 'throttle:10,1,invitation'])->name('invitation');
                Route::post('/{user}/status', 'toggleStatus')->middleware(['can:user.deactivate', 'reauth'])->name('status');
                Route::post('/{user}/peran', 'assignRole')->middleware(['can:user.assign_role', 'reauth'])->name('roles.store');
                Route::delete('/{user}/peran/{assignment}', 'revokeRole')->middleware(['can:user.assign_role', 'reauth'])->name('roles.destroy');
                Route::post('/{user}/reset-mfa', 'resetMfa')->middleware(['can:user.reset_mfa', 'reauth'])->name('mfa-reset');
                Route::post('/{user}/super-admin', 'requestSuperAdmin')->middleware(['can:user.assign_role', 'reauth'])->name('super-admin');
            });

            Route::controller(ProgramAdminController::class)->prefix('program')->name('programs.')->group(function (): void {
                Route::get('/', 'index')->middleware('can:program.view_any')->name('index');
                Route::get('/baru', 'create')->middleware('can:program.create')->name('create');
                Route::post('/', 'store')->middleware('can:program.create')->name('store');
                Route::get('/{program}', 'show')->middleware('can:program.view')->name('show');
                Route::get('/{program}/ubah', 'edit')->middleware('can:program.update')->name('edit');
                Route::put('/{program}', 'update')->middleware('can:program.update')->name('update');
                Route::post('/{program}/ajukan', 'submit')->middleware('can:program.submit_review')->name('submit');
                Route::post('/{program}/terbitkan', 'publish')->middleware(['can:program.publish', 'reauth'])->name('publish');
                Route::post('/{program}/kembalikan', 'reject')->middleware('can:program.publish')->name('reject');
                Route::post('/{program}/arsip', 'archive')->middleware(['can:program.archive', 'reauth'])->name('archive');
            });

            Route::get('/kelas', [ClassListController::class, 'admin'])->middleware('can:course_class.view_any')->name('classes.index');
            Route::controller(ClassAdminController::class)->name('classes.')->group(function (): void {
                Route::get('/program/{program}/kelas/baru', 'create')->middleware('can:course_class.create')->name('create');
                Route::post('/program/{program}/kelas', 'store')->middleware('can:course_class.create')->name('store');
                Route::get('/kelas/{class}/pengaturan', 'edit')->middleware('can:course_class.update')->name('edit');
                Route::put('/kelas/{class}', 'update')->middleware('can:course_class.update')->name('update');
                Route::post('/kelas/{class}/status', 'changeStatus')->middleware('can:course_class.update')->name('status');
                Route::post('/kelas/{class}/trainer', 'addTrainer')->middleware('can:course_class.assign_trainer')->name('trainers.store');
                Route::delete('/kelas/{class}/trainer/{user}', 'removeTrainer')->middleware('can:course_class.assign_trainer')->name('trainers.destroy');
                Route::post('/kelas/{class}/peserta', 'enrollParticipant')->middleware(['can:enrollment.create', 'throttle:30,1,enroll'])->name('enroll');
            });

            Route::controller(CertificateApprovalController::class)->prefix('approval-sertifikat')->name('approvals.')->group(function (): void {
                Route::get('/', 'index')->middleware('can:certificate.approve')->name('index');
                Route::get('/{enrollment}', 'show')->middleware('can:certificate.approve')->name('show');
                Route::post('/{enrollment}/setujui', 'approve')->middleware(['can:certificate.approve', 'reauth'])->name('approve');
                Route::post('/{enrollment}/tolak', 'reject')->middleware(['can:certificate.reject', 'reauth'])->name('reject');
            });

            Route::controller(AcademicCalendarController::class)->prefix('kalender')->name('calendar.')->middleware('can:calendar.manage')->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->middleware('throttle:30,1,calendar-store')->name('store');
                Route::put('/{event}', 'update')->middleware('throttle:30,1,calendar-update')->name('update');
                Route::delete('/{event}', 'destroy')->name('destroy');
            });

            Route::controller(ReportController::class)->prefix('laporan')->name('reports.')->group(function (): void {
                Route::get('/organisasi', 'organizations')->middleware('can:report.view_platform')->name('organizations');
                Route::get('/organisasi/ekspor', 'organizationsExport')->middleware(['can:report.view_platform', 'can:report.export', 'throttle:5,1,report-org-export'])->name('organizations.export');
                Route::get('/organisasi/{organization}', 'organizationShow')->middleware('can:report.view_platform')->name('organizations.show');
                Route::get('/referral', 'referral')->middleware('can:referral.view_any')->name('referral');
                Route::get('/referral/ekspor', 'referralExport')->middleware(['can:referral.view_any', 'can:report.export', 'throttle:5,1,report-ref-export'])->name('referral.export');
                Route::get('/referral/{user}', 'referralShow')->middleware('can:referral.view_any')->name('referral.show');
                Route::post('/referral/{user}/bayar', 'payout')->middleware(['can:referral.pay', 'reauth', 'throttle:20,1,referral-payout'])->name('referral.payout');
                Route::post('/referral/komisi/{commission}/batal', 'voidCommission')->middleware(['can:referral.pay', 'reauth', 'throttle:20,1,referral-void'])->name('referral.void');
            });

            Route::controller(PaymentAdminController::class)->prefix('pembayaran')->name('payments.')->group(function (): void {
                Route::get('/', 'index')->middleware('can:payment.view_any')->name('index');
                Route::get('/{transaction}', 'show')->middleware('can:payment.view_any')->name('show');
                Route::get('/{transaction}/bukti', 'proof')->middleware('can:payment.view_any')->name('proof');
                Route::get('/{transaction}/invoice', 'invoice')->middleware(['can:payment.view_any', 'throttle:20,1,payment-invoice-admin'])->name('invoice');
                Route::post('/{transaction}/lunas', 'settle')->middleware(['can:payment.mark_paid_manual', 'reauth', 'throttle:30,1,payment-settle'])->name('settle');
                Route::post('/{transaction}/tolak-bukti', 'rejectProof')->middleware(['can:payment.mark_paid_manual', 'reauth', 'throttle:30,1,payment-reject'])->name('reject-proof');
                Route::post('/{transaction}/batalkan', 'fail')->middleware(['can:payment.mark_paid_manual', 'reauth', 'throttle:30,1,payment-fail'])->name('fail');
            });

            Route::controller(CertificateAdminController::class)->prefix('sertifikat')->name('certificates.')->group(function (): void {
                Route::get('/', 'index')->middleware('can:certificate.view_any')->name('index');
                Route::get('/ekspor', 'export')->middleware(['can:certificate.view_any', 'can:report.export', 'throttle:5,1,export'])->name('export');
                Route::get('/{certificate}', 'show')->middleware('can:certificate.view_any')->name('show');
                Route::post('/{certificate}/cabut', 'requestRevoke')->middleware(['can:certificate.revoke', 'reauth'])->name('revoke');
            });

            Route::controller(CertificateTemplateController::class)->prefix('template-sertifikat')->name('templates.')->group(function (): void {
                Route::get('/', 'index')->middleware('can:certificate_template.view_any')->name('index');
                Route::get('/baru', 'create')->middleware('can:certificate_template.create')->name('create');
                Route::post('/', 'store')->middleware('can:certificate_template.create')->name('store');
                Route::get('/{template}/ubah', 'edit')->middleware('can:certificate_template.update')->name('edit');
                Route::put('/{template}', 'update')->middleware('can:certificate_template.update')->name('update');
                Route::delete('/{template}', 'destroy')->middleware('can:certificate_template.update')->name('destroy');
                Route::get('/{template}/pratinjau', 'preview')->middleware(['can:certificate_template.view_any', 'throttle:10,1,preview'])->name('preview');
                Route::post('/{template}/aktivasi', 'requestActivation')->middleware(['can:certificate_template.activate', 'reauth'])->name('activate');
            });

            Route::get('/persetujuan', [ApprovalRequestController::class, 'index'])->name('second-approvals.index');
            Route::post('/persetujuan/{approval}', [ApprovalRequestController::class, 'decide'])->middleware('reauth')->name('second-approvals.decide');

            Route::get('/audit', [AuditLogController::class, 'index'])->middleware('can:audit_log.view')->name('audit.index');
            Route::get('/audit/ekspor', [AuditLogController::class, 'export'])->middleware(['can:audit_log.export', 'throttle:5,1,audit-export'])->name('audit.export');

            // Konten beranda publik (CMS ringan).
            Route::prefix('beranda')->name('landing.')->group(function (): void {
                foreach ([
                    ['slide', 'slides', 'slide', LandingSlideController::class],
                    ['testimoni', 'testimonials', 'testimonial', LandingTestimonialController::class],
                    ['mitra', 'partners', 'partner', LandingPartnerController::class],
                ] as [$path, $name, $param, $controller]) {
                    Route::controller($controller)->prefix($path)->name($name.'.')->group(function () use ($param, $name): void {
                        Route::get('/', 'index')->middleware('can:cms.view')->name('index');
                        Route::get('/baru', 'create')->middleware('can:cms.update')->name('create');
                        Route::post('/', 'store')->middleware(['can:cms.update', 'throttle:30,1,cms-'.$name.'-store'])->name('store');
                        Route::get('/{'.$param.'}/ubah', 'edit')->middleware('can:cms.update')->name('edit');
                        Route::put('/{'.$param.'}', 'update')->middleware(['can:cms.update', 'throttle:30,1,cms-'.$name.'-update'])->name('update');
                        Route::delete('/{'.$param.'}', 'destroy')->middleware('can:cms.update')->name('destroy');
                    });
                }
            });

            // Pengaturan sistem bertab: identitas, profil pemilik, beranda, pendaftaran, pembelajaran,
            // sertifikat, keamanan. Tab teknis memerlukan re-autentikasi saat menyimpan.
            Route::get('/pengaturan', [SettingsController::class, 'index'])->name('settings.edit');
            Route::get('/pengaturan/pemilik', [SiteProfileController::class, 'edit'])->middleware('can:cms.view')->name('settings.owner');
            Route::put('/pengaturan/pemilik', [SiteProfileController::class, 'update'])->middleware(['can:cms.update', 'throttle:30,1,cms-profile-update'])->name('settings.owner.update');
            Route::get('/pengaturan/{tab}', [SettingsController::class, 'show'])->whereIn('tab', ['umum', 'beranda', 'pendaftaran', 'pembelajaran', 'sertifikat', 'pembayaran', 'legal', 'keamanan'])->name('settings.tab');
            Route::put('/pengaturan/beranda', [SettingsController::class, 'update'])->defaults('tab', 'beranda')->middleware(['can:cms.update', 'throttle:30,1,settings-landing'])->name('settings.landing.update');
            Route::put('/pengaturan/{tab}', [SettingsController::class, 'update'])->whereIn('tab', ['umum', 'pendaftaran', 'pembelajaran', 'sertifikat', 'pembayaran', 'legal', 'keamanan'])->middleware(['can:system_setting.update', 'reauth', 'throttle:30,1,settings-update'])->name('settings.update');
        });

        // Area peserta (docs/08 PST-*).
        Route::prefix('peserta')->middleware('workspace:participant')->group(function (): void {
            Route::get('/program', [CatalogController::class, 'participantIndex'])->name('catalog.participant');
            Route::get('/program/{slug}', [CatalogController::class, 'participantShow'])->where('slug', '[a-z0-9-]{1,220}')->name('catalog.participant.show');
            Route::post('/kelas/{class}/daftar', [CatalogController::class, 'enroll'])->middleware('throttle:10,1,catalog-enroll')->name('catalog.enroll');

            Route::get('/pembelajaran', [LearningController::class, 'index'])->name('learning.index');
            Route::get('/kelas/{enrollment}', [LearningController::class, 'classroom'])->name('learning.classroom');
            Route::post('/kelas/{enrollment}/batal', [LearningController::class, 'cancel'])->middleware('throttle:10,1,learning-cancel')->name('learning.cancel');
            Route::get('/kelas/{enrollment}/materi/{lesson}', [LearningController::class, 'lesson'])->name('learning.lesson');
            Route::post('/kelas/{enrollment}/materi/{lesson}/heartbeat', [LearningController::class, 'heartbeat'])->middleware('throttle:20,1,learning-heartbeat')->name('learning.heartbeat');
            Route::post('/kelas/{enrollment}/materi/{lesson}/selesai', [LearningController::class, 'complete'])->middleware('throttle:30,1,learning-complete')->name('learning.complete');
            Route::post('/kelas/{enrollment}/materi/{lesson}/ping', [LearningController::class, 'ping'])->middleware('throttle:10,1,learning-ping')->name('learning.ping');

            // Jadwal, sesi & presensi mandiri
            Route::get('/jadwal', [ScheduleController::class, 'participant'])->name('schedule.participant');
            Route::post('/kelas/{enrollment}/sesi/{session}/hadir', [ScheduleController::class, 'checkIn'])->middleware(['can:attendance.check_in', 'throttle:10,1,schedule-checkin'])->name('schedule.checkin');

            Route::get('/kelas/{enrollment}/asesmen/{assessment}', [ExamController::class, 'show'])->name('exams.show');
            Route::post('/kelas/{enrollment}/asesmen/{assessment}/mulai', [ExamController::class, 'start'])->middleware('throttle:10,1,exams-start')->name('exams.start');
            Route::get('/ujian/{attempt}', [ExamController::class, 'take'])->name('exams.take');
            Route::put('/ujian/{attempt}/jawaban', [ExamController::class, 'answer'])->middleware('throttle:60,1,exams-answer')->name('exams.answer');
            Route::post('/ujian/{attempt}/integritas', [ExamController::class, 'integrity'])->middleware('throttle:30,1,exams-integrity')->name('exams.integrity');
            Route::post('/ujian/{attempt}/kumpulkan', [ExamController::class, 'submit'])->middleware('throttle:10,1,exams-submit')->name('exams.submit');
            Route::get('/ujian/{attempt}/hasil', [ExamController::class, 'result'])->name('exams.result');

            Route::get('/sertifikat', [MyCertificatesController::class, 'index'])->name('certificates.mine');

            // Pembayaran transfer manual (FR-PAY tahap A)
            Route::post('/kelas/{class}/bayar', [PaymentController::class, 'checkout'])->middleware(['can:payment.view', 'throttle:10,1,payment-checkout'])->name('payments.checkout');
            Route::get('/transaksi', [PaymentController::class, 'index'])->middleware('can:payment.view')->name('payments.mine');
            Route::get('/pembayaran/{transaction}', [PaymentController::class, 'show'])->middleware('can:payment.view')->name('payments.show');
            Route::post('/pembayaran/{transaction}/bukti', [PaymentController::class, 'submitProof'])->middleware(['can:payment.view', 'throttle:10,1,payment-proof'])->name('payments.proof');
            Route::get('/pembayaran/{transaction}/bukti', [PaymentController::class, 'proof'])->middleware('can:payment.view')->name('payments.proof.view');
            Route::get('/pembayaran/{transaction}/invoice', [PaymentController::class, 'invoice'])->middleware(['can:payment.view', 'throttle:20,1,payment-invoice'])->name('payments.invoice');

            // Program referral
            Route::get('/referral', [ReferralController::class, 'show'])->middleware('can:referral.view')->name('referral.mine');
            Route::post('/referral/rekening', [ReferralController::class, 'updateAccount'])->middleware(['can:referral.view', 'throttle:10,1,referral-account'])->name('referral.account');
        });

        Route::get('/trainer/kelas', [ClassListController::class, 'trainer'])->middleware('workspace:trainer')->name('trainer.classes');
        Route::get('/trainer/jadwal', [ScheduleController::class, 'trainer'])->middleware('workspace:trainer')->name('schedule.trainer');

        // Portal Admin Organisasi (docs/08 ORG-*).
        Route::prefix('organisasi')->middleware('workspace:organization')->group(function (): void {
            Route::get('/anggota', [OrgPortalController::class, 'members'])->middleware('can:organization.manage_members')->name('org.members');
            Route::post('/anggota/{member}', [OrgPortalController::class, 'decide'])->middleware(['can:organization.manage_members', 'throttle:60,1,org-members-decide'])->name('org.members.decide');
            Route::get('/enrollment', [OrgPortalController::class, 'enrollments'])->middleware('can:report.view_organization')->name('org.enrollments');
            Route::get('/audit', [AuditLogController::class, 'index'])->middleware('can:audit_log.view')->name('org.audit');
            Route::get('/audit/ekspor', [AuditLogController::class, 'export'])->middleware(['can:audit_log.export', 'throttle:5,1,org-audit-export'])->name('org.audit.export');
        });

        // Kelola kelas bersama staf platform & trainer pengampu — lingkup dicek di controller (404).
        Route::prefix('kelola')->group(function (): void {
            Route::get('/kelas/{class}', [ClassManageController::class, 'show'])->name('classes.manage');
            Route::get('/kelas/{class}/peserta', [ClassManageController::class, 'participants'])->name('classes.participants');
            Route::post('/kelas/{class}/peserta/{enrollment}/batal', [ClassManageController::class, 'cancelEnrollment'])->middleware('throttle:30,1,class-cancel')->name('classes.enrollments.cancel');
            Route::get('/kelas/{class}/asesmen', [ClassManageController::class, 'assessments'])->name('classes.assessments');

            // Sesi kelas (tatap muka / live class) & presensi
            Route::controller(ClassSessionController::class)->prefix('kelas/{class}')->name('classes.')->group(function (): void {
                Route::get('/sesi', 'index')->name('sessions');
                Route::post('/sesi', 'store')->middleware('throttle:30,1,sessions-store')->name('sessions.store');
                Route::put('/sesi/{session}', 'update')->middleware('throttle:30,1,sessions-update')->name('sessions.update');
                Route::delete('/sesi/{session}', 'destroy')->name('sessions.destroy');
                Route::get('/sesi/{session}/presensi', 'attendance')->name('attendance');
                Route::post('/sesi/{session}/presensi', 'storeAttendance')->middleware('throttle:30,1,attendance-store')->name('attendance.store');
                Route::get('/presensi/ekspor', 'exportAttendance')->middleware('throttle:10,1,attendance-export')->name('attendance.export');
            });

            Route::controller(ContentController::class)->prefix('kelas/{class}')->name('content.')->group(function (): void {
                Route::post('/modul', 'storeModule')->name('modules.store');
                Route::put('/modul/{module}', 'updateModule')->name('modules.update');
                Route::post('/modul/{module}/geser', 'moveModule')->name('modules.move');
                Route::delete('/modul/{module}', 'destroyModule')->name('modules.destroy');
                Route::post('/modul/{module}/bab', 'storeChapter')->name('chapters.store');
                Route::put('/bab/{chapter}', 'updateChapter')->name('chapters.update');
                Route::post('/bab/{chapter}/geser', 'moveChapter')->name('chapters.move');
                Route::delete('/bab/{chapter}', 'destroyChapter')->name('chapters.destroy');
                Route::get('/bab/{chapter}/lesson/baru', 'createLesson')->name('lessons.create');
                Route::post('/bab/{chapter}/lesson', 'storeLesson')->middleware('throttle:30,1,lessons-store')->name('lessons.store');
                Route::get('/lesson/{lesson}/ubah', 'editLesson')->name('lessons.edit');
                Route::put('/lesson/{lesson}', 'updateLesson')->middleware('throttle:30,1,lessons-update')->name('lessons.update');
                Route::post('/lesson/{lesson}/geser', 'moveLesson')->name('lessons.move');
                Route::delete('/lesson/{lesson}', 'destroyLesson')->name('lessons.destroy');
            });

            Route::controller(AssessmentManageController::class)->prefix('kelas/{class}')->name('assessments.')->group(function (): void {
                Route::get('/asesmen/baru', 'create')->name('create');
                Route::post('/asesmen', 'store')->name('store');
                Route::get('/asesmen/{assessment}/ubah', 'edit')->name('edit');
                Route::put('/asesmen/{assessment}', 'update')->name('update');
                Route::get('/asesmen/{assessment}/attempt', 'attempts')->name('attempts');
                Route::post('/asesmen/{assessment}/kesempatan', 'grant')->middleware('throttle:30,1,grant')->name('grant');
                Route::delete('/asesmen/{assessment}', 'destroy')->name('destroy');
                Route::get('/attempt/{attempt}/nilai', 'gradeForm')->name('grade');
                Route::post('/attempt/{attempt}/nilai', 'grade')->name('grade.store');
                Route::post('/attempt/{attempt}/batalkan', 'void')->name('void');
            });

            Route::controller(QuestionBankController::class)->group(function (): void {
                Route::get('/program/{program}/bank-soal', 'index')->name('banks.index');
                Route::post('/program/{program}/bank-soal', 'store')->name('banks.store');
                Route::get('/bank-soal/{bank}', 'show')->name('banks.show');
                Route::put('/bank-soal/{bank}', 'updateBank')->name('banks.update');
                Route::delete('/bank-soal/{bank}', 'destroyBank')->name('banks.destroy');
                Route::get('/bank-soal/{bank}/soal/baru', 'create')->name('questions.create');
                Route::post('/bank-soal/{bank}/soal', 'storeQuestion')->name('questions.store');
                Route::get('/soal/{question}/ubah', 'edit')->name('questions.edit');
                Route::put('/soal/{question}', 'update')->name('questions.update');
                Route::post('/soal/{question}/status', 'toggle')->name('questions.toggle');
                Route::delete('/soal/{question}', 'destroyQuestion')->name('questions.destroy');
            });
        });
    });
});
