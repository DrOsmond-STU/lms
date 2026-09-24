<?php

declare(strict_types=1);

use App\Modules\Access\Models\ApprovalRequest;
use App\Modules\Access\RoleCode;
use App\Modules\Certification\Models\Certificate;
use App\Modules\Certification\Services\CertificateSigner;
use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    cache()->flush();
    Storage::fake('local');
    app(CertificateSigner::class)->generate('STU Test Signer', 'STU');
    $this->artisan('stu:certificate-defaults')->assertSuccessful();
});

/** Peserta menyelesaikan kelas tanpa ujian akhir → pending_approval. Mengembalikan [course, enrollment, participant]. */
function graduate(): array
{
    $course = makeCourse(['final' => false]);
    $participant = signIn(RoleCode::Participant);
    $enrollment = enrollVia($course['class'], $participant);
    completeLessons($enrollment, $course['lessons']);
    test()->post('/keluar');
    nextRequest();

    return [$course, asSystem(fn () => $enrollment->fresh()), $participant];
}

function approveAs(RoleCode $role, Enrollment $enrollment): void
{
    signIn($role);
    confirmAccess();
    test()->post(route('admin.approvals.approve', $enrollment));
}

it('issues a numbered, signed certificate after approval by an eligible admin', function () {
    [$course, $enrollment, $participant] = graduate();
    expect($enrollment->status)->toBe('pending_approval');

    approveAs(RoleCode::AcademicAdmin, $enrollment);

    $certificate = asSystem(fn () => Certificate::query()->where('enrollment_id', $enrollment->id)->firstOrFail());
    $year = now()->timezone('Asia/Jakarta')->format('Y');
    $orgCode = asSystem(fn () => DB::table('organizations')->where('id', $enrollment->organization_id)->value('code')) ?? 'STU';
    expect($certificate->number)->toBe("INT/{$course['program']->short_code}/{$orgCode}/{$year}/00001")
        ->and($certificate->verification_code)->toMatch('/^[0-9A-HJKMNP-TV-Z]{12}$/')
        ->and($certificate->status)->toBe('active')
        ->and($certificate->pdf_sha256)->toHaveLength(64)
        ->and($certificate->signature_cert_fingerprint)->not->toBeNull()
        ->and(asSystem(fn () => $enrollment->fresh()->status))->toBe('passed');

    $pdf = (string) Storage::disk('local')->get((string) asSystem(fn () => DB::table('certificates')->where('id', $certificate->id)->value('pdf_storage_key')));
    expect(hash('sha256', $pdf))->toBe($certificate->pdf_sha256)
        ->and($pdf)->toStartWith('%PDF')
        ->and($pdf)->toContain('/ByteRange')->toContain('adbe.pkcs7.detached');
})->group('FR-CERT-004', 'FR-CERT-005');

it('forbids the class trainer from approving certificates of their own class', function () {
    [$course, $enrollment] = graduate();
    $admin = makeUser(RoleCode::AcademicAdmin);
    asSystem(fn () => DB::table('class_trainers')->insert(['course_class_id' => $course['class']->id, 'user_id' => $admin->id, 'role' => 'lead']));
    loginAs($admin, enrollTotp($admin));
    nextRequest();
    confirmAccess();

    $this->post(route('admin.approvals.approve', $enrollment))->assertSessionHasErrors('enrollment');
    expect(asSystem(fn () => $enrollment->fresh()->status))->toBe('pending_approval');
})->group('SEC-CERT', 'docs/07 §3');

it('numbers certificates sequentially per program and year without gaps', function () {
    $course = makeCourse(['final' => false]);
    $numbers = [];
    foreach (range(1, 2) as $i) {
        $participant = signIn(RoleCode::Participant);
        $enrollment = enrollVia($course['class'], $participant);
        completeLessons($enrollment, $course['lessons']);
        $this->post('/keluar');
        nextRequest();
        approveAs(RoleCode::SuperAdmin, $enrollment);
        $this->post('/keluar');
        nextRequest();
        $numbers[] = asSystem(fn () => Certificate::query()->where('enrollment_id', $enrollment->id)->value('number'));
    }

    expect($numbers[0])->toEndWith('/00001')->and($numbers[1])->toEndWith('/00002');
})->group('SEC-CERT-19');

it('lets only the holder download the PDF through a short-lived signed URL', function () {
    [, $enrollment, $participant] = graduate();
    approveAs(RoleCode::AcademicAdmin, $enrollment);
    $this->post('/keluar');
    nextRequest();
    $certificate = asSystem(fn () => Certificate::query()->where('enrollment_id', $enrollment->id)->firstOrFail());

    loginAs($participant);
    nextRequest();
    $redirect = $this->get(route('certificates.download', $certificate))->assertRedirect()->headers->get('Location');
    $this->get((string) $redirect)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->post('/keluar');
    nextRequest();

    signIn(RoleCode::Participant);
    $this->get(route('certificates.download', $certificate))->assertNotFound();
    $this->get((string) $redirect)->assertNotFound();
})->group('FR-CERT-006');

it('verifies publicly with minimal data and masks names looked up by number', function () {
    [, $enrollment, $participant] = graduate();
    approveAs(RoleCode::AcademicAdmin, $enrollment);
    $this->post('/keluar');
    nextRequest();
    $certificate = asSystem(fn () => Certificate::query()->where('enrollment_id', $enrollment->id)->firstOrFail());

    $this->get('/verifikasi/'.$certificate->formattedCode())->assertOk()->assertSee('Valid')->assertSee($participant->name)
        ->assertDontSee($participant->email)->assertHeader('Referrer-Policy', 'no-referrer');
    $this->post('/verifikasi', ['mode' => 'number', 'value' => strtolower($certificate->number)])->assertOk()
        ->assertSee($certificate->holder_name_masked)->assertDontSee($participant->name);
    $this->post('/verifikasi', ['mode' => 'number', 'value' => $certificate->number, 'full_name' => mb_strtoupper($participant->name)])->assertOk()->assertSee($participant->name);
    $this->post('/verifikasi', ['mode' => 'code', 'value' => 'ZZZZ-ZZZZ-ZZZZ'])->assertOk()->assertSee('Tidak ditemukan');

    $this->getJson('/api/v1/certificates/verify/'.$certificate->verification_code)->assertOk()->assertJsonPath('data.status', 'valid')->assertJsonMissingPath('data.email');
    $this->getJson('/api/v1/certificates/verify/ZZZZZZZZZZZZ')->assertNotFound()->assertExactJson(['data' => ['status' => 'not_found']]);
    expect(DB::table('certificate_verification_logs')->whereNotNull('ip_hash')->count())->toBeGreaterThan(0)
        ->and(DB::table('certificate_verification_logs')->where('ip_hash', '127.0.0.1')->exists())->toBeFalse();
})->group('FR-CERT-007', 'FR-API-001', 'SEC-CERT-11');

it('rate-limits public verification per IP', function () {
    foreach (range(1, 10) as $i) {
        $this->get('/verifikasi/ZZZZZZZZZZZZ')->assertOk();
    }
    $this->get('/verifikasi/ZZZZZZZZZZZZ')->assertStatus(429);
})->group('SEC-CERT-12');

it('revokes only after a second, different admin approves', function () {
    [, $enrollment] = graduate();
    approveAs(RoleCode::AcademicAdmin, $enrollment);
    $certificate = asSystem(fn () => Certificate::query()->where('enrollment_id', $enrollment->id)->firstOrFail());
    confirmAccess();
    $this->post(route('admin.certificates.revoke', $certificate), ['reason_code' => 'data_error', 'reason' => 'Nama pada sertifikat salah ketik'])->assertRedirect();
    $request = ApprovalRequest::query()->where('subject_id', $certificate->id)->firstOrFail();

    // Pengaju tidak dapat memutus sendiri.
    confirmAccess();
    $this->post(route('admin.second-approvals.decide', $request), ['decision' => 'approve'])->assertSessionHasErrors('decision');
    expect(asSystem(fn () => $certificate->fresh()->status))->toBe('active');
    $this->post('/keluar');
    nextRequest();

    signIn(RoleCode::SuperAdmin);
    confirmAccess();
    $this->post(route('admin.second-approvals.decide', $request), ['decision' => 'approve'])->assertRedirect();
    expect(asSystem(fn () => $certificate->fresh()->status))->toBe('revoked');
    $this->post('/keluar');
    nextRequest();
    $this->get('/verifikasi/'.$certificate->verification_code)->assertSee('Dicabut');
})->group('FR-CERT-009', 'SEC-AUTHZ-15');

it('rejects unknown placeholders in certificate templates', function () {
    signIn(RoleCode::AcademicAdmin);
    $this->post(route('admin.templates.store'), [
        'name' => 'Uji', 'category' => 'international', 'title_text' => 'Sertifikat', 'body_text' => 'Halo {nama} {email}',
        'signatory_name' => 'A', 'signatory_title' => 'B', 'accent_color' => '#0e3a63',
    ])->assertSessionHasErrors('body_text');
})->group('SEC-CERT-04');
