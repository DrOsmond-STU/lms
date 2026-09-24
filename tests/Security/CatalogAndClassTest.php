<?php

declare(strict_types=1);

use App\Modules\Access\RoleCode;
use App\Modules\Catalog\Models\Program;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => cache()->flush());

function programPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'AWS Cloud Practitioner', 'category' => 'international', 'provider_name' => 'Mitra Uji', 'short_code' => 'aws-ccp',
        'duration_hours' => 20, 'language' => 'id', 'default_mode' => 'online', 'passing_score' => 70,
        'certificate_validity_months' => 36, 'price' => 0, 'description_md' => 'Halo **dunia** <script>alert(1)</script> [x](javascript:alert(1))',
    ], $overrides);
}

it('requires a different admin to publish a program than the one who submitted it', function () {
    $author = signIn(RoleCode::AcademicAdmin);
    $this->post('/admin/program', programPayload())->assertRedirect();
    $program = Program::query()->where('short_code', 'AWS-CCP')->firstOrFail();
    expect($program->status)->toBe('draft');

    $this->post("/admin/program/{$program->id}/ajukan")->assertRedirect();
    confirmAccess();
    $this->post("/admin/program/{$program->id}/terbitkan")->assertSessionHasErrors('status');
    expect($program->fresh()->status)->toBe('in_review');

    // Basis data juga menolak reviewer = pengaju.
    expect(fn () => DB::transaction(fn () => DB::table('programs')->where('id', $program->id)->update(['reviewed_by' => $author->id])))
        ->toThrow(QueryException::class, 'programs_review_sod_check');

    $this->post('/keluar');
    nextRequest();
    signIn(RoleCode::SuperAdmin);
    confirmAccess();
    $this->post("/admin/program/{$program->id}/terbitkan")->assertRedirect();
    expect($program->fresh()->status)->toBe('published');
})->group('FR-CAT-002');

it('sanitizes rich text descriptions on save', function () {
    signIn(RoleCode::AcademicAdmin);
    $this->post('/admin/program', programPayload(['short_code' => 'SAN-1']));
    $html = (string) Program::query()->where('short_code', 'SAN-1')->value('description_html');

    expect($html)->toContain('<strong>dunia</strong>')
        ->not->toContain('<script')
        ->not->toContain('javascript:');
})->group('SEC-INPUT-01');

it('hides draft and archived programs from public and participant catalogs', function () {
    $published = makeCourse()['program'];
    $draft = makeCourse()['program'];
    asSystem(fn () => $draft->forceFill(['status' => 'draft'])->save());

    $this->get('/program')->assertOk()->assertSee($published->name)->assertDontSee($draft->name);
    $this->get('/program/'.$draft->slug)->assertNotFound();
    $this->get('/program/'.$published->slug)->assertOk();

    signIn(RoleCode::Participant);
    $this->get('/peserta/program/'.$draft->slug)->assertNotFound();
})->group('FR-CAT-003');

it('only allows valid class status transitions and opening for published programs', function () {
    signIn(RoleCode::AcademicAdmin);
    $course = makeCourse();
    $class = $course['class'];
    asSystem(fn () => $class->forceFill(['status' => 'draft'])->save());
    asSystem(fn () => $course['program']->forceFill(['status' => 'draft'])->save());

    $this->post("/admin/kelas/{$class->id}/status", ['status' => 'running'])->assertSessionHasErrors('status');
    $this->post("/admin/kelas/{$class->id}/status", ['status' => 'open'])->assertSessionHasErrors('status');
    asSystem(fn () => $course['program']->forceFill(['status' => 'published'])->save());
    $this->post("/admin/kelas/{$class->id}/status", ['status' => 'open'])->assertRedirect();
    expect($class->fresh()->status)->toBe('open');
})->group('FR-CLS-001');

it('never lets the database exceed the class quota', function () {
    $class = makeCourse(['quota' => 1])['class'];

    expect(fn () => asSystem(fn () => DB::transaction(fn () => DB::table('course_classes')->where('id', $class->id)->update(['enrolled_count' => 2]))))
        ->toThrow(QueryException::class, 'course_classes_enrolled_count_check');
})->group('FR-CLS-002');

it('lets only assigned trainers manage class content', function () {
    $course = makeCourse();
    $trainer = makeUser(RoleCode::Trainer);
    $other = makeUser(RoleCode::Trainer);
    asSystem(fn () => DB::table('class_trainers')->insert(['course_class_id' => $course['class']->id, 'user_id' => $trainer->id, 'role' => 'lead']));

    loginAs($other, enrollTotp($other));
    nextRequest();
    $this->get("/kelola/kelas/{$course['class']->id}")->assertNotFound();
    $this->post("/kelola/kelas/{$course['class']->id}/modul", ['title' => 'Modul Susupan'])->assertNotFound();

    $this->post('/keluar');
    nextRequest();
    loginAs($trainer, enrollTotp($trainer));
    nextRequest();
    $this->get("/kelola/kelas/{$course['class']->id}")->assertOk();
    $this->post("/kelola/kelas/{$course['class']->id}/modul", ['title' => 'Modul Baru'])->assertRedirect();
    expect(DB::table('modules')->where('course_class_id', $course['class']->id)->where('title', 'Modul Baru')->exists())->toBeTrue();
})->group('SEC-AUTHZ-03');

it('rejects external links outside the allowlist and non-https', function () {
    $course = makeCourse();
    signIn(RoleCode::AcademicAdmin);
    $chapterId = DB::table('chapters')->join('modules', 'modules.id', '=', 'chapters.module_id')->where('modules.course_class_id', $course['class']->id)->value('chapters.id');

    $this->post("/kelola/kelas/{$course['class']->id}/bab/{$chapterId}/lesson", ['type' => 'link', 'title' => 'Tautan', 'external_url' => 'https://evil.example/x'])->assertSessionHasErrors('external_url');
    $this->post("/kelola/kelas/{$course['class']->id}/bab/{$chapterId}/lesson", ['type' => 'link', 'title' => 'Tautan', 'external_url' => 'http://youtube.com/x'])->assertSessionHasErrors('external_url');
    $this->post("/kelola/kelas/{$course['class']->id}/bab/{$chapterId}/lesson", ['type' => 'link', 'title' => 'Tautan', 'external_url' => 'https://www.youtube.com/watch?v=abc'])->assertSessionHasNoErrors();
})->group('FR-CNT-002');

it('validates uploads by their real content type, not the extension', function () {
    $course = makeCourse();
    signIn(RoleCode::AcademicAdmin);
    $chapterId = DB::table('chapters')->join('modules', 'modules.id', '=', 'chapters.module_id')->where('modules.course_class_id', $course['class']->id)->value('chapters.id');
    $fake = UploadedFile::fake()->createWithContent('materi.pdf', '<?php echo "bukan pdf"; ?>');

    $this->post("/kelola/kelas/{$course['class']->id}/bab/{$chapterId}/lesson", ['type' => 'pdf', 'title' => 'PDF', 'file' => $fake])->assertSessionHasErrors('file');

    $pdf = UploadedFile::fake()->createWithContent('materi.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
    $this->post("/kelola/kelas/{$course['class']->id}/bab/{$chapterId}/lesson", ['type' => 'pdf', 'title' => 'PDF Asli', 'file' => $pdf])->assertSessionHasNoErrors();
    $asset = DB::table('media_assets')->latest('created_at')->first();
    expect($asset->mime_type)->toBe('application/pdf')
        ->and($asset->storage_key)->toStartWith('media/')
        ->and($asset->storage_key)->not->toContain('materi');
})->group('FR-CNT-003', 'SEC-FILE');
