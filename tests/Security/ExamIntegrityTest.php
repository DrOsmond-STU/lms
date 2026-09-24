<?php

declare(strict_types=1);

use App\Modules\Access\RoleCode;
use App\Modules\Assessment\Models\ExamAttempt;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\QuestionOption;
use App\Modules\Assessment\Services\AttemptService;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => cache()->flush());

/** Peserta login, terdaftar, materi selesai; kembalikan [course, enrollment, participant]. */
function readyForFinal(array $options = []): array
{
    $course = makeCourse($options);
    $participant = signIn(RoleCode::Participant);
    $enrollment = enrollVia($course['class'], $participant);
    completeLessons($enrollment, $course['lessons']);

    return [$course, asSystem(fn () => $enrollment->fresh()), $participant];
}

function startFinal(array $course, $enrollment): ExamAttempt
{
    test()->post(route('exams.start', [$enrollment, $course['final']]))->assertRedirect();

    return asSystem(fn () => ExamAttempt::query()->where('enrollment_id', $enrollment->id)->where('status', 'in_progress')->firstOrFail());
}

it('locks the final exam until required lessons are complete', function () {
    $course = makeCourse();
    $participant = signIn(RoleCode::Participant);
    $enrollment = enrollVia($course['class'], $participant);

    $this->post(route('exams.start', [$enrollment, $course['final']]))->assertSessionHasErrors('assessment');
    expect(asSystem(fn () => ExamAttempt::query()->count()))->toBe(0);
})->group('FR-ASM-004');

it('never sends answer keys or real option ids to the browser', function () {
    [$course, $enrollment] = readyForFinal();
    $attempt = startFinal($course, $enrollment);

    $html = (string) $this->get(route('exams.take', $attempt))->assertOk()->getContent();
    $options = asSystem(fn () => QuestionOption::query()->whereIn('question_id', $attempt->question_order)->get());
    foreach ($options as $option) {
        expect($html)->not->toContain($option->id);
    }
    foreach ($attempt->question_order as $questionId) {
        expect($html)->not->toContain($questionId);
    }
    expect($html)->not->toContain('is_correct')->not->toContain('data-correct');
})->group('SEC-EXAM-01', 'SEC-EXAM-02');

it('keeps a single active attempt across tabs and does not reset the timer', function () {
    [$course, $enrollment] = readyForFinal();
    $first = startFinal($course, $enrollment);
    $this->travel(3)->minutes();
    $this->post(route('exams.start', [$enrollment, $course['final']]))->assertRedirect(route('exams.take', $first));

    expect(asSystem(fn () => ExamAttempt::query()->where('enrollment_id', $enrollment->id)->count()))->toBe(1)
        ->and(asSystem(fn () => $first->fresh()->deadline_at->equalTo($first->deadline_at)))->toBeTrue();
})->group('SEC-EXAM-06');

it('scores only on the server from stored answers', function () {
    [$course, $enrollment] = readyForFinal();
    $attempt = startFinal($course, $enrollment);

    // Upaya mengirim skor/status dari klien diabaikan.
    answerAttempt($attempt, correct: true);
    $this->post(route('exams.submit', $attempt), ['score' => 0, 'passed' => false]);

    $attempt = asSystem(fn () => $attempt->fresh());
    expect($attempt->status)->toBe('graded')
        ->and((float) $attempt->score)->toBe(100.0)
        ->and($attempt->passed)->toBeTrue()
        ->and(asSystem(fn () => $enrollment->fresh()->status))->toBe('pending_approval');
})->group('SEC-EXAM-04', 'FR-ENR-006');

it('rejects answers after the deadline and auto-submits expired attempts', function () {
    [$course, $enrollment] = readyForFinal();
    $attempt = startFinal($course, $enrollment);
    $service = app(AttemptService::class);
    $questionId = $attempt->question_order[0];
    $optionId = asSystem(fn () => QuestionOption::query()->where('question_id', $questionId)->where('is_correct', true)->value('id'));

    $this->travel(31)->minutes();
    $this->putJson(route('exams.answer', $attempt), ['question' => $service::alias($attempt, $questionId), 'options' => [$service::alias($attempt, $optionId)]])
        ->assertStatus(409);

    $this->artisan('stu:exams-auto-submit')->assertSuccessful();
    $attempt = asSystem(fn () => $attempt->fresh());
    expect($attempt->status)->toBe('graded')->and((float) $attempt->score)->toBe(0.0);
})->group('SEC-EXAM-05', 'FR-ASM-006');

it('rejects answers for questions or options outside the attempt', function () {
    [$course, $enrollment] = readyForFinal(['questions' => 8, 'final_count' => 3]);
    $attempt = startFinal($course, $enrollment);
    $service = app(AttemptService::class);
    $outside = asSystem(fn () => Question::query()->where('question_bank_id', $course['bank']->id)->whereNotIn('id', $attempt->question_order)->first());

    $this->putJson(route('exams.answer', $attempt), ['question' => $service::alias($attempt, $outside->id), 'options' => []])->assertStatus(422);
    $this->putJson(route('exams.answer', $attempt), ['question' => $service::alias($attempt, $attempt->question_order[0]), 'options' => [str_repeat('a', 20)]])->assertStatus(422);
})->group('SEC-EXAM-08');

it('enforces the attempt limit and fails the enrollment when attempts run out', function () {
    [$course, $enrollment] = readyForFinal(['max_attempts' => 1]);
    $attempt = startFinal($course, $enrollment);
    answerAttempt($attempt, correct: false);

    expect(asSystem(fn () => $enrollment->fresh()->status))->toBe('failed');
    $this->post(route('exams.start', [$enrollment, $course['final']]))->assertSessionHasErrors('assessment');
})->group('FR-ASM-003');

it('lets the class trainer grant an extra attempt with a reason, reviving the enrollment', function () {
    [$course, $enrollment, $participant] = readyForFinal(['max_attempts' => 1]);
    answerAttempt(startFinal($course, $enrollment), correct: false);
    $this->post('/keluar');
    nextRequest();

    $trainer = makeUser(RoleCode::Trainer);
    asSystem(fn () => DB::table('class_trainers')->insert(['course_class_id' => $course['class']->id, 'user_id' => $trainer->id, 'role' => 'lead']));
    loginAs($trainer, enrollTotp($trainer));
    nextRequest();
    $this->post(route('assessments.grant', [$course['class'], $course['final']]), ['enrollment_id' => $enrollment->id, 'extra_attempts' => 1, 'reason' => ''])->assertSessionHasErrors('reason');
    $this->post(route('assessments.grant', [$course['class'], $course['final']]), ['enrollment_id' => $enrollment->id, 'extra_attempts' => 1, 'reason' => 'Gangguan listrik saat ujian'])->assertRedirect();

    expect(asSystem(fn () => $enrollment->fresh()->status))->toBe('in_progress')
        ->and(DB::table('audit_logs')->where('action', 'assessment.attempt_granted')->exists())->toBeTrue();
})->group('FR-ASM-009', 'SEC-EXAM-09');

it('requires manual grading for essays by the class trainer only', function () {
    $course = makeCourse(['questions' => 0, 'final_count' => 1]);
    asSystem(function () use ($course) {
        $essay = new Question;
        $essay->forceFill(['question_bank_id' => $course['bank']->id, 'type' => 'essay', 'stem_md' => 'Jelaskan', 'stem_html' => '<p>Jelaskan</p>', 'difficulty' => 3, 'points' => 10, 'competency_tags' => []])->save();
    });
    $participant = signIn(RoleCode::Participant);
    $enrollment = enrollVia($course['class'], $participant);
    completeLessons($enrollment, $course['lessons']);
    $attempt = startFinal($course, asSystem(fn () => $enrollment->fresh()));
    $service = app(AttemptService::class);
    $this->putJson(route('exams.answer', $attempt), ['question' => $service::alias($attempt, $attempt->question_order[0]), 'text' => 'Jawaban esai saya'])->assertOk();
    $this->post(route('exams.submit', $attempt));
    expect(asSystem(fn () => $attempt->fresh()->status))->toBe('submitted');
    $this->post('/keluar');
    nextRequest();

    $outsider = makeUser(RoleCode::Trainer);
    loginAs($outsider, enrollTotp($outsider));
    nextRequest();
    $this->post(route('assessments.grade.store', [$course['class'], $attempt]), ['points' => [$attempt->question_order[0] => 10]])->assertNotFound();
    $this->post('/keluar');
    nextRequest();

    $trainer = makeUser(RoleCode::Trainer);
    asSystem(fn () => DB::table('class_trainers')->insert(['course_class_id' => $course['class']->id, 'user_id' => $trainer->id, 'role' => 'lead']));
    loginAs($trainer, enrollTotp($trainer));
    nextRequest();
    $this->post(route('assessments.grade.store', [$course['class'], $attempt]), ['points' => [$attempt->question_order[0] => 8]])->assertRedirect();
    $attempt = asSystem(fn () => $attempt->fresh());
    expect($attempt->status)->toBe('graded')->and((float) $attempt->score)->toBe(80.0);
})->group('FR-ASM-001', 'SEC-EXAM-19');

it('hides another participants attempt', function () {
    [$course, $enrollment] = readyForFinal();
    $attempt = startFinal($course, $enrollment);
    $this->post('/keluar');
    nextRequest();
    signIn(RoleCode::Participant);

    $this->get(route('exams.take', $attempt))->assertNotFound();
    $this->putJson(route('exams.answer', $attempt), ['question' => str_repeat('a', 20)])->assertNotFound();
})->group('SEC-AUTHZ-03');
