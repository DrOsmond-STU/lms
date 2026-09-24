<?php

declare(strict_types=1);

namespace App\Modules\Enrollment\Console;

use App\Modules\Access\RoleCode;
use App\Modules\Access\Services\RoleAssigner;
use App\Modules\Assessment\Models\Assessment;
use App\Modules\Assessment\Models\ExamAttempt;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Services\AttemptService;
use App\Modules\Catalog\Models\Program;
use App\Modules\Certification\Models\Certificate;
use App\Modules\Certification\Services\CertificateIssuer;
use App\Modules\Certification\Services\VerificationService;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Services\EnrollmentService;
use App\Modules\Enrollment\Services\ProgressService;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\ConsentRecorder;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Learning\Models\Lesson;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Simulasi perjalanan peserta end-to-end untuk UAT (lokal/staging): akun peserta FIKTIF
 * (@simulasi.test) bergabung ke kelas, belajar, mengerjakan kuis & ujian akhir, lalu admin
 * simulasi menyetujui sertifikat → PDF bertanda tangan → verifikasi publik. Semua langkah
 * memakai layanan aplikasi yang sama dengan antarmuka (tanpa jalan pintas ke tabel), dan
 * setiap langkah diperiksa hasilnya. Email tidak dikirim. Idempoten; ditolak di produksi.
 */
final class SimulateJourneyCommand extends Command
{
    public const EMAIL_DOMAIN = 'simulasi.test';

    protected $signature = 'stu:simulate {--program=DEMO-KI : Kode singkat program yang disimulasikan}';

    protected $description = 'Simulasi peserta: join kelas → belajar → ujian → sertifikat → verifikasi (hanya lokal/staging)';

    /** Persona: nama, target akhir. */
    private const PERSONAS = [
        ['Rina Kartika', 'certified'],
        ['Bagas Pratama', 'certified'],
        ['Sinta Maharani', 'certified'],
        ['Yoga Firmansyah', 'certified'],
        ['Laras Ayuningtyas', 'certified'],
        ['Fajar Nugroho', 'awaiting_approval'],
        ['Dimas Saputra', 'failed_exam'],
        ['Dewi Puspita', 'learning'],
        ['Nadia Rahma', 'cancelled'],
    ];

    /** @var list<array{0: string, 1: bool, 2: string}> */
    private array $checks = [];

    public function handle(
        TenantContext $tenant,
        RoleAssigner $roles,
        ConsentRecorder $consents,
        EnrollmentService $enrollments,
        ProgressService $progress,
        AttemptService $attempts,
        CertificateIssuer $issuer,
        VerificationService $verification,
    ): int {
        if (app()->isProduction()) {
            $this->error('Simulasi tidak boleh dijalankan di produksi.');

            return self::FAILURE;
        }
        // Tanpa efek keluar: email ke alamat fiktif dibuang, antrean diproses seketika (PDF ikut terbit).
        config(['mail.default' => 'array', 'queue.default' => 'sync', 'queue.urgent_connection' => 'sync']);

        return $tenant->runAsSystem(function () use ($roles, $consents, $enrollments, $progress, $attempts, $issuer, $verification): int {
            /** @var Program|null $program */
            $program = Program::query()->where('short_code', (string) $this->option('program'))->where('status', 'published')->first();
            $class = $program === null ? null : CourseClass::query()->where('program_id', $program->id)->where('status', 'open')->orderBy('starts_on')->first();
            if ($program === null || $class === null) {
                $this->error('Program terbit dengan kelas dibuka tidak ditemukan. Jalankan stu:demo-content terlebih dahulu.');

                return self::FAILURE;
            }
            $this->info("Program: {$program->name} · Kelas: {$class->batch_name}");

            $admin = $this->account('Admin Simulasi', 'admin', RoleCode::AcademicAdmin, $roles, $consents);
            $lessons = Lesson::query()->with('chapter.module')->whereIn('chapter_id', fn ($q) => $q->select('chapters.id')->from('chapters')->join('modules', 'modules.id', '=', 'chapters.module_id')->where('modules.course_class_id', $class->id))
                ->where('is_required', true)->orderBy('position')->get();
            $quizzes = Assessment::query()->where('course_class_id', $class->id)->where('kind', 'quiz')->get();
            $final = Assessment::query()->where('course_class_id', $class->id)->where('kind', 'final_exam')->first();

            $rows = [];
            foreach (self::PERSONAS as [$name, $target]) {
                $user = $this->account($name, Str::slug($name, '.'), RoleCode::Participant, $roles, $consents);
                $enrollment = Enrollment::query()->where('user_id', $user->id)->where('course_class_id', $class->id)->first();

                // 1. Bergabung ke kelas (kuota & aturan organisasi dicek layanan).
                if ($enrollment === null) {
                    $enrollment = $enrollments->enrollSelf($user, $class->refresh());
                }
                $this->check("{$name}: bergabung ke kelas", $enrollment->exists, $enrollment->statusLabel());

                if ($target === 'cancelled' && $enrollment->isActive()) {
                    $enrollments->cancel($enrollment->load('program', 'user'), $user, 'Simulasi: peserta membatalkan pendaftaran');
                    $enrollment->refresh();
                }

                // 2. Belajar: buka & selesaikan lesson wajib (lesson kuis selesai saat kuis lulus).
                if ($enrollment->isActive() && in_array($target, ['certified', 'awaiting_approval', 'failed_exam', 'learning'], true)) {
                    $toComplete = $target === 'learning' ? $lessons->where('type', '!=', 'quiz')->take(1) : $lessons->where('type', '!=', 'quiz');
                    foreach ($toComplete as $lesson) {
                        $progress->open($enrollment, $lesson);
                        $progress->markComplete($enrollment, $lesson);
                    }
                    $progress->recalculate($enrollment);
                    $enrollment->refresh();
                    $this->check("{$name}: mempelajari materi", $enrollment->progress_percent > 0, $enrollment->progress_percent.'%');
                }

                // 3. Kuis & ujian akhir.
                if ($enrollment->isActive() && in_array($target, ['certified', 'awaiting_approval', 'failed_exam'], true)) {
                    foreach ($quizzes as $quiz) {
                        $this->takeExam($attempts, $enrollment, $quiz, true);
                    }
                    if ($final !== null) {
                        $attempt = $this->takeExam($attempts, $enrollment, $final, $target !== 'failed_exam');
                        $this->check("{$name}: ujian akhir", $attempt !== null && $attempt->status === 'graded', $attempt === null ? 'tidak dapat dimulai' : 'skor '.fmt_score($attempt->score).($attempt->passed ? ' (lulus)' : ' (belum lulus)'));
                    }
                    $enrollment->refresh();
                }

                // 4. Persetujuan sertifikat oleh admin (SoD dicek layanan) → PDF bertanda tangan.
                if ($target === 'certified' && $enrollment->status === 'pending_approval') {
                    $blocker = $issuer->approvalBlocker($enrollment, $admin);
                    $this->check("{$name}: dapat disetujui admin", $blocker === null, $blocker ?? 'ok');
                    if ($blocker === null) {
                        $issuer->approve($enrollment, $admin);
                        $enrollment->refresh();
                    }
                }

                // 5. Sertifikat terbit & verifikasi publik.
                $certificate = Certificate::query()->where('enrollment_id', $enrollment->id)->first();
                if ($target === 'certified') {
                    $this->check("{$name}: sertifikat terbit", $certificate?->status === 'active', $certificate === null ? 'belum ada sertifikat' : 'status '.$certificate->status);
                }
                if ($certificate !== null && $certificate->status === 'active') {
                    $pdfOk = $certificate->pdf_storage_key !== null && Storage::disk((string) config('media.disk'))->exists($certificate->pdf_storage_key);
                    $this->check("{$name}: PDF sertifikat bertanda tangan", $pdfOk, $certificate->number);
                    $result = $verification->byCode($certificate->verification_code, '127.0.0.1', 'stu:simulate');
                    $this->check("{$name}: verifikasi publik", $result['status'] === 'valid', $result['status']);
                }

                $expected = ['certified' => 'passed', 'awaiting_approval' => 'pending_approval', 'failed_exam' => 'in_progress', 'learning' => 'in_progress', 'cancelled' => 'cancelled'][$target];
                $this->check("{$name}: status akhir sesuai skenario", $enrollment->status === $expected || ($target === 'failed_exam' && $enrollment->status === 'failed'), $enrollment->statusLabel());

                $rows[] = [$name, $user->email, $enrollment->statusLabel(), $enrollment->progress_percent.'%',
                    $certificate === null ? '—' : $certificate->number, $certificate?->status === 'active' ? route('verification.show', $certificate->formattedCode()) : '—'];
            }

            $this->newLine();
            $this->table(['Peserta', 'Email', 'Status', 'Progres', 'Nomor sertifikat', 'Verifikasi publik'], $rows);
            $failed = array_filter($this->checks, fn (array $check): bool => ! $check[1]);
            $this->info(sprintf('Pemeriksaan: %d langkah, %d berhasil, %d gagal.', count($this->checks), count($this->checks) - count($failed), count($failed)));
            foreach ($failed as [$label, , $detail]) {
                $this->error("GAGAL — {$label}: {$detail}");
            }

            // Akun admin simulasi tidak boleh bisa masuk di luar simulasi (tanpa MFA, kata sandi acak).
            $admin->forceFill(['status' => 'deactivated'])->save();

            return $failed === [] ? self::SUCCESS : self::FAILURE;
        });
    }

    /** Akun fiktif (@simulasi.test) dengan peran & persetujuan dokumen; kata sandi acak (tidak untuk masuk). */
    private function account(string $name, string $local, RoleCode $role, RoleAssigner $roles, ConsentRecorder $consents): User
    {
        $email = $local.'@'.self::EMAIL_DOMAIN;
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $user = new User;
            $user->forceFill([
                'name' => $name, 'email' => $email, 'password' => Str::password(40), 'status' => 'active', 'email_verified_at' => now(),
            ])->save();
            $roles->assign($user, $role, null, null);
            $consents->record($user, 'registration', Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'stu:simulate']));
        } elseif (! $user->isActive()) {
            // Akun admin simulasi dinonaktifkan di akhir run; aktifkan lagi selama simulasi berjalan.
            $user->forceFill(['status' => 'active'])->save();
        }

        return $user->refresh();
    }

    /** Kerjakan satu asesmen lewat layanan attempt: jawaban benar (lulus) atau salah (gagal). */
    private function takeExam(AttemptService $attempts, Enrollment $enrollment, Assessment $assessment, bool $correct): ?ExamAttempt
    {
        $existing = ExamAttempt::query()->where('enrollment_id', $enrollment->id)->where('assessment_id', $assessment->id)
            ->where('status', 'graded')->orderByDesc('attempt_no')->first();
        if ($existing !== null && ($existing->passed || ! $correct)) {
            return $existing;
        }
        if ($attempts->blockReason($enrollment->refresh(), $assessment) !== null) {
            return $existing;
        }

        $attempt = $attempts->start($enrollment, $assessment, '127.0.0.1');
        $questions = Question::query()->with('options')->whereIn('id', $attempt->question_order)->get()->keyBy('id');
        foreach ($attempt->question_order as $questionId) {
            $question = $questions->get($questionId);
            if (! $question instanceof Question) {
                continue;
            }
            $alias = AttemptService::alias($attempt, $questionId);
            if ($question->type === 'short_answer' || $question->type === 'essay') {
                $answer = $correct ? (string) (($question->accepted_answers_encrypted ?? [])[0] ?? 'jawaban simulasi') : 'tidak tahu';
                $attempts->saveAnswer($attempt, $alias, [], $answer);

                continue;
            }
            $options = $question->options->filter(fn ($option): bool => $option->is_correct === $correct);
            if ($question->type !== 'multiple_choice') {
                $options = $options->take(1);
            }
            $attempts->saveAnswer($attempt, $alias, array_values($options->map(fn ($option): string => AttemptService::alias($attempt, $option->id))->all()), null);
        }

        return $attempts->submit($attempt);
    }

    private function check(string $label, bool $ok, string $detail): void
    {
        $this->checks[] = [$label, $ok, $detail];
        $this->line(($ok ? '  <info>✓</info> ' : '  <error>✗</error> ').$label.' — '.$detail);
    }
}
