<?php

declare(strict_types=1);

use App\Modules\Access\RoleCode;
use App\Modules\Learning\Models\AttendanceRecord;
use App\Modules\Learning\Models\ClassSession;
use App\Modules\Learning\Models\Lesson;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
 * Fase konten & jadwal: lesson audio/dokumen, drip content & prasyarat, urutan wajib,
 * sesi kelas (live class) dengan cek-in mandiri & presensi manual, kalender akademik.
 */

/** Berkas DOCX minimal (zip dengan [Content_Types].xml) untuk uji deteksi jenis. */
function fakeDocx(): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'docx');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $zip->addFromString('word/document.xml', '<w:document/>');
    $zip->close();

    return new UploadedFile($path, 'materi.docx', 'application/octet-stream', null, true);
}

it('accepts audio and office document lessons and locks them by drip and prerequisite rules', function () {
    Storage::fake('local');
    $course = makeCourse();
    $chapterId = $course['lessons'][0]->chapter_id;
    signIn(RoleCode::AcademicAdmin);

    // Dokumen kantor: jenis dikenali dari isi arsip, bukan ekstensi/header klien.
    $this->post(route('content.lessons.store', [$course['class'], $chapterId]), ['type' => 'document', 'title' => 'Slide Pengantar', 'file' => fakeDocx(), 'is_required' => '1', 'unlock_after_days' => 7])
        ->assertSessionHasNoErrors()->assertRedirect(route('classes.manage', $course['class']));
    $document = asSystem(fn () => Lesson::query()->where('type', 'document')->firstOrFail());
    expect($document->media->mime_type)->toBe('application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->and($document->unlock_after_days)->toBe(7)->and($document->allow_download)->toBeTrue();

    // Audio: berkas MP3 (header ID3) + durasi wajib.
    $mp3 = UploadedFile::fake()->createWithContent('podcast.mp3', "ID3\x04\x00\x00\x00\x00\x00\x00".str_repeat("\xFF\xFB\x90\x00", 600));
    $this->post(route('content.lessons.store', [$course['class'], $chapterId]), ['type' => 'audio', 'title' => 'Podcast', 'file' => $mp3, 'duration_minutes' => 3, 'duration_seconds_part' => 0, 'prerequisite_lesson_id' => $course['lessons'][0]->id])
        ->assertSessionHasNoErrors();
    $audio = asSystem(fn () => Lesson::query()->where('type', 'audio')->firstOrFail());
    expect($audio->media->kind)->toBe('audio')->and($audio->prerequisite_lesson_id)->toBe($course['lessons'][0]->id);

    // Berkas yang bukan audio ditolak walau ekstensinya mp3.
    $this->post(route('content.lessons.store', [$course['class'], $chapterId]), ['type' => 'audio', 'title' => 'Palsu', 'file' => UploadedFile::fake()->createWithContent('x.mp3', '%PDF-1.4 bukan audio'), 'duration_minutes' => 1, 'duration_seconds_part' => 0])
        ->assertSessionHasErrors('file');
    $this->post('/keluar');
    nextRequest();

    // Peserta: dokumen terkunci 7 hari, audio terkunci sampai prasyarat selesai.
    $participant = signIn(RoleCode::Participant);
    $enrollment = enrollVia($course['class'], $participant);
    $this->get(route('learning.lesson', [$enrollment, $document]))->assertRedirect(route('learning.classroom', $enrollment))->assertSessionHas('status', fn (string $s) => str_contains($s, 'terkunci'));
    $this->get(route('learning.lesson', [$enrollment, $audio]))->assertRedirect(route('learning.classroom', $enrollment));
    $this->post(route('learning.complete', [$enrollment, $audio]))->assertSessionHasErrors('lesson');
    $this->get(route('learning.classroom', $enrollment))->assertOk()->assertSee('7 hari setelah pendaftaran');

    completeLessons($enrollment, [$course['lessons'][0]]);
    $this->get(route('learning.lesson', [$enrollment, $audio]))->assertOk()->assertSee('<audio', false);
    // Heartbeat audio menambah durasi dengar & durasi belajar.
    $this->postJson(route('learning.heartbeat', [$enrollment, $audio]), ['position' => 20])->assertOk();
    $this->travel(200)->seconds();
    $this->postJson(route('learning.heartbeat', [$enrollment, $audio]), ['position' => 175])->assertOk()->assertJson(['completed' => true]);
    $progress = asSystem(fn () => DB::table('lesson_progress')->where('enrollment_id', $enrollment->id)->where('lesson_id', $audio->id)->first());
    expect($progress->status)->toBe('completed')->and((int) $progress->time_spent_seconds)->toBeGreaterThan(0);

    // Ping lesson teks menambah durasi belajar dengan batas kredit per ping.
    $this->get(route('learning.lesson', [$enrollment, $course['lessons'][1]]))->assertOk();
    $this->travel(5)->minutes();
    $this->postJson(route('learning.ping', [$enrollment, $course['lessons'][1]]))->assertOk()->assertJson(['time_spent' => 60]);
});

it('locks lessons sequentially when the class requires order', function () {
    $course = makeCourse();
    asSystem(fn () => $course['class']->forceFill(['is_sequential' => true])->save());
    $participant = signIn(RoleCode::Participant);
    $enrollment = enrollVia($course['class'], $participant);

    $this->get(route('learning.lesson', [$enrollment, $course['lessons'][1]]))->assertRedirect(route('learning.classroom', $enrollment));
    $this->get(route('learning.classroom', $enrollment))->assertSee('secara berurutan');
    completeLessons($enrollment, [$course['lessons'][0]]);
    $this->get(route('learning.lesson', [$enrollment, $course['lessons'][1]]))->assertOk();
});

it('schedules live sessions with self check-in, manual attendance, export and academic calendar', function () {
    $course = makeCourse();
    $participant = makeUser(RoleCode::Participant);
    loginAs($participant);
    nextRequest();
    $enrollment = enrollVia($course['class'], $participant);
    $this->post('/keluar');
    nextRequest();

    $admin = signIn(RoleCode::AcademicAdmin);
    $starts = now()->addMinutes(5);
    $this->post(route('classes.sessions.store', $course['class']), [
        'title' => 'Live Class Pekan 1', 'type' => 'online', 'starts_at' => $starts->format('Y-m-d H:i'), 'ends_at' => $starts->copy()->addHours(2)->format('Y-m-d H:i'),
        'meeting_url' => 'https://meet.google.com/abc-defg-hij', 'attendance_mode' => 'self', 'checkin_code' => 'abc123', 'checkin_opens_before' => 15, 'checkin_closes_after' => 30,
    ])->assertSessionHasNoErrors();
    // Live class tanpa tautan ditolak.
    $this->post(route('classes.sessions.store', $course['class']), ['title' => 'Tanpa link', 'type' => 'online', 'starts_at' => $starts->format('Y-m-d H:i'), 'ends_at' => $starts->copy()->addHour()->format('Y-m-d H:i'), 'attendance_mode' => 'none'])
        ->assertSessionHasErrors('meeting_url');
    $session = asSystem(fn () => ClassSession::query()->where('course_class_id', $course['class']->id)->firstOrFail());
    expect($session->checkin_code)->toBe('ABC123');
    expect(asSystem(fn () => DB::table('notifications')->where('user_id', $participant->id)->where('title', 'Sesi baru dijadwalkan')->exists()))->toBeTrue();

    // Kalender akademik (admin) → tampil di jadwal peserta.
    $this->post(route('admin.calendar.store'), ['title' => 'Libur Nasional Uji', 'kind' => 'holiday', 'scope' => 'platform', 'starts_on' => now()->toDateString(), 'ends_on' => now()->toDateString()])->assertSessionHasNoErrors();
    $this->get(route('classes.sessions', $course['class']))->assertOk()->assertSee('Live Class Pekan 1')->assertSee('ABC123');
    $this->post('/keluar');
    nextRequest();

    loginAs($participant);
    nextRequest();
    $this->get(route('schedule.participant'))->assertOk()->assertSee('Live Class Pekan 1')->assertSee('Libur Nasional Uji')->assertSee('Gabung Live Class');
    // Kode salah ditolak, kode benar mencatat hadir.
    $this->post(route('schedule.checkin', [$enrollment, $session]), ['code' => 'salah'])->assertSessionHasErrors('checkin');
    $this->post(route('schedule.checkin', [$enrollment, $session]), ['code' => 'abc123'])->assertSessionHasNoErrors();
    $record = asSystem(fn () => AttendanceRecord::query()->where('class_session_id', $session->id)->where('enrollment_id', $enrollment->id)->firstOrFail());
    expect($record->status)->toBe('present')->and($record->method)->toBe('self');
    // Di luar jendela → ditolak.
    $this->travel(2)->hours();
    $this->post(route('schedule.checkin', [$enrollment, $session]), ['code' => 'abc123'])->assertSessionHasErrors('checkin');
    $this->post('/keluar');
    nextRequest();

    // Presensi manual oleh admin & ekspor CSV.
    loginAs($admin, enrollTotp($admin));
    nextRequest();
    $this->get(route('classes.attendance', [$course['class'], $session]))->assertOk()->assertSee('Hadir');
    $this->post(route('classes.attendance.store', [$course['class'], $session]), ['status' => [$enrollment->id => 'excused'], 'note' => [$enrollment->id => 'Sakit']])->assertSessionHasNoErrors();
    expect(asSystem(fn () => AttendanceRecord::query()->whereKey($record->id)->value('status')))->toBe('excused');
    $csv = $this->get(route('classes.attendance.export', $course['class']))->assertOk();
    expect($csv->streamedContent())->toContain('Live Class Pekan 1')->toContain('Izin');

    // Peserta tidak dapat membuka halaman kelola sesi.
    $this->post('/keluar');
    nextRequest();
    loginAs($participant);
    nextRequest();
    $this->get(route('classes.sessions', $course['class']))->assertNotFound();
});

it('lets a participant see enrollment activity even for cancelled sessions gracefully', function () {
    $course = makeCourse();
    $participant = signIn(RoleCode::Participant);
    enrollVia($course['class'], $participant);
    $this->get(route('schedule.participant', ['bulan' => now()->addMonth()->format('Y-m')]))->assertOk()->assertSee('Tidak ada jadwal');
});
