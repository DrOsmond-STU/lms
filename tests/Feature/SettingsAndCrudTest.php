<?php

declare(strict_types=1);

use App\Modules\Access\RoleCode;
use App\Modules\Assessment\Models\Assessment;
use App\Modules\Assessment\Models\ExamAttempt;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\QuestionBank;
use App\Modules\Assessment\Services\AttemptService;
use App\Modules\Certification\Models\Certificate;
use App\Modules\Certification\Models\CertificateTemplate;
use App\Modules\Enrollment\Console\SimulateJourneyCommand;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\InvitationNotification;
use App\Modules\Identity\Services\OneTimeTokens;
use App\Modules\Settings\Services\SystemSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

// ---------------------------------------------------------------------------------- Pengaturan

it('drives brand, landing and certificate texts from system settings', function () {
    signIn(RoleCode::SuperAdmin);
    confirmAccess();

    $this->put(route('admin.settings.update', 'umum'), [
        'branding__app_display_name' => 'LMS Uji', 'branding__short_name' => 'Akademi Uji', 'branding__tagline' => 'Belajar Tanpa Batas',
        'branding__display_timezone' => 'Asia/Makassar', 'login__panel_title' => 'Selamat datang di Akademi Uji',
    ])->assertSessionHasNoErrors();
    $this->put(route('admin.settings.landing.update'), array_merge(
        collect(SystemSettings::forTab('beranda'))->mapWithKeys(fn ($d, $key) => [SystemSettings::field($key) => (string) SystemSettings::get($key)])->all(),
        ['landing__hero_title' => 'Judul Pembuka Dari Pengaturan', 'landing__feature1_title' => 'Keunggulan Dari Pengaturan'],
    ))->assertSessionHasNoErrors();

    SystemSettings::applyToConfig();
    expect(display_tz())->toBe('Asia/Makassar')->and(tz_label())->toBe('WITA');
    $this->get('/')->assertSee('Akademi Uji')->assertSee('Belajar Tanpa Batas')->assertSee('Judul Pembuka Dari Pengaturan')->assertSee('Keunggulan Dari Pengaturan');
    $this->post('/keluar');
    nextRequest();
    $this->get('/masuk')->assertSee('Selamat datang di Akademi Uji');
    expect(DB::table('audit_logs')->where('action', 'system_setting.updated')->count())->toBe(2);
})->group('FR-SET-001');

it('validates settings: required, pattern, select options, bounds', function () {
    signIn(RoleCode::SuperAdmin);
    confirmAccess();

    $this->put(route('admin.settings.update', 'umum'), ['branding__app_display_name' => 'X', 'branding__short_name' => '', 'branding__display_timezone' => 'Europe/London'])
        ->assertSessionHasErrors(['branding__short_name', 'branding__display_timezone']);
    $this->put(route('admin.settings.update', 'sertifikat'), ['certificate__issuer_code' => 'stu lower', 'certificate__expiry_reminder_days' => 60])
        ->assertSessionHasErrors('certificate__issuer_code');
    $this->put(route('admin.settings.update', 'pembelajaran'), ['learning__video_completion_percent' => 10, 'exam__grace_seconds' => 30, 'program__default_passing_score' => 70, 'program__default_validity_months' => 36])
        ->assertSessionHasErrors('learning__video_completion_percent');
})->group('FR-SET-002');

it('gives content admins the landing & owner tabs but not technical settings', function () {
    signIn(RoleCode::AcademicAdmin);

    $this->get(route('admin.settings.tab', 'beranda'))->assertOk();
    $this->get(route('admin.settings.owner'))->assertOk();
    $this->get(route('admin.settings.tab', 'keamanan'))->assertForbidden();
    $this->get(route('admin.settings.tab', 'umum'))->assertForbidden();
})->group('FR-SET-001', 'SEC-AUTHZ');

it('uses the certificate issuer code and exam grace period from settings', function () {
    DB::table('system_settings')->insert([
        ['key' => 'certificate.issuer_code', 'value' => json_encode('AKD'), 'updated_at' => now()],
        ['key' => 'exam.grace_seconds', 'value' => json_encode(10), 'updated_at' => now()],
    ]);
    Cache::forget('system_settings.v1');
    SystemSettings::applyToConfig();

    expect(config('lms.certificate_issuer_code'))->toBe('AKD')
        ->and(AttemptService::graceSeconds())->toBe(10);
})->group('FR-SET-001');

it('renders legal documents from settings as sanitized markdown', function () {
    DB::table('system_settings')->insert(['key' => 'legal.terms_body', 'value' => json_encode("## Pasal 1\n\nIsi **tebal** <script>alert(1)</script>"), 'updated_at' => now()]);
    Cache::forget('system_settings.v1');
    SystemSettings::applyToConfig();

    $this->get('/syarat-ketentuan')->assertOk()->assertSee('<h2>Pasal 1</h2>', false)->assertSee('<strong>tebal</strong>', false)->assertDontSee('<script>alert(1)</script>', false);
})->group('FR-CMS-003');

// ----------------------------------------------------------------------------------------- CRUD

it('renames and deletes an unused question bank but protects banks in use', function () {
    $course = makeCourse(['final' => true]);
    signIn(RoleCode::AcademicAdmin);

    $this->put(route('banks.update', $course['bank']), ['name' => 'Bank Soal Baru'])->assertRedirect();
    expect($course['bank']->fresh()->name)->toBe('Bank Soal Baru');
    $this->delete(route('banks.destroy', $course['bank']))->assertSessionHasErrors('bank');

    $empty = asSystem(function () use ($course) {
        $bank = new QuestionBank;
        $bank->forceFill(['program_id' => $course['program']->id, 'name' => 'Bank Kosong'])->save();

        return $bank;
    });
    $this->delete(route('banks.destroy', $empty))->assertRedirect(route('banks.index', $course['program']));
    expect(QuestionBank::query()->whereKey($empty->id)->exists())->toBeFalse();
})->group('CRUD');

it('deletes only questions and assessments that were never attempted', function () {
    $course = makeCourse(['final' => true]);
    $participant = makeUser(RoleCode::Participant);
    loginAs($participant);
    $enrollment = enrollVia($course['class'], $participant);
    completeLessons($enrollment, $course['lessons']);
    $this->post(route('exams.start', [$enrollment, $course['final']]))->assertRedirect();
    $attempt = asSystem(fn () => ExamAttempt::query()->where('enrollment_id', $enrollment->id)->firstOrFail());
    $this->post('/keluar');
    nextRequest();

    signIn(RoleCode::AcademicAdmin);
    $used = Question::query()->whereKey($attempt->question_order[0])->firstOrFail();
    $this->delete(route('questions.destroy', $used))->assertSessionHasErrors('question');
    $this->delete(route('assessments.destroy', [$course['class'], $course['final']]))->assertSessionHasErrors('assessment');

    $unusedQuiz = asSystem(function () use ($course) {
        $quiz = new Assessment;
        $quiz->forceFill(['course_class_id' => $course['class']->id, 'question_bank_id' => $course['bank']->id, 'kind' => 'quiz', 'title' => 'Kuis Tak Terpakai',
            'duration_minutes' => 10, 'max_attempts' => 1, 'question_count' => 1, 'passing_score' => 50, 'review_policy' => 'after_submit',
            'is_required' => false, 'requires_prerequisites' => false])->save();

        return $quiz;
    });
    $this->delete(route('assessments.destroy', [$course['class'], $unusedQuiz]))->assertRedirect(route('classes.assessments', $course['class']));
    expect(Assessment::query()->whereKey($unusedQuiz->id)->exists())->toBeFalse();
})->group('CRUD');

it('deletes an unused inactive certificate template but never an active one', function () {
    signIn(RoleCode::SuperAdmin);
    $active = CertificateTemplate::query()->where('is_active', true)->first() ?? tap(new CertificateTemplate, fn ($t) => $t->forceFill([
        'name' => 'Aktif', 'category' => 'international', 'version' => 1, 'title_text' => 'SERTIFIKAT', 'body_text' => 'Isi {nama}', 'signatory_name' => 'A', 'signatory_title' => 'B', 'accent_color' => '#0e3a63', 'is_active' => true,
    ])->save());
    $draft = new CertificateTemplate;
    $draft->forceFill(['name' => 'Draf', 'category' => 'international', 'version' => 99, 'title_text' => 'SERTIFIKAT', 'body_text' => 'Isi {nama}', 'signatory_name' => 'A', 'signatory_title' => 'B', 'accent_color' => '#0e3a63', 'is_active' => false])->save();

    $this->delete(route('admin.templates.destroy', $active))->assertSessionHasErrors('template');
    $this->delete(route('admin.templates.destroy', $draft))->assertRedirect(route('admin.templates.index'));
    expect(CertificateTemplate::query()->whereKey($draft->id)->exists())->toBeFalse();
})->group('CRUD');

it('lets participants cancel their own active enrollment and frees the seat', function () {
    $course = makeCourse(['quota' => 5]);
    $participant = makeUser(RoleCode::Participant);
    loginAs($participant);
    $enrollment = enrollVia($course['class'], $participant);
    expect($course['class']->fresh()->enrolled_count)->toBe(1);

    $this->post(route('learning.cancel', $enrollment))->assertRedirect(route('learning.index'));
    expect(asSystem(fn () => $enrollment->fresh()->status))->toBe('cancelled')
        ->and($course['class']->fresh()->enrolled_count)->toBe(0);
    $this->post(route('learning.cancel', $enrollment))->assertSessionHasErrors('enrollment');
})->group('CRUD', 'FR-ENR');

it('lets admins cancel an enrollment with a reason from the class roster', function () {
    $course = makeCourse();
    $participant = makeUser(RoleCode::Participant);
    loginAs($participant);
    $enrollment = enrollVia($course['class'], $participant);
    $this->post('/keluar');
    nextRequest();

    signIn(RoleCode::AcademicAdmin);
    $this->post(route('classes.enrollments.cancel', [$course['class'], $enrollment]), ['reason' => ''])->assertSessionHasErrors('reason');
    $this->post(route('classes.enrollments.cancel', [$course['class'], $enrollment]), ['reason' => 'Salah pilih batch'])->assertRedirect(route('classes.participants', $course['class']));
    expect(asSystem(fn () => $enrollment->fresh()->status))->toBe('cancelled');
})->group('CRUD', 'FR-ENR');

// ------------------------------------------------------------------------------------ Simulasi

it('simulates the full journey: join, learn, exam, certificate, public verification', function () {
    Storage::fake('local'); // PDF tanpa kunci penandatangan lokal (APP_KEY uji berbeda)
    $this->artisan('stu:certificate-defaults')->assertSuccessful();
    $this->artisan('stu:demo-content')->assertSuccessful();
    $this->artisan('stu:simulate')->assertSuccessful();

    $simulated = User::query()->where('email', 'like', '%@'.SimulateJourneyCommand::EMAIL_DOMAIN)->pluck('id');
    $certificates = asSystem(fn () => Certificate::query()->whereIn('user_id', $simulated)->where('status', 'active')->get());
    expect($certificates)->toHaveCount(5)
        ->and(asSystem(fn () => Enrollment::query()->whereIn('user_id', $simulated)->where('status', 'pending_approval')->count()))->toBe(1)
        ->and(asSystem(fn () => Enrollment::query()->whereIn('user_id', $simulated)->where('status', 'cancelled')->count()))->toBe(1);

    $this->get(route('verification.show', $certificates->first()->formattedCode()))->assertOk()->assertSee('Valid');
    $this->getJson('/api/v1/certificates/verify/'.$certificates->first()->verification_code)->assertOk()->assertJsonPath('data.status', 'valid');

    // Idempoten: dijalankan ulang tidak menggandakan data.
    $this->artisan('stu:simulate')->assertSuccessful();
    expect(asSystem(fn () => Certificate::query()->whereIn('user_id', $simulated)->count()))->toBe(5);
})->group('SIMULASI');

// ------------------------------------------------------------------ Konten & pesan validasi

it('renames a chapter from the class content page', function () {
    $course = makeCourse();
    signIn(RoleCode::AcademicAdmin);
    $chapter = $course['lessons'][0]->chapter;

    $this->get(route('classes.manage', $course['class']))->assertOk()->assertSee('Ganti nama bab');
    $this->put(route('content.chapters.update', [$course['class'], $chapter]), ['title' => 'Bab Hasil Ganti Nama'])
        ->assertSessionHasNoErrors()->assertSessionHas('status', 'Bab diperbarui.');
    $this->get(route('classes.manage', $course['class']))->assertSee('Bab Hasil Ganti Nama');
})->group('CRUD', 'FR-CNT');

it('shows validation messages in Indonesian instead of raw translation keys', function () {
    $course = makeCourse();
    signIn(RoleCode::AcademicAdmin);
    $chapter = $course['lessons'][0]->chapter;

    $this->from(route('classes.manage', $course['class']))
        ->put(route('content.chapters.update', [$course['class'], $chapter]), ['title' => ''])
        ->assertSessionHasErrors('title');
    $message = session('errors')->first('title');

    expect($message)->toContain('wajib diisi')->not->toContain('validation.');
})->group('CRUD', 'UX');

it('leaves the simulation admin account unable to log in after the run', function () {
    Storage::fake('local');
    $this->artisan('stu:certificate-defaults')->assertSuccessful();
    $this->artisan('stu:demo-content')->assertSuccessful();
    $this->artisan('stu:simulate')->assertSuccessful();

    $admin = User::query()->where('email', 'admin@'.SimulateJourneyCommand::EMAIL_DOMAIN)->firstOrFail();
    expect($admin->status)->toBe('deactivated');

    // Dijalankan ulang: diaktifkan sementara untuk menyetujui, lalu dinonaktifkan lagi.
    $this->artisan('stu:simulate')->assertSuccessful();
    expect($admin->fresh()->status)->toBe('deactivated');
})->group('SIMULASI', 'SEC-AUTH');

// ------------------------------------------------------------------------- Undangan via CLI

it('resends the invitation from the CLI only for accounts without a password', function () {
    Notification::fake();
    $this->artisan('stu:bootstrap-admin', ['--email' => 'admin.baru@contoh.test', '--name' => 'Admin Baru'])->assertSuccessful();
    $admin = User::query()->where('email', 'admin.baru@contoh.test')->firstOrFail();
    // Skenario staging: akun sempat berstatus active tanpa kata sandi.
    asSystem(fn () => $admin->forceFill(['status' => 'active'])->save());

    $this->artisan('stu:resend-invitation', ['email' => 'admin.baru@contoh.test'])->assertSuccessful();

    Notification::assertSentToTimes($admin, InvitationNotification::class, 2);
    expect($admin->fresh()->status)->toBe('pending_verification')
        ->and(DB::table('one_time_tokens')->where('user_id', $admin->id)->where('purpose', OneTimeTokens::PURPOSE_INVITATION)->whereNull('consumed_at')->count())->toBeGreaterThanOrEqual(1)
        ->and(DB::table('audit_logs')->where('action', 'user.invitation_resent')->count())->toBe(1);

    // Akun yang sudah punya kata sandi ditolak.
    $withPassword = makeUser(RoleCode::Participant);
    $this->artisan('stu:resend-invitation', ['email' => $withPassword->email])->assertFailed();
    $this->artisan('stu:resend-invitation', ['email' => 'tidak.ada@contoh.test'])->assertFailed();
})->group('SEC-AUTH', 'CLI');
