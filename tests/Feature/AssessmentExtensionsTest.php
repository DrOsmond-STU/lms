<?php

declare(strict_types=1);

use App\Modules\Access\RoleCode;
use App\Modules\Assessment\Models\Assessment;
use App\Modules\Assessment\Models\Assignment;
use App\Modules\Assessment\Models\AssignmentSubmission;
use App\Modules\Assessment\Models\ExamAttempt;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Services\AttemptService;
use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
 * Fase asesmen: soal menjodohkan (kredit parsial), rubrik esai + umpan balik, pre-test/post-test,
 * tugas dengan pengumpulan berkas/teks, penilaian rubrik, dan syarat kelulusan.
 */

it('creates matching questions and grades them with partial credit', function () {
    $course = makeCourse(['questions' => 0, 'final' => false, 'quiz' => false]);
    $admin = signIn(RoleCode::AcademicAdmin);
    $this->post(route('questions.store', $course['bank']), ['type' => 'matching', 'stem_md' => 'Jodohkan istilah', 'difficulty' => 2, 'points' => 4,
        'pairs' => [['left' => 'HTTP', 'right' => 'Protokol web'], ['left' => 'SQL', 'right' => 'Bahasa kueri'], ['left' => 'CSS', 'right' => 'Gaya tampilan'], ['left' => 'TLS', 'right' => 'Enkripsi transport']]])
        ->assertSessionHasNoErrors();
    $this->post(route('questions.store', $course['bank']), ['type' => 'matching', 'stem_md' => 'Kurang', 'difficulty' => 2, 'points' => 1, 'pairs' => [['left' => 'A', 'right' => 'B']]])
        ->assertSessionHasErrors('pairs');
    $question = asSystem(fn () => Question::query()->where('type', 'matching')->with('options')->firstOrFail());
    expect($question->options)->toHaveCount(4)->and($question->options->first()->match_text)->toBe('Protokol web');

    // Kuis dari bank tersebut.
    $this->post(route('assessments.store', $course['class']), ['kind' => 'quiz', 'title' => 'Kuis Jodoh', 'question_bank_id' => $course['bank']->id, 'duration_minutes' => 10, 'max_attempts' => 2, 'cooldown_minutes' => 0,
        'question_count' => 1, 'passing_score' => 50, 'review_policy' => 'after_submit', 'shuffle_options' => '1', 'is_required' => '1'])->assertSessionHasNoErrors();
    $quiz = asSystem(fn () => Assessment::query()->where('title', 'Kuis Jodoh')->firstOrFail());
    $this->post('/keluar');
    nextRequest();

    $participant = signIn(RoleCode::Participant);
    $enrollment = enrollVia($course['class'], $participant);
    $this->post(route('exams.start', [$enrollment, $quiz]))->assertSessionHasNoErrors()->assertRedirect();
    $attempt = asSystem(fn () => ExamAttempt::query()->where('enrollment_id', $enrollment->id)->firstOrFail());
    $service = app(AttemptService::class);
    $item = $service->payload($attempt)[0];
    expect($item['type'])->toBe('matching')->and($item['targets'])->toHaveCount(4);
    $this->get(route('exams.take', $attempt))->assertOk()->assertSee('pilih pasangan');

    // 3 dari 4 pasangan benar → 75% poin (3 dari 4) = skor 75.
    $optionIds = $question->options->pluck('id')->all();
    $pairs = [];
    foreach ($optionIds as $i => $id) {
        $target = $i < 3 ? $id : $optionIds[0];
        $pairs[$service::alias($attempt, $id)] = $service::alias($attempt, 'target:'.$target);
    }
    $this->putJson(route('exams.answer', $attempt), ['question' => $item['alias'], 'options' => [], 'pairs' => $pairs])->assertOk();
    $this->post(route('exams.submit', $attempt))->assertRedirect(route('exams.result', $attempt));
    $attempt = asSystem(fn () => $attempt->fresh());
    expect((float) $attempt->score)->toBe(75.0)->and($attempt->passed)->toBeTrue();
    $this->get(route('exams.result', $attempt))->assertOk()->assertSee('kredit parsial');
});

it('grades essays with a rubric and feedback, and keeps pre-tests out of graduation', function () {
    $course = makeCourse(['questions' => 0, 'final' => false]);
    $admin = signIn(RoleCode::AcademicAdmin);
    $this->post(route('questions.store', $course['bank']), ['type' => 'essay', 'stem_md' => 'Jelaskan CIA triad', 'difficulty' => 3, 'points' => 10,
        'rubric' => [['name' => 'Ketepatan', 'max' => 6, 'description' => 'Konsep benar'], ['name' => 'Kejelasan', 'max' => 4]]])->assertSessionHasNoErrors();
    $essay = asSystem(fn () => Question::query()->where('type', 'essay')->firstOrFail());
    expect($essay->rubric)->toHaveCount(2);

    // Pre-test tidak pernah wajib walau dicentang.
    $this->post(route('assessments.store', $course['class']), ['kind' => 'pretest', 'title' => 'Pre-test Awal', 'question_bank_id' => $course['bank']->id, 'duration_minutes' => 10, 'max_attempts' => 1, 'cooldown_minutes' => 0,
        'question_count' => 1, 'passing_score' => 0, 'review_policy' => 'never', 'is_required' => '1'])->assertSessionHasNoErrors();
    $pretest = asSystem(fn () => Assessment::query()->where('kind', 'pretest')->firstOrFail());
    expect($pretest->is_required)->toBeFalse();
    $this->post(route('assessments.store', $course['class']), ['kind' => 'posttest', 'title' => 'Post-test', 'question_bank_id' => $course['bank']->id, 'duration_minutes' => 10, 'max_attempts' => 2, 'cooldown_minutes' => 0,
        'question_count' => 1, 'passing_score' => 60, 'review_policy' => 'after_submit', 'is_required' => '1'])->assertSessionHasNoErrors();
    $posttest = asSystem(fn () => Assessment::query()->where('kind', 'posttest')->firstOrFail());
    $this->post('/keluar');
    nextRequest();

    $participant = signIn(RoleCode::Participant);
    $enrollment = enrollVia($course['class'], $participant);
    $this->post(route('exams.start', [$enrollment, $posttest]))->assertRedirect();
    $attempt = asSystem(fn () => ExamAttempt::query()->where('enrollment_id', $enrollment->id)->where('assessment_id', $posttest->id)->firstOrFail());
    $alias = AttemptService::alias($attempt, $essay->id);
    $this->putJson(route('exams.answer', $attempt), ['question' => $alias, 'options' => [], 'text' => 'Kerahasiaan, integritas, ketersediaan.'])->assertOk();
    $this->post(route('exams.submit', $attempt))->assertRedirect();
    expect(asSystem(fn () => $attempt->fresh()->status))->toBe('submitted');
    $this->post('/keluar');
    nextRequest();

    loginAs($admin, enrollTotp($admin));
    nextRequest();
    $this->get(route('assessments.grade', [$course['class'], $attempt]))->assertOk()->assertSee('Ketepatan')->assertSee('Umpan balik');
    $this->post(route('assessments.grade.store', [$course['class'], $attempt]), ['rubric' => [$essay->id => ['Ketepatan' => 6, 'Kejelasan' => 2]], 'feedback' => [$essay->id => 'Bagus, perjelas contoh.']])->assertSessionHasNoErrors();
    $attempt = asSystem(fn () => $attempt->fresh());
    expect((float) $attempt->score)->toBe(80.0)->and($attempt->passed)->toBeTrue();
    $answer = asSystem(fn () => DB::table('attempt_answers')->where('exam_attempt_id', $attempt->id)->first());
    expect((float) $answer->points_awarded)->toBe(8.0)->and($answer->feedback)->toBe('Bagus, perjelas contoh.');
    $this->post('/keluar');
    nextRequest();

    loginAs($participant);
    nextRequest();
    $this->get(route('exams.result', $attempt))->assertOk()->assertSee('Umpan balik trainer')->assertSee('Ketepatan: 6');
    // Post-test wajib lulus + lesson wajib → kelulusan (pre-test tidak menghalangi walau belum dikerjakan).
    completeLessons($enrollment, $course['lessons']);
    expect(asSystem(fn () => Enrollment::query()->whereKey($enrollment->id)->value('status')))->toBe('pending_approval');
});

it('runs the assignment flow: create, submit file and text, grade with rubric, and require it for graduation', function () {
    Storage::fake('local');
    $course = makeCourse(['final' => false]);
    $admin = signIn(RoleCode::AcademicAdmin);
    $due = now()->addDays(3);
    $this->post(route('assignments.store', $course['class']), ['title' => 'Laporan Praktikum', 'instructions_md' => 'Unggah **laporan** Anda.', 'due_at' => $due->format('Y-m-d H:i'), 'max_score' => 100, 'passing_score' => 60,
        'is_required' => '1', 'allow_text' => '1', 'allow_file' => '1', 'rubric' => [['name' => 'Isi', 'max' => 70], ['name' => 'Format', 'max' => 30]]])->assertSessionHasNoErrors();
    $this->post(route('assignments.store', $course['class']), ['title' => 'Tanpa bentuk', 'max_score' => 10])->assertSessionHasErrors('allow_text');
    $assignment = asSystem(fn () => Assignment::query()->where('title', 'Laporan Praktikum')->firstOrFail());
    $this->get(route('classes.assignments', $course['class']))->assertOk()->assertSee('Laporan Praktikum');
    $this->post('/keluar');
    nextRequest();

    $participant = signIn(RoleCode::Participant);
    $enrollment = enrollVia($course['class'], $participant);
    $this->get(route('learning.classroom', $enrollment))->assertOk()->assertSee('Laporan Praktikum')->assertSee('Tugas wajib dinilai: 0/1');
    $this->get(route('participant.dashboard'))->assertOk()->assertSee('Tugas: Laporan Praktikum');
    $this->post(route('assignments.submit', [$enrollment, $assignment]), [])->assertSessionHasErrors('submission');
    $this->post(route('assignments.submit', [$enrollment, $assignment]), ['text_answer' => 'Ringkasan laporan.', 'file' => UploadedFile::fake()->createWithContent('laporan.pdf', "%PDF-1.4\n%uji\n")])->assertSessionHasNoErrors();
    $submission = asSystem(fn () => AssignmentSubmission::query()->where('assignment_id', $assignment->id)->firstOrFail());
    expect($submission->status)->toBe('submitted')->and($submission->media_asset_id)->not->toBeNull()->and($submission->is_late)->toBeFalse();
    $this->get(route('assignments.show', [$enrollment, $assignment]))->assertOk()->assertSee('Menunggu penilaian')->assertSee('Unduh berkas saya');
    // Materi wajib selesai tetapi tugas wajib belum dinilai → belum lulus.
    completeLessons($enrollment, $course['lessons']);
    expect(asSystem(fn () => Enrollment::query()->whereKey($enrollment->id)->value('status')))->toBe('in_progress');
    $this->post('/keluar');
    nextRequest();

    loginAs($admin, enrollTotp($admin));
    nextRequest();
    $this->get(route('assignments.submissions', [$course['class'], $assignment]))->assertOk()->assertSee('Ringkasan laporan.')->assertSee('Unduh berkas');
    // Dikembalikan untuk revisi → peserta dapat mengumpulkan ulang.
    $this->post(route('assignments.grade', [$course['class'], $submission]), ['action' => 'return', 'feedback' => 'Lengkapi bagian metode.'])->assertSessionHasNoErrors();
    expect(asSystem(fn () => $submission->fresh()->status))->toBe('returned');
    $this->post('/keluar');
    nextRequest();

    loginAs($participant);
    nextRequest();
    $this->get(route('assignments.show', [$enrollment, $assignment]))->assertOk()->assertSee('Lengkapi bagian metode.');
    $this->post(route('assignments.submit', [$enrollment, $assignment]), ['text_answer' => 'Ringkasan laporan revisi.'])->assertSessionHasNoErrors();
    expect(asSystem(fn () => $submission->fresh()->version))->toBe(2);
    $this->post('/keluar');
    nextRequest();

    loginAs($admin, enrollTotp($admin));
    nextRequest();
    $this->post(route('assignments.grade', [$course['class'], $submission]), ['rubric' => ['Isi' => 60, 'Format' => 25], 'feedback' => 'Baik.'])->assertSessionHasNoErrors();
    $submission = asSystem(fn () => $submission->fresh());
    expect($submission->status)->toBe('graded')->and((float) $submission->score)->toBe(85.0)->and($submission->rubric_scores)->toEqual(['Isi' => 60, 'Format' => 25]);
    // Tugas wajib dinilai ≥ 60 → syarat kelulusan terpenuhi.
    expect(asSystem(fn () => Enrollment::query()->whereKey($enrollment->id)->value('status')))->toBe('pending_approval');
    // Sudah dinilai → tidak dapat dikumpulkan ulang; tugas tidak dapat dihapus.
    $this->delete(route('assignments.destroy', [$course['class'], $assignment]))->assertSessionHasErrors('assignment');
    $this->post('/keluar');
    nextRequest();
    loginAs($participant);
    nextRequest();
    $this->post(route('assignments.submit', [$enrollment, $assignment]), ['text_answer' => 'lagi'])->assertSessionHasErrors('submission');
    $this->get(route('assignments.show', [$enrollment, $assignment]))->assertOk()->assertSee('85');
});
