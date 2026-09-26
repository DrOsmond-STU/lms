<?php

declare(strict_types=1);

use App\Modules\Access\RoleCode;
use App\Modules\Communication\Models\Announcement;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Learning\Models\ClassGroup;
use App\Modules\Organization\Models\Organization;
use Illuminate\Support\Facades\DB;

/*
 * Administrasi: approval pendaftaran, kelompok belajar, buku nilai, pengumuman, peran supervisor.
 */

it('holds self-enrollment for approval and lets trainer/admin approve or reject', function () {
    $course = makeCourse(['final' => false]);
    $class = $course['class'];
    asSystem(fn () => $class->forceFill(['requires_approval' => true])->save());

    $participant = signIn(RoleCode::Participant);
    $this->post(route('catalog.enroll', $class))->assertRedirect(route('learning.index'));
    $enrollment = asSystem(fn () => Enrollment::query()->where('user_id', $participant->id)->firstOrFail());
    expect($enrollment->status)->toBe('applied');
    $this->get(route('learning.classroom', $enrollment))->assertNotFound();
    $this->get(route('learning.index'))->assertOk()->assertSee('Menunggu persetujuan trainer/admin');
    $this->get(route('catalog.participant.show', $course['program']->slug))->assertOk()->assertSee('menunggu persetujuan');
    $this->post('/keluar');
    nextRequest();

    $other = signIn(RoleCode::Participant);
    $this->post(route('catalog.enroll', $class))->assertRedirect();
    $otherEnrollment = asSystem(fn () => Enrollment::query()->where('user_id', $other->id)->firstOrFail());
    $this->post('/keluar');
    nextRequest();

    signIn(RoleCode::AcademicAdmin);
    $this->get(route('classes.participants', $class))->assertOk()->assertSee('Setujui')->assertSee('mensyaratkan persetujuan');
    $this->post(route('classes.enrollments.decide', [$class, $enrollment]), ['decision' => 'approve'])->assertSessionHasNoErrors();
    expect(asSystem(fn () => $enrollment->fresh()->status))->toBe('enrolled');
    expect(asSystem(fn () => DB::table('notifications')->where('user_id', $participant->id)->where('title', 'Pendaftaran disetujui')->exists()))->toBeTrue();
    $this->post(route('classes.enrollments.decide', [$class, $otherEnrollment]), ['decision' => 'reject'])->assertSessionHasErrors('reason');
    $this->post(route('classes.enrollments.decide', [$class, $otherEnrollment]), ['decision' => 'reject', 'reason' => 'Bukan peserta angkatan ini'])->assertSessionHasNoErrors();
    $fresh = asSystem(fn () => $otherEnrollment->fresh());
    expect($fresh->status)->toBe('cancelled')->and($fresh->rejection_reason)->toBe('Bukan peserta angkatan ini');
    expect(asSystem(fn () => $class->fresh()->enrolled_count))->toBe(1);
    // Sudah diputuskan → tidak bisa diputuskan lagi.
    $this->post(route('classes.enrollments.decide', [$class, $enrollment]), ['decision' => 'approve'])->assertSessionHasErrors('enrollment');
    $this->post('/keluar');
    nextRequest();

    // Peserta yang ditolak boleh mengajukan lagi.
    loginAs($other);
    nextRequest();
    $this->post(route('catalog.enroll', $class))->assertRedirect(route('learning.index'));
    expect(asSystem(fn () => Enrollment::query()->where('user_id', $other->id)->where('status', 'applied')->exists()))->toBeTrue();
});

it('manages study groups and shows the gradebook with CSV export', function () {
    $course = makeCourse(['final' => false]);
    $class = $course['class'];
    $participant = signIn(RoleCode::Participant);
    $enrollment = enrollVia($class, $participant);
    $this->post('/keluar');
    nextRequest();

    $admin = signIn(RoleCode::AcademicAdmin);
    $this->post(route('classes.groups.store', $class), ['name' => 'Kelompok A'])->assertSessionHasNoErrors();
    $this->post(route('classes.groups.store', $class), ['name' => 'Kelompok A'])->assertSessionHasErrors('name');
    $group = asSystem(fn () => ClassGroup::query()->where('course_class_id', $class->id)->firstOrFail());
    $this->get(route('classes.groups.index', $class))->assertOk()->assertSee('Kelompok A')->assertSee($participant->name);
    $this->post(route('classes.groups.assign', $class), ['assignments' => [$enrollment->id => $group->id]])->assertSessionHasNoErrors();
    expect(asSystem(fn () => $enrollment->fresh()->group_id))->toBe($group->id);

    $this->get(route('classes.gradebook', $class))->assertOk()->assertSee($participant->name)->assertSee('Kelompok A');
    $csv = $this->get(route('classes.gradebook.export', $class))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();
    expect($csv)->toContain('Peserta;Email;Kelompok')->toContain($participant->email);

    $this->delete(route('classes.groups.destroy', [$class, $group]))->assertSessionHasNoErrors();
    expect(asSystem(fn () => $enrollment->fresh()->group_id))->toBeNull();
    $this->post('/keluar');
    nextRequest();

    loginAs($participant);
    nextRequest();
    $this->get(route('learning.index'))->assertOk();
    $this->get(route('classes.gradebook', $class))->assertNotFound();
});

it('publishes announcements per audience', function () {
    $course = makeCourse(['final' => false]);
    $class = $course['class'];
    $organization = asSystem(fn () => Organization::factory()->create());

    $member = makeUser(RoleCode::Participant, $organization);
    $outsider = makeUser(RoleCode::Participant);

    // Admin platform: pengumuman platform + notifikasi.
    signIn(RoleCode::AcademicAdmin);
    $this->post(route('admin.announcements.store'), ['scope' => 'platform', 'title' => 'Libur Nasional', 'body' => 'Platform **tetap** dapat diakses.', 'notify' => '1'])->assertSessionHasNoErrors();
    $this->post(route('admin.announcements.store'), ['scope' => 'organization', 'title' => 'Tanpa organisasi', 'body' => 'Isi uji'])->assertSessionHasErrors('organization_id');
    $this->get(route('admin.announcements.index'))->assertOk()->assertSee('Libur Nasional')->assertSee('Seluruh platform');
    $platform = asSystem(fn () => Announcement::query()->where('scope', 'platform')->firstOrFail());
    expect($platform->body_html)->toContain('<strong>tetap</strong>');
    expect(asSystem(fn () => DB::table('notifications')->where('user_id', $member->id)->where('title', 'Pengumuman: Libur Nasional')->exists()))->toBeTrue();
    $this->post('/keluar');
    nextRequest();

    // Admin organisasi: hanya lingkup organisasinya; lingkup platform ditolak.
    signIn(RoleCode::OrgAdmin, $organization);
    $this->post(route('org.announcements.store'), ['scope' => 'organization', 'organization_id' => $organization->id, 'title' => 'Rapat Anggota', 'body' => 'Jumat pukul 09.00.'])->assertSessionHasNoErrors();
    $this->post(route('org.announcements.store'), ['scope' => 'platform', 'title' => 'Coba platform', 'body' => 'Isi uji'])->assertForbidden();
    $this->get(route('org.announcements.index'))->assertOk()->assertSee('Rapat Anggota');
    $this->put(route('admin.announcements.update', $platform), ['scope' => 'platform', 'title' => 'Ubah', 'body' => 'Isi uji'])->assertNotFound();
    $this->post('/keluar');
    nextRequest();

    // Anggota organisasi yang terdaftar di kelas melihat platform + organisasi + kelas; bukan anggota hanya platform.
    loginAs($member);
    nextRequest();
    $this->post(route('catalog.enroll', $class))->assertRedirect();
    $this->post('/keluar');
    nextRequest();

    signIn(RoleCode::AcademicAdmin);
    $this->post(route('classes.announcements.store', $class), ['scope' => 'class', 'title' => 'Sesi Tambahan', 'body' => 'Sabtu pagi.', 'is_pinned' => '1'])->assertSessionHasNoErrors();
    $this->get(route('classes.announcements.index', $class))->assertOk()->assertSee('Sesi Tambahan')->assertSee('Disematkan');
    $this->post('/keluar');
    nextRequest();

    loginAs($member);
    nextRequest();
    $this->get(route('announcements.index'))->assertOk()->assertSee('Libur Nasional')->assertSee('Rapat Anggota')->assertSee('Sesi Tambahan');
    $this->get(route('participant.dashboard'))->assertOk()->assertSee('Pengumuman')->assertSee('Sesi Tambahan');
    $this->post('/keluar');
    nextRequest();

    loginAs($outsider);
    nextRequest();
    $this->get(route('announcements.index'))->assertOk()->assertSee('Libur Nasional')->assertDontSee('Rapat Anggota')->assertDontSee('Sesi Tambahan');
    $this->get(route('admin.announcements.index'))->assertNotFound();
});

it('gives supervisors read-only access to their organization workspace', function () {
    $organization = asSystem(fn () => Organization::factory()->create());
    $course = makeCourse(['final' => false]);
    $member = makeUser(RoleCode::Participant, $organization);
    loginAs($member);
    nextRequest();
    enrollVia($course['class'], $member);
    $this->post('/keluar');
    nextRequest();

    $supervisor = signIn(RoleCode::Supervisor, $organization);
    expect($supervisor->tenantOrganizationIds())->toBe([$organization->id])->and($supervisor->defaultWorkspace())->toBe('organization');
    $this->get('/dasbor')->assertRedirect(route('organization.dashboard'));
    $this->get(route('organization.dashboard'))->assertOk();
    $this->get(route('org.enrollments'))->assertOk()->assertSee($member->name);
    $this->get(route('org.members'))->assertForbidden();
    $this->get(route('org.announcements.index'))->assertForbidden();
    $this->get(route('admin.dashboard'))->assertNotFound();
    $this->get(route('announcements.index'))->assertOk();
});
