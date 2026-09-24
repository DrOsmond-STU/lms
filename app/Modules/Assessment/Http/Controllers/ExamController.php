<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Http\Controllers;

use App\Modules\Assessment\Models\Assessment;
use App\Modules\Assessment\Models\AttemptAnswer;
use App\Modules\Assessment\Models\ExamAttempt;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Services\AttemptService;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pengerjaan kuis/ujian oleh peserta (docs/08 BARU-17, keamanan/08). Attempt milik orang
 * lain → 404. Endpoint submit tidak menerima skor/status — hanya jawaban.
 */
final class ExamController
{
    public function __construct(private readonly AttemptService $attempts) {}

    public function show(Request $request, Enrollment $enrollment, Assessment $assessment): View
    {
        $this->ownEnrollment($request, $enrollment);
        abort_unless($assessment->course_class_id === $enrollment->course_class_id, 404);
        $history = ExamAttempt::query()->where('enrollment_id', $enrollment->id)->where('assessment_id', $assessment->id)->orderByDesc('attempt_no')->get();

        return view('exams.show', [
            'enrollment' => $enrollment->load('program'),
            'assessment' => $assessment,
            'history' => $history,
            'active' => $this->attempts->activeAttempt($enrollment, $assessment),
            'blockReason' => $this->attempts->blockReason($enrollment, $assessment),
            'remaining' => $this->attempts->remainingAttempts($enrollment, $assessment),
        ]);
    }

    public function start(Request $request, Enrollment $enrollment, Assessment $assessment): RedirectResponse
    {
        $this->ownEnrollment($request, $enrollment);
        abort_unless($assessment->course_class_id === $enrollment->course_class_id, 404);
        $attempt = $this->attempts->start($enrollment, $assessment, $request->ip());

        return redirect()->route('exams.take', $attempt);
    }

    public function take(Request $request, ExamAttempt $attempt): View|RedirectResponse
    {
        $this->ownAttempt($request, $attempt);
        if (! $attempt->isInProgress()) {
            return redirect()->route('exams.result', $attempt);
        }
        if ($attempt->deadline_at->copy()->addSeconds(AttemptService::GRACE_SECONDS)->isPast()) {
            $this->attempts->submit($attempt, auto: true);

            return redirect()->route('exams.result', $attempt)->with('status', 'Waktu habis — jawaban dikumpulkan otomatis.');
        }

        return view('exams.take', [
            'attempt' => $attempt->load('assessment'),
            'items' => $this->attempts->payload($attempt),
        ]);
    }

    public function answer(Request $request, ExamAttempt $attempt): JsonResponse
    {
        $this->ownAttempt($request, $attempt);
        $data = $request->validate([
            'question' => ['required', 'string', 'size:20'],
            'options' => ['nullable', 'array', 'max:10'],
            'options.*' => ['string', 'size:20'],
            'text' => ['nullable', 'string', 'max:10000'],
        ]);
        $this->attempts->saveAnswer($attempt, $data['question'], array_values($data['options'] ?? []), $data['text'] ?? null);

        return response()->json(['saved' => true, 'seconds_left' => $attempt->secondsLeft()]);
    }

    public function integrity(Request $request, ExamAttempt $attempt): JsonResponse
    {
        $this->ownAttempt($request, $attempt);
        $data = $request->validate(['event' => ['required', 'string', 'in:blur,copy,paste']]);
        $this->attempts->recordIntegrity($attempt, $data['event']);

        return response()->json(['ok' => true]);
    }

    /** Kumpulkan: jawaban dari formulir (fallback tanpa JS) disimpan dulu, lalu dinilai server. */
    public function submit(Request $request, ExamAttempt $attempt): RedirectResponse
    {
        $this->ownAttempt($request, $attempt);
        $data = $request->validate(['answers' => ['nullable', 'array', 'max:200'], 'answers.*' => ['nullable'], 'texts' => ['nullable', 'array', 'max:200'], 'texts.*' => ['nullable', 'string', 'max:10000']]);

        if ($attempt->isInProgress() && $attempt->deadline_at->copy()->addSeconds(AttemptService::GRACE_SECONDS)->isFuture()) {
            foreach ($data['answers'] ?? [] as $alias => $options) {
                $this->attempts->saveAnswer($attempt, (string) $alias, array_values(array_map('strval', (array) $options)), null);
            }
            foreach ($data['texts'] ?? [] as $alias => $text) {
                $this->attempts->saveAnswer($attempt, (string) $alias, [], (string) $text);
            }
        }
        $this->attempts->submit($attempt, auto: ! $attempt->deadline_at->copy()->addSeconds(AttemptService::GRACE_SECONDS)->isFuture());

        return redirect()->route('exams.result', $attempt)->with('status', 'Jawaban dikumpulkan.');
    }

    public function result(Request $request, ExamAttempt $attempt): View
    {
        $this->ownAttempt($request, $attempt);
        $attempt->load('assessment', 'enrollment.program');
        $review = $attempt->status === 'graded' && $attempt->assessment->reviewAllowed();
        $questions = $review ? Question::query()->with('options')->whereIn('id', $attempt->question_order)->get()->keyBy('id') : collect();
        $answers = $review ? AttemptAnswer::query()->where('exam_attempt_id', $attempt->id)->get()->keyBy('question_id') : collect();

        return view('exams.result', compact('attempt', 'review', 'questions', 'answers'));
    }

    private function ownEnrollment(Request $request, Enrollment $enrollment): void
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($enrollment->user_id === $user->id, 404);
    }

    private function ownAttempt(Request $request, ExamAttempt $attempt): void
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($attempt->user_id === $user->id, 404);
    }
}
