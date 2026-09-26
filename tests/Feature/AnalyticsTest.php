<?php

declare(strict_types=1);

use App\Modules\Access\RoleCode;
use App\Modules\Assessment\Models\ExamAttempt;
use App\Support\Export\TableExport;
use Illuminate\Support\Facades\DB;

/*
 * Dashboard & analitik: laporan kelas trainer/admin, nilai & riwayat peserta, ekspor CSV/XLSX/PDF.
 */

it('shows class analytics, participant grades and exports in three formats', function () {
    $course = makeCourse(['final' => true]);
    $class = $course['class'];
    $participant = signIn(RoleCode::Participant);
    $enrollment = enrollVia($class, $participant);
    completeLessons($enrollment, $course['lessons']);
    $this->post(route('exams.start', [$enrollment, $course['final']]))->assertRedirect();
    $attempt = asSystem(fn () => ExamAttempt::query()->where('enrollment_id', $enrollment->id)->firstOrFail());
    answerAttempt($attempt, true);
    $this->get(route('learning.grades'))->assertOk()->assertSee($course['program']->name)->assertSee('Ujian Akhir')->assertSee('Lulus')->assertSee('Riwayat Status');
    $this->post('/keluar');
    nextRequest();

    signIn(RoleCode::AcademicAdmin);
    $this->get(route('classes.report', $class))->assertOk()->assertSee('Tingkat penyelesaian')->assertSee($participant->name)->assertSee('Hasil Kuis');
    foreach (array_keys(TableExport::FORMATS) as $format) {
        $response = $this->get(route('classes.report.export', [$class, 'format' => $format]))->assertOk();
        $content = $response->streamedContent();
        match ($format) {
            'csv' => expect($content)->toContain('Peserta;Email')->toContain($participant->email),
            'xlsx' => expect(substr($content, 0, 2))->toBe('PK'),
            'pdf' => expect(substr($content, 0, 4))->toBe('%PDF'),
        };
        $this->get(route('classes.gradebook.export', [$class, 'format' => $format]))->assertOk();
    }
    $this->get(route('admin.reports.organizations.export', ['format' => 'xlsx']))->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect(asSystem(fn () => DB::table('audit_logs')->where('action', 'report.exported')->count()))->toBeGreaterThanOrEqual(4);
    $this->post('/keluar');
    nextRequest();

    // Trainer tanpa kelas: daftar kosong; peserta tidak dapat membuka laporan kelas.
    signIn(RoleCode::Trainer);
    $this->get(route('trainer.reports'))->assertOk()->assertSee('Belum ada kelas');
    $this->get(route('classes.report', $class))->assertNotFound();
});

it('builds a valid minimal xlsx workbook', function () {
    $binary = TableExport::xlsx('Uji', ['Nama', 'Skor'], [['=Rumus', 90.5], ['Budi', 80]]);
    $path = tempnam(sys_get_temp_dir(), 'x');
    file_put_contents($path, $binary);
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    unlink($path);
    expect($sheet)->toContain('&apos;=Rumus')->toContain('<v>90.5</v>')->toContain('r="B3"');
});
