<?php

declare(strict_types=1);

namespace App\Modules\Ai\Services;

use App\Modules\Ai\Models\AiConversation;
use App\Modules\Ai\Models\AiInsight;
use App\Modules\Ai\Models\LessonSummary;
use App\Modules\Assessment\Models\AttemptAnswer;
use App\Modules\Assessment\Models\ExamAttempt;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\QuestionBank;
use App\Modules\Assessment\Models\QuestionOption;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\Chapter;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\Module;
use App\Support\Content\RichText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fitur AI berbasis Claude: tutor materi, rangkuman, rekomendasi belajar, jalur belajar
 * personal, generator soal, saran penilaian esai, analisis kelas, rancangan kurikulum.
 * Semua keluaran Markdown disanitasi (RichText) sebelum ditampilkan; soal & kurikulum
 * hasil AI disimpan sebagai draf yang harus ditinjau manusia.
 *
 * @phpstan-import-type ClassReport from \App\Modules\Reporting\Services\ClassReportService
 */
final class AiAssistant
{
    private const BASE_SYSTEM = 'Anda adalah asisten pembelajaran pada platform pelatihan dan sertifikasi STU LMS. Selalu jawab dalam Bahasa Indonesia yang jelas, ringkas, dan sopan. Jangan mengarang fakta; bila tidak yakin, katakan. Jangan pernah membocorkan kunci jawaban ujian.';

    public function __construct(private readonly ClaudeClient $claude, private readonly AuditLogger $audit) {}

    /** Tutor materi: menjawab pertanyaan peserta berdasarkan konten lesson (20 pesan terakhir disimpan). */
    public function tutorReply(User $user, Enrollment $enrollment, Lesson $lesson, string $question): string
    {
        $conversation = AiConversation::query()->where('user_id', $user->id)->where('enrollment_id', $enrollment->id)->where('lesson_id', $lesson->id)->first() ?? new AiConversation;
        $history = array_slice($conversation->messages ?? [], -10);
        $messages = array_map(fn (array $m) => ['role' => $m['role'], 'content' => $m['content']], $history);
        $messages[] = ['role' => 'user', 'content' => $question];
        $system = self::BASE_SYSTEM."\nPeran Anda: tutor untuk materi \"".$lesson->title.'" pada program "'.$enrollment->program->name."\". Jelaskan konsep dengan contoh, ajukan pertanyaan pancingan bila membantu, dan arahkan peserta kembali ke materi. Tolak dengan sopan permintaan di luar konteks belajar. Jangan memberikan jawaban soal kuis/ujian secara langsung.\n\nMateri:\n".self::lessonText($lesson);
        $reply = $this->claude->complete($user, 'tutor', $system, $messages, 800, 0.4);
        $stored = array_slice(array_merge($history, [['role' => 'user', 'content' => $question, 'at' => now()->toIso8601String()], ['role' => 'assistant', 'content' => $reply, 'at' => now()->toIso8601String()]]), -20);
        $conversation->forceFill(['user_id' => $user->id, 'enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id, 'messages' => $stored])->save();

        return $reply;
    }

    /** @return list<array{role: string, content: string, at?: string}> */
    public function tutorHistory(User $user, Enrollment $enrollment, Lesson $lesson): array
    {
        $conversation = AiConversation::query()->where('user_id', $user->id)->where('enrollment_id', $enrollment->id)->where('lesson_id', $lesson->id)->first();

        return $conversation instanceof AiConversation ? $conversation->messages : [];
    }

    /** Rangkuman materi (di-cache per lesson; dibuat ulang bila materi berubah setelah rangkuman). */
    public function summarize(User $user, Lesson $lesson, bool $force = false): LessonSummary
    {
        $existing = LessonSummary::query()->find($lesson->id);
        if ($existing instanceof LessonSummary && ! $force && $existing->generated_at->gte($lesson->updated_at ?? $existing->generated_at)) {
            return $existing;
        }
        $text = self::lessonText($lesson);
        if (mb_strlen($text) < 80) {
            throw new AiUnavailableException('Materi ini tidak memiliki teks yang cukup untuk dirangkum (video/dokumen tanpa transkrip).');
        }
        $markdown = $this->claude->complete($user, 'summary', self::BASE_SYSTEM."\nBuat rangkuman belajar dari materi berikut dalam Markdown: 3–6 poin utama, istilah kunci dengan definisi singkat, dan 2 pertanyaan refleksi. Maksimal 250 kata.", [['role' => 'user', 'content' => 'Judul: '.$lesson->title."\n\n".$text]], 900);
        $summary = $existing ?? new LessonSummary;
        $summary->forceFill(['lesson_id' => $lesson->id, 'summary_md' => $markdown, 'summary_html' => RichText::toHtml($markdown), 'model' => ClaudeClient::model(), 'generated_at' => now()])->save();

        return $summary;
    }

    /** Rekomendasi belajar personal untuk satu enrollment (cache 12 jam). */
    public function recommend(User $user, Enrollment $enrollment, bool $force = false): AiInsight
    {
        $cached = AiInsight::find('recommendation', $enrollment->id);
        if ($cached instanceof AiInsight && ! $force && $cached->isFresh(12)) {
            return $cached;
        }
        $context = $this->enrollmentContext($enrollment);
        $markdown = $this->claude->complete($user, 'recommendation', self::BASE_SYSTEM."\nBerdasarkan data progres berikut, beri rekomendasi belajar personal dalam Markdown: (1) 2–3 langkah berikutnya yang paling berdampak (sebut judul materi/kuis), (2) area yang perlu diperkuat, (3) satu tips belajar singkat. Maksimal 180 kata, nada menyemangati.", [['role' => 'user', 'content' => $context]], 700);

        return AiInsight::put('recommendation', $enrollment->id, $markdown, RichText::toHtml($markdown));
    }

    /** Jalur belajar personal lintas program (cache 24 jam). */
    public function learningPath(User $user, bool $force = false): AiInsight
    {
        $cached = AiInsight::find('learning_path', $user->id);
        if ($cached instanceof AiInsight && ! $force && $cached->isFresh(24)) {
            return $cached;
        }
        $enrollments = Enrollment::query()->with('program:id,name,category,level', 'courseClass:id,batch_name')->where('user_id', $user->id)->whereNotIn('status', ['cancelled'])->get();
        $lines = $enrollments->map(fn (Enrollment $e) => '- '.$e->program->name.' ('.$e->statusLabel().', progres '.$e->progress_percent.'%'.($e->final_score !== null ? ', skor '.$e->final_score : '').')')->implode("\n");
        $catalog = DB::table('programs')->where('status', 'published')->whereNotIn('id', $enrollments->pluck('program_id'))->orderBy('name')->limit(40)->get(['name', 'category', 'level', 'duration_hours'])
            ->map(fn ($p) => '- '.$p->name.' ['.$p->category.($p->level ? ', '.$p->level : '').', '.$p->duration_hours.' jam]')->implode("\n");
        $prompt = "Riwayat pelatihan peserta:\n".($lines !== '' ? $lines : '- belum ada')."\n\nProgram tersedia di katalog (belum diikuti):\n".($catalog !== '' ? $catalog : '- tidak ada');
        $markdown = $this->claude->complete($user, 'learning_path', self::BASE_SYSTEM."\nSusun jalur belajar personal dalam Markdown: (1) prioritas menyelesaikan pelatihan yang sedang berjalan, (2) urutan 2–4 program berikutnya dari katalog yang paling relevan dengan alasan singkat, (3) estimasi waktu. Hanya sebut program yang ada dalam daftar. Maksimal 220 kata.", [['role' => 'user', 'content' => $prompt]], 900);

        return AiInsight::put('learning_path', $user->id, $markdown, RichText::toHtml($markdown));
    }

    /**
     * Generator soal ke bank soal (disimpan nonaktif sebagai draf AI untuk ditinjau).
     *
     * @return int jumlah soal dibuat
     */
    public function generateQuestions(User $user, QuestionBank $bank, string $topic, int $count, string $type, int $difficulty): int
    {
        $count = max(1, min(10, $count));
        $typeLabel = Question::TYPES[$type] ?? $type;
        $schema = match ($type) {
            'true_false' => '{"stem": string, "answer": true|false, "explanation": string}',
            'essay' => '{"stem": string, "rubric": [{"name": string, "max": number, "description": string}], "explanation": string}',
            default => '{"stem": string, "options": [{"body": string, "correct": boolean}] (4 opsi, '.($type === 'multiple_choice' ? '1–3' : 'tepat 1').' benar), "explanation": string}',
        };
        $result = $this->claude->json($user, 'questions', self::BASE_SYSTEM."\nAnda membuat soal latihan/ujian berkualitas untuk bank soal program \"".$bank->program->name."\". Tipe: {$typeLabel}. Tingkat kesulitan {$difficulty} dari 5. Soal harus jelas, tidak ambigu, pengecoh masuk akal, dan bebas dari petunjuk jawaban. Format: {\"questions\": [".$schema.']}', [['role' => 'user', 'content' => "Buat {$count} soal tentang:\n".$topic]], 4096);
        $items = is_array($result['questions'] ?? null) ? $result['questions'] : [];
        $created = 0;
        DB::transaction(function () use ($items, $bank, $type, $difficulty, $user, &$created): void {
            foreach (array_slice($items, 0, 10) as $item) {
                if (! is_array($item) || ! is_string($item['stem'] ?? null) || trim($item['stem']) === '') {
                    continue;
                }
                $rows = [];
                if ($type === 'true_false') {
                    $answer = (bool) ($item['answer'] ?? true);
                    $rows = [['Benar', $answer], ['Salah', ! $answer]];
                } elseif (in_array($type, ['single_choice', 'multiple_choice'], true)) {
                    foreach (is_array($item['options'] ?? null) ? $item['options'] : [] as $option) {
                        if (is_array($option) && is_string($option['body'] ?? null) && trim($option['body']) !== '') {
                            $rows[] = [trim($option['body']), (bool) ($option['correct'] ?? false)];
                        }
                    }
                    $correct = count(array_filter($rows, fn (array $r) => $r[1]));
                    if (count($rows) < 2 || $correct === 0 || ($type === 'single_choice' && $correct !== 1)) {
                        continue;
                    }
                }
                $rubric = null;
                if ($type === 'essay' && is_array($item['rubric'] ?? null)) {
                    $rubric = array_values(array_filter(array_map(fn ($c) => is_array($c) && is_string($c['name'] ?? null) ? ['name' => Str::limit(trim($c['name']), 60, ''), 'max' => max(1, (float) ($c['max'] ?? 10)), 'description' => Str::limit(trim((string) ($c['description'] ?? '')), 200, '')] : null, $item['rubric'])));
                    $rubric = $rubric === [] ? null : array_slice($rubric, 0, 6);
                }
                $question = new Question;
                $question->forceFill([
                    'question_bank_id' => $bank->id, 'type' => $type, 'stem_md' => trim($item['stem']), 'stem_html' => RichText::toHtml($item['stem']),
                    'explanation_html' => RichText::toHtml(is_string($item['explanation'] ?? null) ? $item['explanation'] : null) ?: null,
                    'difficulty' => max(1, min(5, $difficulty)), 'points' => 1, 'competency_tags' => ['draf-ai'], 'is_active' => false, 'version' => 1, 'created_by' => $user->id,
                    'rubric' => $rubric,
                ])->save();
                foreach ($rows as $index => [$body, $isCorrect]) {
                    $option = new QuestionOption;
                    $option->forceFill(['question_id' => $question->id, 'body_html' => e($body), 'is_correct' => $isCorrect, 'position' => $index + 1])->save();
                }
                $created++;
            }
        });
        $this->audit->record('ai.questions_generated', $user, 'question_bank', $bank->id, ['count' => $created, 'type' => $type]);

        return $created;
    }

    /**
     * Saran penilaian esai: skor & umpan balik (disimpan di attempt_answers.ai_suggestion).
     *
     * @return array{score: float, feedback: string}
     */
    public function essayFeedback(User $user, ExamAttempt $attempt, Question $question): array
    {
        $answer = AttemptAnswer::query()->where('exam_attempt_id', $attempt->id)->where('question_id', $question->id)->first();
        $text = $answer instanceof AttemptAnswer ? trim((string) $answer->text_answer) : '';
        if ($text === '') {
            throw new AiUnavailableException('Peserta tidak menjawab soal ini.');
        }
        $max = (float) $question->points;
        $rubric = is_array($question->rubric) ? collect($question->rubric)->map(fn ($c) => '- '.$c['name'].' (maks '.$c['max'].'): '.($c['description'] ?? ''))->implode("\n") : '';
        $result = $this->claude->json($user, 'essay_feedback', self::BASE_SYSTEM."\nAnda membantu trainer menilai jawaban esai. Nilai secara objektif terhadap pertanyaan".($rubric !== '' ? " dan rubrik berikut:\n".$rubric : '').". Keluaran: {\"score\": angka 0–{$max}, \"feedback\": umpan balik 2–4 kalimat untuk peserta (apa yang baik, apa yang kurang, saran perbaikan)".($rubric !== '' ? ', "rubric": {nama_kriteria: skor}' : '').'}', [['role' => 'user', 'content' => "Pertanyaan:\n".strip_tags($question->stem_html)."\n\nJawaban peserta:\n".$text]], 800);
        $suggestion = ['score' => max(0.0, min($max, round((float) ($result['score'] ?? 0), 1))), 'feedback' => Str::limit(trim((string) ($result['feedback'] ?? '')), 1500, ''), 'rubric' => is_array($result['rubric'] ?? null) ? $result['rubric'] : null, 'at' => now()->toIso8601String()];
        $answer->forceFill(['ai_suggestion' => $suggestion])->save();

        return ['score' => $suggestion['score'], 'feedback' => $suggestion['feedback']];
    }

    /**
     * Analisis kelas untuk trainer (cache 6 jam) dari data laporan kelas (ClassReportService::build).
     *
     * @param  ClassReport  $report
     */
    public function classInsight(User $user, CourseClass $class, array $report, bool $force = false): AiInsight
    {
        $cached = AiInsight::find('class_insight', $class->id);
        if ($cached instanceof AiInsight && ! $force && $cached->isFresh(6)) {
            return $cached;
        }
        $quiz = $report['quiz']->map(fn (array $q) => '- '.$q['assessment']->title.': '.(is_object($q['stats']) && isset($q['stats']->avg_score, $q['stats']->passed, $q['stats']->participants) ? 'rata-rata '.round((float) $q['stats']->avg_score, 1).', lolos '.(int) $q['stats']->passed.'/'.(int) $q['stats']->participants : 'belum dikerjakan'))->implode("\n");
        $hard = implode("\n", array_map(fn (array $l) => '- '.$l['title'].': selesai '.$l['completion'].'%, rata-rata '.round($l['avg_seconds'] / 60).' menit', $report['difficultLessons']));
        $hardQ = implode("\n", array_map(fn (array $q) => '- "'.Str::limit($q['stem'], 80).'": benar '.$q['correct_rate'].'% dari '.$q['answers'].' jawaban', $report['difficultQuestions']));
        $prompt = 'Kelas '.$class->batch_name.' — '.$class->program->name."\nPeserta: {$report['total']} (aktif {$report['active']}, lulus {$report['passed']}, tidak lulus {$report['failed']}), penyelesaian ".($report['completionRate'] ?? '—').'%, rata-rata skor '.($report['avgScore'] ?? '—').', rata-rata progres '.($report['avgProgress'] ?? '—')."%, aktif 7 hari: {$report['activeLast7']}, tidak aktif: ".count($report['inactive']).".\n\nHasil asesmen:\n".$quiz."\n\nMateri tersulit:\n".($hard ?: '- (belum ada data)')."\n\nSoal tersulit:\n".($hardQ ?: '- (belum ada data)');
        $markdown = $this->claude->complete($user, 'class_insight', self::BASE_SYSTEM."\nAnda menganalisis data kelas untuk trainer. Tulis dalam Markdown: (1) 3 temuan utama, (2) materi/soal yang perlu diperbaiki atau dijelaskan ulang dan mengapa, (3) 3 tindakan konkret minggu ini (mis. sesi review, pengingat peserta tertentu, perbaikan soal). Maksimal 250 kata.", [['role' => 'user', 'content' => $prompt]], 900);

        return AiInsight::put('class_insight', $class->id, $markdown, RichText::toHtml($markdown));
    }

    /**
     * Rancangan kurikulum: modul → bab → lesson teks (kerangka) dibuat langsung ke kelas sebagai draf.
     *
     * @return array{modules: int, lessons: int}
     */
    public function draftCurriculum(User $user, CourseClass $class, string $goals, int $moduleCount): array
    {
        $moduleCount = max(1, min(8, $moduleCount));
        $program = $class->program;
        $result = $this->claude->json($user, 'curriculum', self::BASE_SYSTEM."\nAnda merancang kurikulum pelatihan profesional. Format: {\"modules\": [{\"title\": string, \"chapters\": [{\"title\": string, \"lessons\": [{\"title\": string, \"outline\": string (Markdown 80–150 kata: tujuan, poin utama, aktivitas)}]}]}]}. Buat tepat {$moduleCount} modul, 1–3 bab per modul, 1–3 lesson per bab, berurutan dari dasar ke lanjut.", [['role' => 'user', 'content' => 'Program: '.$program->name.' ('.($program->category ?? '').($program->level ? ', level '.$program->level : '').', '.$program->duration_hours." jam)\nDeskripsi program: ".Str::limit(strip_tags((string) $program->description_html), 1500)."\n\nTujuan/permintaan trainer:\n".$goals]], 8192);
        $modules = is_array($result['modules'] ?? null) ? $result['modules'] : [];
        $counts = ['modules' => 0, 'lessons' => 0];
        DB::transaction(function () use ($modules, $class, &$counts): void {
            $modulePosition = (int) Module::query()->where('course_class_id', $class->id)->max('position');
            foreach (array_slice($modules, 0, 8) as $m) {
                if (! is_array($m) || ! is_string($m['title'] ?? null)) {
                    continue;
                }
                $module = new Module;
                $module->forceFill(['course_class_id' => $class->id, 'title' => Str::limit(trim($m['title']), 200, ''), 'position' => ++$modulePosition])->save();
                $counts['modules']++;
                $chapterPosition = 0;
                foreach (array_slice(is_array($m['chapters'] ?? null) ? $m['chapters'] : [], 0, 5) as $c) {
                    if (! is_array($c) || ! is_string($c['title'] ?? null)) {
                        continue;
                    }
                    $chapter = new Chapter;
                    $chapter->forceFill(['module_id' => $module->id, 'title' => Str::limit(trim($c['title']), 200, ''), 'position' => ++$chapterPosition])->save();
                    $lessonPosition = 0;
                    foreach (array_slice(is_array($c['lessons'] ?? null) ? $c['lessons'] : [], 0, 5) as $l) {
                        if (! is_array($l) || ! is_string($l['title'] ?? null)) {
                            continue;
                        }
                        $outline = is_string($l['outline'] ?? null) ? $l['outline'] : '';
                        $lesson = new Lesson;
                        $lesson->forceFill(['chapter_id' => $chapter->id, 'type' => 'text', 'title' => Str::limit(trim($l['title']), 200, ''), 'position' => ++$lessonPosition, 'is_required' => true,
                            'body_md' => "> Draf AI — tinjau dan lengkapi sebelum kelas dibuka.\n\n".$outline, 'body_html' => RichText::toHtml("> Draf AI — tinjau dan lengkapi sebelum kelas dibuka.\n\n".$outline)])->save();
                        $counts['lessons']++;
                    }
                }
            }
        });
        $this->audit->record('ai.curriculum_drafted', $user, 'course_class', $class->id, $counts);

        return $counts;
    }

    private static function lessonText(Lesson $lesson): string
    {
        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $lesson->body_html))) ?? '');

        return Str::limit($text, 12000, ' …');
    }

    private function enrollmentContext(Enrollment $enrollment): string
    {
        $enrollment->loadMissing('program', 'courseClass');
        $lessons = DB::table('lessons')->join('chapters', 'chapters.id', '=', 'lessons.chapter_id')->join('modules', 'modules.id', '=', 'chapters.module_id')
            ->where('modules.course_class_id', $enrollment->course_class_id)->orderBy('modules.position')->orderBy('chapters.position')->orderBy('lessons.position')
            ->get(['lessons.id', 'lessons.title', 'lessons.type', 'lessons.is_required', 'modules.title as module_title']);
        $done = DB::table('lesson_progress')->where('enrollment_id', $enrollment->id)->where('status', 'completed')->pluck('lesson_id')->flip();
        $list = $lessons->map(fn (object $l) => '- '.($done->has((string) $l->id) ? '[selesai] ' : '[belum] ').(string) $l->module_title.' › '.(string) $l->title.' ('.(string) $l->type.($l->is_required ? '' : ', opsional').')')->implode("\n");
        $attempts = DB::table('exam_attempts')->join('assessments', 'assessments.id', '=', 'exam_attempts.assessment_id')->where('exam_attempts.enrollment_id', $enrollment->id)->where('exam_attempts.status', '<>', 'voided')
            ->selectRaw('assessments.title, assessments.kind, assessments.passing_score, max(exam_attempts.score) as best, count(*) as tries, bool_or(exam_attempts.passed) as passed')->groupBy('assessments.title', 'assessments.kind', 'assessments.passing_score')->get()
            ->map(fn ($a) => '- '.$a->title.' ('.$a->kind.'): terbaik '.($a->best ?? '—').' dari KKM '.$a->passing_score.', '.$a->tries.' percobaan, '.($a->passed ? 'lulus' : 'belum lulus'))->implode("\n");

        return 'Program: '.$enrollment->program->name.' — kelas '.$enrollment->courseClass->batch_name."\nStatus: ".$enrollment->statusLabel().', progres '.$enrollment->progress_percent."%\n\nMateri:\n".$list."\n\nAsesmen:\n".($attempts !== '' ? $attempts : '- belum ada percobaan');
    }
}
