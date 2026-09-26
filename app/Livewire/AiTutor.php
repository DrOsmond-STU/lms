<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Modules\Ai\Services\AiAssistant;
use App\Modules\Ai\Services\AiUnavailableException;
use App\Modules\Ai\Services\ClaudeClient;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\Lesson;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Tutor AI pada halaman materi: tanya jawab berbasis isi lesson, riwayat per peserta. */
final class AiTutor extends Component
{
    #[Locked]
    public string $enrollmentId = '';

    #[Locked]
    public string $lessonId = '';

    public string $question = '';

    /** @var list<array{role: string, content: string}> */
    public array $messages = [];

    public ?string $error = null;

    public function mount(Enrollment $enrollment, Lesson $lesson): void
    {
        /** @var User $user */
        $user = auth()->user();
        abort_unless($enrollment->user_id === $user->id, 404);
        $this->enrollmentId = $enrollment->id;
        $this->lessonId = $lesson->id;
        $this->messages = array_map(fn (array $m) => ['role' => $m['role'], 'content' => $m['content']], app(AiAssistant::class)->tutorHistory($user, $enrollment, $lesson));
    }

    public function ask(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $this->validate(['question' => ['required', 'string', 'min:2', 'max:1500']]);
        $enrollment = Enrollment::query()->where('user_id', $user->id)->findOrFail($this->enrollmentId);
        $lesson = Lesson::query()->findOrFail($this->lessonId);
        abort_unless($enrollment->canAccessContent() && $lesson->courseClassId() === $enrollment->course_class_id && $user->hasPermission('ai.use'), 404);
        $key = 'ai-tutor:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->error = 'Terlalu banyak pertanyaan dalam semenit. Tunggu sebentar.';

            return;
        }
        RateLimiter::hit($key, 60);
        $question = trim($this->question);
        $this->error = null;
        try {
            $reply = app(AiAssistant::class)->tutorReply($user, $enrollment->load('program'), $lesson, $question);
        } catch (AiUnavailableException $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->messages[] = ['role' => 'user', 'content' => $question];
        $this->messages[] = ['role' => 'assistant', 'content' => $reply];
        $this->messages = array_slice($this->messages, -20);
        $this->question = '';
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('livewire.ai-tutor', ['remaining' => ClaudeClient::remainingToday($user)]);
    }
}
