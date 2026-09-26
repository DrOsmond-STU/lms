<?php

declare(strict_types=1);

namespace App\Modules\Ai\Http\Controllers;

use App\Modules\Ai\Models\AiInsight;
use App\Modules\Ai\Services\AiAssistant;
use App\Modules\Ai\Services\AiUnavailableException;
use App\Modules\Ai\Services\ClaudeClient;
use App\Modules\Assessment\Models\ExamAttempt;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\QuestionBank;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Services\ClassAccess;
use App\Modules\Reporting\Services\ClassReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Titik masuk fitur AI: peserta (rangkuman, rekomendasi, jalur belajar) dan trainer/admin (soal, esai, analisis, kurikulum). */
final class AiController
{
    public function __construct(private readonly AiAssistant $ai, private readonly ClassAccess $access) {}

    public function summarize(Request $request, Enrollment $enrollment, Lesson $lesson): RedirectResponse
    {
        $user = $this->participant($request, $enrollment);
        abort_unless($lesson->courseClassId() === $enrollment->course_class_id, 404);

        return $this->attempt(fn () => $this->ai->summarize($user, $lesson, $request->boolean('ulang')), 'Rangkuman AI dibuat.');
    }

    public function recommend(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $user = $this->participant($request, $enrollment);

        return $this->attempt(fn () => $this->ai->recommend($user, $enrollment, true), 'Rekomendasi belajar diperbarui.');
    }

    public function learningPath(Request $request): View
    {
        $user = $this->user($request);

        return view('learning.path', [
            'insight' => AiInsight::find('learning_path', $user->id),
            'configured' => ClaudeClient::configured(),
            'remaining' => ClaudeClient::configured() ? ClaudeClient::remainingToday($user) : 0,
        ]);
    }

    public function generateLearningPath(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        return $this->attempt(fn () => $this->ai->learningPath($user, true), 'Jalur belajar disusun.');
    }

    public function generateQuestions(Request $request, QuestionBank $bank): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($user->hasPermission('ai.author') && $this->access->canManageQuestionBank($user, $bank->program), 404);
        $data = $request->validate([
            'topic' => ['required', 'string', 'min:10', 'max:4000'],
            'count' => ['required', 'integer', 'min:1', 'max:10'],
            'type' => ['required', Rule::in(['single_choice', 'multiple_choice', 'true_false', 'essay'])],
            'difficulty' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        return $this->attempt(function () use ($user, $bank, $data): string {
            $count = $this->ai->generateQuestions($user, $bank, $data['topic'], (int) $data['count'], $data['type'], (int) $data['difficulty']);

            return $count.' soal draf AI ditambahkan (nonaktif). Tinjau, sunting, lalu aktifkan.';
        });
    }

    public function essayFeedback(Request $request, CourseClass $class, ExamAttempt $attempt, Question $question): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($user->hasPermission('ai.author') && $this->access->canManageContent($user, $class, 'assessment.grade_manual') && $attempt->course_class_id === $class->id, 404);
        abort_unless($question->type === 'essay' && in_array($question->id, $attempt->question_order, true), 404);

        return $this->attempt(function () use ($user, $attempt, $question): string {
            $result = $this->ai->essayFeedback($user, $attempt, $question);

            return 'Saran AI: '.fmt_score($result['score']).' poin. Tinjau sebelum menyimpan nilai.';
        });
    }

    public function classInsight(Request $request, CourseClass $class, ClassReportService $reports): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($user->hasPermission('ai.author') && $this->access->canView($user, $class), 404);
        $class->load('program');

        return $this->attempt(fn () => $this->ai->classInsight($user, $class, $reports->build($class), true), 'Analisis AI diperbarui.');
    }

    public function draftCurriculum(Request $request, CourseClass $class): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($user->hasPermission('ai.author') && $this->access->canManageContent($user, $class, 'content.create'), 404);
        $data = $request->validate(['goals' => ['required', 'string', 'min:10', 'max:4000'], 'modules' => ['required', 'integer', 'min:1', 'max:8']]);
        $class->load('program');

        return $this->attempt(function () use ($user, $class, $data): string {
            $counts = $this->ai->draftCurriculum($user, $class, $data['goals'], (int) $data['modules']);

            return 'Draf kurikulum dibuat: '.$counts['modules'].' modul, '.$counts['lessons'].' lesson teks. Tinjau dan lengkapi materinya.';
        });
    }

    /** @param  \Closure(): (string|object)  $action */
    private function attempt(\Closure $action, string $message = 'Selesai.'): RedirectResponse
    {
        try {
            $result = $action();
        } catch (AiUnavailableException $e) {
            return back()->with('status', $e->getMessage());
        }

        return back()->with('status', is_string($result) ? $result : $message);
    }

    private function participant(Request $request, Enrollment $enrollment): User
    {
        $user = $this->user($request);
        abort_unless($enrollment->user_id === $user->id && $enrollment->canAccessContent() && $user->hasPermission('ai.use'), 404);

        return $user;
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
