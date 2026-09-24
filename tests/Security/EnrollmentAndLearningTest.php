<?php

declare(strict_types=1);

use App\Modules\Access\RoleCode;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Services\MediaStorage;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => cache()->flush());

it('enrolls a participant in a free open class and takes one seat atomically', function () {
    $course = makeCourse(['quota' => 1]);
    $first = signIn(RoleCode::Participant);
    $enrollment = enrollVia($course['class'], $first);

    expect($enrollment->status)->toBe('enrolled')
        ->and($course['class']->fresh()->enrolled_count)->toBe(1)
        ->and(DB::table('enrollment_status_histories')->where('enrollment_id', $enrollment->id)->value('to_status'))->toBe('enrolled');

    $this->post('/keluar');
    nextRequest();
    signIn(RoleCode::Participant);
    $this->post(route('catalog.enroll', $course['class']))->assertSessionHasErrors('class');
    expect($course['class']->fresh()->enrolled_count)->toBe(1);
})->group('FR-ENR-001', 'FR-CLS-002');

it('allows only one active enrollment per program', function () {
    $course = makeCourse();
    $second = asSystem(function () use ($course) {
        $class = $course['class']->replicate(['enrolled_count']);
        $class->forceFill(['batch_name' => 'Batch 2', 'enrolled_count' => 0])->save();

        return $class;
    });
    $participant = signIn(RoleCode::Participant);
    enrollVia($course['class'], $participant);

    $this->post(route('catalog.enroll', $second))->assertSessionHasErrors('class');
})->group('FR-ENR-002');

it('refuses self-enrollment into paid programs and restricted classes', function () {
    $paid = makeCourse(['price' => 350000]);
    $restricted = makeCourse();
    $otherOrg = makeOrganization();
    asSystem(fn () => $restricted['class']->forceFill(['restricted_organization_id' => $otherOrg->id])->save());
    signIn(RoleCode::Participant);

    $this->post(route('catalog.enroll', $paid['class']))->assertSessionHasErrors('class');
    $this->post(route('catalog.enroll', $restricted['class']))->assertSessionHasErrors('class');
})->group('FR-ENR-001');

it('hides other participants enrollments and lessons of other classes', function () {
    $course = makeCourse();
    $other = makeCourse();
    $owner = signIn(RoleCode::Participant);
    $enrollment = enrollVia($course['class'], $owner);
    $this->post('/keluar');
    nextRequest();

    signIn(RoleCode::Participant);
    $this->get(route('learning.classroom', $enrollment))->assertNotFound();
    $this->get(route('learning.lesson', [$enrollment, $course['lessons'][0]]))->assertNotFound();

    $this->post('/keluar');
    nextRequest();
    loginAs($owner);
    nextRequest();
    $this->get(route('learning.lesson', [$enrollment, $other['lessons'][0]]))->assertNotFound();
})->group('SEC-AUTHZ-03');

it('isolates enrollments per user at the database level', function () {
    $course = makeCourse();
    $alice = signIn(RoleCode::Participant);
    enrollVia($course['class'], $alice);
    $this->post('/keluar');
    nextRequest();
    $bob = makeUser(RoleCode::Participant);

    $tenant = app(TenantContext::class);
    $tenant->applyFor($bob);
    expect(Enrollment::query()->where('course_class_id', $course['class']->id)->count())->toBe(0);
    $tenant->applyFor($alice);
    expect(Enrollment::query()->where('course_class_id', $course['class']->id)->count())->toBe(1);
    $tenant->clear();
})->group('SEC-AUTHZ-11');

it('lets a trainer see enrollments only of the classes they teach', function () {
    $course = makeCourse();
    $other = makeCourse();
    $participant = signIn(RoleCode::Participant);
    enrollVia($course['class'], $participant);
    enrollVia($other['class'], $participant);
    $trainer = makeUser(RoleCode::Trainer);
    asSystem(fn () => DB::table('class_trainers')->insert(['course_class_id' => $course['class']->id, 'user_id' => $trainer->id, 'role' => 'lead']));

    $tenant = app(TenantContext::class);
    $tenant->applyFor($trainer->fresh());
    expect(Enrollment::query()->pluck('course_class_id')->all())->toBe([$course['class']->id]);
    $tenant->clear();
})->group('SEC-AUTHZ-11');

it('tracks progress and marks text lessons complete', function () {
    $course = makeCourse(['final' => false]);
    $participant = signIn(RoleCode::Participant);
    $enrollment = enrollVia($course['class'], $participant);

    completeLessons($enrollment, [$course['lessons'][0]]);
    $enrollment = asSystem(fn () => $enrollment->fresh());
    expect($enrollment->status)->toBe('in_progress')->and($enrollment->progress_percent)->toBe(50);

    completeLessons($enrollment, [$course['lessons'][1]]);
    // Tanpa ujian akhir: semua syarat terpenuhi → menunggu approval sertifikat.
    expect(asSystem(fn () => $enrollment->fresh())->status)->toBe('pending_approval');
})->group('FR-CNT-005', 'FR-ENR-006');

it('does not credit implausible video progress', function () {
    $course = makeCourse(['final' => false]);
    $video = asSystem(function () use ($course) {
        $lesson = new Lesson;
        $lesson->forceFill(['chapter_id' => $course['lessons'][0]->chapter_id, 'type' => 'video', 'title' => 'Video', 'position' => 9, 'is_required' => true, 'duration_seconds' => 600])->save();

        return $lesson;
    });
    $participant = signIn(RoleCode::Participant);
    $enrollment = enrollVia($course['class'], $participant);
    $this->get(route('learning.lesson', [$enrollment, $video]))->assertOk();

    $this->postJson(route('learning.heartbeat', [$enrollment, $video]), ['position' => 600])->assertOk()->assertJson(['completed' => false]);
    $progress = asSystem(fn () => DB::table('lesson_progress')->where('enrollment_id', $enrollment->id)->where('lesson_id', $video->id)->first());
    expect($progress->status)->toBe('started')
        ->and($progress->watched_seconds)->toBeLessThan(60)
        ->and(json_decode((string) $progress->integrity_flags, true))->toHaveKey('implausible_progress');

    $this->travel(20)->minutes();
    for ($i = 0; $i < 3; $i++) {
        $this->postJson(route('learning.heartbeat', [$enrollment, $video]), ['position' => 600])->assertOk();
    }
    expect(asSystem(fn () => DB::table('lesson_progress')->where('enrollment_id', $enrollment->id)->where('lesson_id', $video->id)->value('status')))->toBe('completed');
})->group('SEC-EXAM-17');

it('serves private media only through short-lived URLs bound to the viewer', function () {
    Storage::fake('local');
    $course = makeCourse(['final' => false]);
    $admin = makeUser(RoleCode::AcademicAdmin);
    $asset = asSystem(fn () => app(MediaStorage::class)->store(
        UploadedFile::fake()->createWithContent('m.pdf', "%PDF-1.4\n%%EOF"), 'pdf', $admin));
    $pdfLesson = asSystem(function () use ($course, $asset) {
        $lesson = new Lesson;
        $lesson->forceFill(['chapter_id' => $course['lessons'][0]->chapter_id, 'type' => 'pdf', 'title' => 'PDF', 'position' => 5, 'is_required' => false, 'media_asset_id' => $asset->id])->save();

        return $lesson;
    });
    $participant = signIn(RoleCode::Participant);
    $enrollment = enrollVia($course['class'], $participant);

    $page = $this->get(route('learning.lesson', [$enrollment, $pdfLesson]))->assertOk()->getContent();
    preg_match('#href="([^"]+/media/[^"]+)"#', (string) $page, $match);
    $url = html_entity_decode($match[1]);

    $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get(route('media.stream', ['media' => $asset->id]))->assertForbidden(); // tanpa tanda tangan

    $this->post('/keluar');
    nextRequest();
    signIn(RoleCode::Participant);
    $this->get($url)->assertNotFound(); // URL terikat pengguna lain
    $this->travel(11)->minutes();
    $this->get($url)->assertForbidden(); // kedaluwarsa
})->group('FR-CNT-004');
