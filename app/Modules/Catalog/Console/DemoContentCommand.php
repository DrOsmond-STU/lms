<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Console;

use App\Modules\Assessment\Models\Assessment;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\QuestionBank;
use App\Modules\Assessment\Models\QuestionOption;
use App\Modules\Catalog\Models\Program;
use App\Modules\Cms\Models\SiteProfile;
use App\Modules\Learning\Models\Chapter;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\Module;
use App\Support\Content\RichText;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Konten contoh SINTETIS untuk lokal/staging (UAT): satu program gratis terbit dengan kelas
 * dibuka, materi teks, kuis, dan ujian akhir. Ditolak di produksi; idempoten (kode DEMO-*).
 */
final class DemoContentCommand extends Command
{
    protected $signature = 'stu:demo-content';

    protected $description = 'Buat program, kelas, materi & ujian contoh (hanya lokal/staging)';

    public function handle(TenantContext $tenant): int
    {
        if (app()->isProduction()) {
            $this->error('Konten demo tidak boleh dibuat di produksi.');

            return self::FAILURE;
        }
        if (Program::query()->where('short_code', 'DEMO-KI')->exists()) {
            $this->info('Konten demo sudah ada.');

            return self::SUCCESS;
        }

        $tenant->runAsSystem(fn () => DB::transaction(function (): void {
            $program = new Program;
            $program->forceFill([
                'category' => 'international', 'name' => 'Contoh: Dasar Keamanan Informasi', 'slug' => 'contoh-dasar-keamanan-informasi',
                'provider_name' => SiteProfile::current()->company_name, 'short_code' => 'DEMO-KI', 'level' => 'dasar', 'duration_hours' => 4,
                'language' => 'id', 'default_mode' => 'online', 'passing_score' => 70, 'certificate_validity_months' => 36, 'price' => 0,
                'description_md' => "Program **contoh** untuk uji penerimaan (UAT).\n\n- Prinsip kerahasiaan, integritas, ketersediaan\n- Kata sandi & autentikasi dua faktor\n- Mengenali phishing",
                'status' => 'published', 'published_at' => now(),
            ]);
            $program->description_html = RichText::toHtml($program->description_md);
            $program->save();

            $class = new CourseClass;
            $class->forceFill([
                'program_id' => $program->id, 'batch_name' => 'Batch Demo', 'starts_on' => now()->toDateString(),
                'ends_on' => now()->addMonths(3)->toDateString(), 'quota' => 100, 'mode' => 'online', 'status' => 'open',
                'completion_rules' => ['require_final_exam' => true],
            ])->save();

            $bank = new QuestionBank;
            $bank->forceFill(['program_id' => $program->id, 'name' => 'Bank Soal Demo'])->save();
            $questions = [
                ['type' => 'single_choice', 'stem' => 'Apa kepanjangan CIA dalam keamanan informasi?', 'difficulty' => 1, 'answers' => [],
                    'options' => [['Confidentiality, Integrity, Availability', true], ['Control, Inspect, Audit', false], ['Cyber, Internet, Access', false]]],
                ['type' => 'true_false', 'stem' => 'Autentikasi dua faktor menggabungkan dua jenis bukti identitas yang berbeda.', 'difficulty' => 1, 'answers' => [],
                    'options' => [['Benar', true], ['Salah', false]]],
                ['type' => 'multiple_choice', 'stem' => 'Manakah ciri umum email phishing?', 'difficulty' => 2, 'answers' => [],
                    'options' => [['Mendesak meminta kata sandi', true], ['Dari domain resmi tanpa permintaan data', false], ['Tautan ke alamat yang mirip tetapi berbeda', true]]],
                ['type' => 'true_false', 'stem' => 'Kata sandi yang baik sebaiknya dipakai ulang di banyak layanan.', 'difficulty' => 1, 'answers' => [],
                    'options' => [['Benar', false], ['Salah', true]]],
                ['type' => 'short_answer', 'stem' => 'Sebutkan satu contoh faktor autentikasi "sesuatu yang Anda miliki".', 'difficulty' => 2,
                    'answers' => ['ponsel', 'token', 'kunci keamanan', 'kartu'], 'options' => []],
                ['type' => 'single_choice', 'stem' => 'Siapa yang boleh mengetahui kode OTP Anda?', 'difficulty' => 1, 'answers' => [],
                    'options' => [['Hanya Anda', true], ['Petugas bank', false], ['Admin LMS', false]]],
            ];
            foreach ($questions as $item) {
                $question = new Question;
                $question->forceFill([
                    'question_bank_id' => $bank->id, 'type' => $item['type'], 'stem_md' => $item['stem'], 'stem_html' => RichText::toHtml($item['stem']),
                    'difficulty' => $item['difficulty'], 'points' => 1, 'competency_tags' => ['keamanan'],
                    'accepted_answers_encrypted' => $item['type'] === 'short_answer' ? $item['answers'] : null,
                ])->save();
                foreach ($item['options'] as $position => [$body, $correct]) {
                    $option = new QuestionOption;
                    $option->forceFill(['question_id' => $question->id, 'body_html' => e($body), 'is_correct' => $correct, 'position' => $position + 1])->save();
                }
            }

            $quiz = new Assessment;
            $quiz->forceFill([
                'course_class_id' => $class->id, 'question_bank_id' => $bank->id, 'kind' => 'quiz', 'title' => 'Kuis Modul 1',
                'duration_minutes' => 10, 'max_attempts' => 3, 'question_count' => 3, 'passing_score' => 60, 'review_policy' => 'after_submit',
                'is_required' => true, 'requires_prerequisites' => false,
            ])->save();
            $final = new Assessment;
            $final->forceFill([
                'course_class_id' => $class->id, 'question_bank_id' => $bank->id, 'kind' => 'final_exam', 'title' => 'Ujian Akhir',
                'duration_minutes' => 20, 'max_attempts' => 2, 'question_count' => 6, 'passing_score' => 70, 'review_policy' => 'after_close',
                'is_required' => true, 'requires_prerequisites' => true,
            ])->save();

            $module = new Module;
            $module->forceFill(['course_class_id' => $class->id, 'title' => 'Pengantar Keamanan Informasi', 'position' => 1])->save();
            $chapter = new Chapter;
            $chapter->forceFill(['module_id' => $module->id, 'title' => 'Konsep Dasar', 'position' => 1])->save();
            $lessons = [
                ['text', 'Prinsip CIA', "## Kerahasiaan, Integritas, Ketersediaan\n\nKeamanan informasi menjaga agar data hanya diakses pihak berwenang, tidak diubah tanpa izin, dan tersedia saat dibutuhkan."],
                ['text', 'Autentikasi Dua Faktor', 'Gunakan **aplikasi authenticator**. Jangan pernah membagikan kode OTP kepada siapa pun.'],
                ['quiz', 'Kuis Modul 1', null],
            ];
            foreach ($lessons as $position => [$type, $title, $body]) {
                $lesson = new Lesson;
                $lesson->forceFill([
                    'chapter_id' => $chapter->id, 'type' => $type, 'title' => $title, 'position' => $position + 1, 'is_required' => true,
                    'body_md' => $body, 'body_html' => $body === null ? null : RichText::toHtml($body),
                    'assessment_id' => $type === 'quiz' ? $quiz->id : null, 'published_at' => now(),
                ])->save();
            }
        }));

        $this->info('Konten demo dibuat: program "Contoh: Dasar Keamanan Informasi" (gratis, kelas dibuka).');

        return self::SUCCESS;
    }
}
