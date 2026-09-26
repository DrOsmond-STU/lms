<?php

declare(strict_types=1);

use App\Livewire\AiTutor;
use App\Modules\Access\RoleCode;
use App\Modules\Assessment\Models\ExamAttempt;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\QuestionBank;
use App\Modules\Assessment\Services\AttemptService;
use App\Modules\Settings\Services\SystemSettings;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/*
 * Fitur AI (Claude): tutor, rangkuman, rekomendasi, jalur belajar, generator soal, saran esai,
 * analisis kelas, rancangan kurikulum. API dipalsukan; kunci disimpan terenkripsi; kuota harian.
 */

function claudeText(string $text): array
{
    return ['id' => 'msg_x', 'type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'text', 'text' => $text]], 'usage' => ['input_tokens' => 120, 'output_tokens' => 80]];
}

function enableAi(): void
{
    $admin = signIn(RoleCode::SuperAdmin);
    confirmAccess();
    test()->put(route('admin.settings.update', 'integrasi'), [
        SystemSettings::field('ai.enabled') => '1', SystemSettings::field('ai.api_key') => 'sk-ant-uji-rahasia', SystemSettings::field('ai.model') => 'claude-sonnet-5',
        SystemSettings::field('ai.daily_limit') => '5', SystemSettings::field('ai.tutor_enabled') => '1',
        SystemSettings::field('push.subject') => '', SystemSettings::field('push.vapid_public') => '', SystemSettings::field('whatsapp.endpoint') => '', SystemSettings::field('whatsapp.payload') => 'json', SystemSettings::field('whatsapp.sender') => '',
        SystemSettings::field('reminder.deadline_hours') => '48', SystemSettings::field('reminder.inactive_days') => '7', SystemSettings::field('reminder.new_program') => '1',
    ])->assertSessionHasNoErrors();
    expect(asSystem(fn () => (string) DB::table('system_settings')->where('key', 'ai.api_key')->value('value')))->not->toContain('sk-ant-uji');
    test()->post('/keluar');
    nextRequest();
}

it('serves participants with tutor, summary, recommendation and learning path under a daily quota', function () {
    Http::fake(function ($request) {
        expect($request->header('x-api-key')[0])->toBe('sk-ant-uji-rahasia')->and($request['model'])->toBe('claude-sonnet-5');
        $system = (string) $request['system'];
        if (str_contains($system, 'tutor')) {
            return Http::response(claudeText('Jawaban tutor: **konsep** dijelaskan.'));
        }
        if (str_contains($system, 'rangkuman')) {
            return Http::response(claudeText("- Poin satu\n- Poin dua"));
        }

        return Http::response(claudeText('Rekomendasi: lanjutkan Lesson 2.'));
    });
    enableAi();
    $course = makeCourse(['final' => false]);
    asSystem(fn () => $course['lessons'][0]->forceFill(['body_md' => str_repeat('Materi keamanan informasi menjelaskan kerahasiaan, integritas, dan ketersediaan. ', 4), 'body_html' => '<p>'.str_repeat('Materi keamanan informasi menjelaskan kerahasiaan, integritas, dan ketersediaan. ', 4).'</p>'])->save());
    $participant = signIn(RoleCode::Participant);
    $enrollment = enrollVia($course['class'], $participant);
    $lesson = $course['lessons'][0];

    $this->get(route('learning.lesson', [$enrollment, $lesson]))->assertOk()->assertSee('Rangkuman AI')->assertSee('Tutor AI');
    $this->post(route('ai.summarize', [$enrollment, $lesson]))->assertRedirect();
    $this->get(route('learning.lesson', [$enrollment, $lesson]))->assertOk()->assertSee('Poin satu');
    expect(asSystem(fn () => DB::table('lesson_summaries')->where('lesson_id', $lesson->id)->exists()))->toBeTrue();

    app(TenantContext::class)->applyFor($participant);
    Livewire::actingAs($participant)->test(AiTutor::class, ['enrollment' => $enrollment, 'lesson' => $lesson])
        ->set('question', 'Apa itu integritas?')->call('ask')->assertSee('konsep')->assertSet('question', '');
    app(TenantContext::class)->clear();
    expect(asSystem(fn () => DB::table('ai_conversations')->where('user_id', $participant->id)->count()))->toBe(1);

    $this->post(route('ai.recommend', $enrollment))->assertRedirect();
    $this->get(route('learning.classroom', $enrollment))->assertOk()->assertSee('Rekomendasi AI')->assertSee('lanjutkan Lesson 2');
    $this->post(route('learning.path.generate'))->assertRedirect();
    $this->get(route('learning.path'))->assertOk()->assertSee('Susun ulang');
    expect(asSystem(fn () => DB::table('ai_usages')->where('user_id', $participant->id)->where('status', 'ok')->count()))->toBe(4);

    // Kuota harian 5: permintaan ke-6 ditolak dengan pesan, bukan error.
    $this->post(route('learning.path.generate'))->assertRedirect();
    $this->post(route('learning.path.generate'))->assertSessionHas('status', 'Kuota asisten AI harian Anda habis. Coba lagi besok.');
    Http::assertSentCount(5);
});

it('helps trainers generate questions, suggest essay grades, analyse the class and draft a curriculum', function () {
    Http::fake(function ($request) {
        $system = (string) $request['system'];
        if (str_contains($system, 'bank soal')) {
            return Http::response(claudeText('```json'."\n".json_encode(['questions' => [
                ['stem' => 'Apa kepanjangan CIA dalam keamanan informasi?', 'options' => [['body' => 'Confidentiality, Integrity, Availability', 'correct' => true], ['body' => 'Central Intelligence Agency', 'correct' => false], ['body' => 'Control, Inspect, Audit', 'correct' => false], ['body' => 'Cipher, Index, Access', 'correct' => false]], 'explanation' => 'Tiga pilar keamanan.'],
                ['stem' => 'Soal rusak tanpa opsi benar', 'options' => [['body' => 'A', 'correct' => false], ['body' => 'B', 'correct' => false]]],
            ]])."\n```"));
        }
        if (str_contains($system, 'menilai jawaban esai')) {
            return Http::response(claudeText(json_encode(['score' => 8.5, 'feedback' => 'Jawaban cukup lengkap, tambahkan contoh.'])));
        }
        if (str_contains($system, 'kurikulum')) {
            return Http::response(claudeText(json_encode(['modules' => [['title' => 'Modul AI 1', 'chapters' => [['title' => 'Bab 1', 'lessons' => [['title' => 'Lesson AI', 'outline' => 'Tujuan: memahami dasar.']]]]]]])));
        }

        return Http::response(claudeText('## Temuan\n- Peserta aktif.'));
    });
    enableAi();
    $course = makeCourse(['final' => true]);
    $class = $course['class'];
    $participant = signIn(RoleCode::Participant);
    $enrollment = enrollVia($class, $participant);
    $this->post('/keluar');
    nextRequest();

    $admin = signIn(RoleCode::AcademicAdmin);
    $bank = asSystem(fn () => QuestionBank::query()->where('program_id', $course['program']->id)->firstOrFail());
    $this->get(route('banks.show', $bank))->assertOk()->assertSee('Buat soal dengan AI');
    $this->post(route('ai.questions', $bank), ['topic' => 'CIA triad dalam keamanan informasi', 'count' => 2, 'type' => 'single_choice', 'difficulty' => 3])->assertSessionHas('status', '1 soal draf AI ditambahkan (nonaktif). Tinjau, sunting, lalu aktifkan.');
    $draft = asSystem(fn () => Question::query()->where('question_bank_id', $bank->id)->where('is_active', false)->firstOrFail());
    expect($draft->competency_tags)->toBe(['draf-ai'])->and(asSystem(fn () => DB::table('question_options')->where('question_id', $draft->id)->where('is_correct', true)->count()))->toBe(1);

    // Esai: buat soal esai, peserta menjawab, admin minta saran.
    $essay = asSystem(function () use ($bank, $admin): Question {
        $q = new Question;
        $q->forceFill(['question_bank_id' => $bank->id, 'type' => 'essay', 'stem_md' => 'Jelaskan integritas data.', 'stem_html' => '<p>Jelaskan integritas data.</p>', 'difficulty' => 3, 'points' => 10, 'competency_tags' => [], 'is_active' => true, 'version' => 1, 'created_by' => $admin->id])->save();

        return $q;
    });
    asSystem(fn () => DB::table('assessments')->where('id', $course['final']->id)->update(['question_count' => 6]));
    $this->post('/keluar');
    nextRequest();
    loginAs($participant);
    nextRequest();
    completeLessons($enrollment, $course['lessons']);
    $this->post(route('exams.start', [$enrollment, $course['final']]))->assertRedirect();
    $attempt = asSystem(fn () => ExamAttempt::query()->where('enrollment_id', $enrollment->id)->firstOrFail());
    $service = app(AttemptService::class);
    $this->putJson(route('exams.answer', $attempt), ['question' => $service::alias($attempt, $essay->id), 'text' => 'Integritas data berarti data tidak diubah tanpa izin.'])->assertOk();
    $this->post(route('exams.submit', $attempt))->assertRedirect();
    $this->post('/keluar');
    nextRequest();

    loginAs($admin, enrollTotp($admin));
    nextRequest();
    $this->post(route('ai.essay-feedback', [$class, $attempt, $essay]))->assertSessionHas('status', 'Saran AI: 8,5 poin. Tinjau sebelum menyimpan nilai.');
    $this->get(route('assessments.grade', [$class, $attempt]))->assertOk()->assertSee('tambahkan contoh');

    $this->post(route('ai.class-insight', $class))->assertRedirect();
    $this->get(route('classes.report', $class))->assertOk()->assertSee('Materi Tersulit')->assertSee('Peserta aktif.');

    $this->post(route('ai.curriculum', $class), ['goals' => 'Peserta memahami dasar keamanan informasi', 'modules' => 1])->assertSessionHas('status', 'Draf kurikulum dibuat: 1 modul, 1 lesson teks. Tinjau dan lengkapi materinya.');
    expect(asSystem(fn () => DB::table('modules')->where('course_class_id', $class->id)->where('title', 'Modul AI 1')->exists()))->toBeTrue();
    $this->post('/keluar');
    nextRequest();

    // Peserta tidak boleh memakai fitur trainer.
    loginAs($participant);
    nextRequest();
    $this->post(route('ai.questions', $bank), ['topic' => 'x', 'count' => 1, 'type' => 'essay', 'difficulty' => 1])->assertForbidden();
});
