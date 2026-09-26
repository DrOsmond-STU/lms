<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Modules\Discussion\Models\ClassMessage;
use App\Modules\Discussion\Services\ClassMembership;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\CourseClass;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Obrolan kelas: pesan teks polos, disegarkan tiap 10 detik (polling, tanpa WebSocket),
 * batas laju per pengguna, moderator dapat menyembunyikan pesan.
 */
final class ClassChat extends Component
{
    #[Locked]
    public string $classId = '';

    #[Locked]
    public bool $moderator = false;

    public string $body = '';

    public function mount(CourseClass $class): void
    {
        /** @var User $user */
        $user = auth()->user();
        $member = app(ClassMembership::class)->require($user, $class);
        $this->classId = $class->id;
        $this->moderator = $member['role'] === 'moderator';
    }

    public function send(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $class = CourseClass::query()->findOrFail($this->classId);
        app(ClassMembership::class)->require($user, $class);
        if (! $class->chat_enabled) {
            throw ValidationException::withMessages(['body' => 'Obrolan kelas dinonaktifkan.']);
        }
        $this->validate(['body' => ['required', 'string', 'min:1', 'max:1000']]);
        $key = 'class-chat:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 20)) {
            throw ValidationException::withMessages(['body' => 'Terlalu banyak pesan. Tunggu sebentar.']);
        }
        RateLimiter::hit($key, 60);

        $message = new ClassMessage;
        $message->forceFill(['course_class_id' => $class->id, 'user_id' => $user->id, 'body' => trim(strip_tags($this->body)), 'created_at' => now()])->save();
        $this->body = '';
    }

    public function hide(string $messageId): void
    {
        /** @var User $user */
        $user = auth()->user();
        $class = CourseClass::query()->findOrFail($this->classId);
        $member = app(ClassMembership::class)->require($user, $class);
        abort_unless($member['role'] === 'moderator', 403);
        ClassMessage::query()->where('course_class_id', $class->id)->whereKey($messageId)->update(['is_hidden' => true]);
    }

    public function render(): View
    {
        $messages = ClassMessage::query()->with('user:id,name')->where('course_class_id', $this->classId)->where('is_hidden', false)
            ->orderByDesc('created_at')->limit(100)->get()->reverse()->values();

        return view('livewire.class-chat', ['messages' => $messages, 'me' => auth()->id()]);
    }
}
