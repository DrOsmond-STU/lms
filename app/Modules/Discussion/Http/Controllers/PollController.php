<?php

declare(strict_types=1);

namespace App\Modules\Discussion\Http\Controllers;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Discussion\Models\Poll;
use App\Modules\Discussion\Models\PollVote;
use App\Modules\Discussion\Services\ClassMembership;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Notification\Services\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Polling kelas: dibuat trainer/admin, dijawab peserta, hasil tampil agregat. */
final class PollController
{
    public function __construct(private readonly ClassMembership $membership, private readonly Notifier $notifier, private readonly AuditLogger $audit) {}

    public function index(Request $request, CourseClass $class): View
    {
        /** @var User $user */
        $user = $request->user();
        $member = $this->membership->require($user, $class);
        $polls = Poll::query()->with('votes')->where('course_class_id', $class->id)->orderByDesc('created_at')->get();
        $mine = PollVote::query()->where('user_id', $user->id)->whereIn('poll_id', $polls->pluck('id'))->get()->keyBy('poll_id');

        return view('discussion.polls', ['class' => $class->load('program'), 'member' => $member, 'polls' => $polls, 'mine' => $mine]);
    }

    public function store(Request $request, CourseClass $class): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $member = $this->membership->require($user, $class);
        abort_unless($member['role'] === 'moderator', 403);
        $data = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:300'],
            'options' => ['required', 'array', 'min:2', 'max:10'], 'options.*' => ['nullable', 'string', 'max:200'],
            'is_anonymous' => ['nullable', 'boolean'], 'multiple' => ['nullable', 'boolean'], 'closes_at' => ['nullable', 'date'],
        ]);
        $options = array_values(array_filter(array_map(fn ($o) => trim((string) $o), $data['options']), fn (string $o) => $o !== ''));
        if (count($options) < 2) {
            throw ValidationException::withMessages(['options' => 'Isi minimal dua opsi.']);
        }
        $poll = new Poll;
        $poll->forceFill(['course_class_id' => $class->id, 'question' => trim($data['question']), 'options' => $options, 'is_anonymous' => (bool) ($data['is_anonymous'] ?? true),
            'multiple' => (bool) ($data['multiple'] ?? false), 'closes_at' => ! empty($data['closes_at']) ? $data['closes_at'] : null, 'created_by' => $user->id])->save();
        $this->audit->record('poll.created', $user, 'course_class', $class->id, ['poll_id' => $poll->id]);
        Enrollment::query()->with('user')->where('course_class_id', $class->id)->whereIn('status', Enrollment::ACTIVE)->get()
            ->each(fn (Enrollment $e) => $this->notifier->send($e->user, 'content', 'Polling baru', $poll->question, '/diskusi/kelas/'.$class->id.'/polling'));

        return back()->with('status', 'Polling dibuat.');
    }

    public function vote(Request $request, CourseClass $class, Poll $poll): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->membership->require($user, $class);
        abort_unless($poll->course_class_id === $class->id, 404);
        if (! $poll->isOpen()) {
            throw ValidationException::withMessages(['options' => 'Polling sudah ditutup.']);
        }
        $data = $request->validate(['options' => ['required', 'array', 'min:1'], 'options.*' => ['integer', 'min:0', 'max:'.(count($poll->options) - 1)]]);
        $indexes = array_values(array_unique(array_map('intval', $data['options'])));
        if (! $poll->multiple && count($indexes) > 1) {
            throw ValidationException::withMessages(['options' => 'Pilih satu opsi.']);
        }
        PollVote::query()->upsert([['id' => (string) Str::uuid7(), 'poll_id' => $poll->id, 'user_id' => $user->id, 'option_indexes' => json_encode($indexes), 'created_at' => now()]], ['poll_id', 'user_id'], ['option_indexes', 'created_at']);

        return back()->with('status', 'Suara Anda tersimpan.');
    }

    public function close(Request $request, CourseClass $class, Poll $poll): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $member = $this->membership->require($user, $class);
        abort_unless($member['role'] === 'moderator' && $poll->course_class_id === $class->id, 404);
        $poll->forceFill(['is_closed' => ! $poll->is_closed])->save();

        return back()->with('status', $poll->is_closed ? 'Polling ditutup.' : 'Polling dibuka kembali.');
    }

    public function destroy(Request $request, CourseClass $class, Poll $poll): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $member = $this->membership->require($user, $class);
        abort_unless($member['role'] === 'moderator' && $poll->course_class_id === $class->id, 404);
        $poll->delete();
        $this->audit->record('poll.deleted', $user, 'course_class', $class->id, ['poll_id' => $poll->id]);

        return back()->with('status', 'Polling dihapus.');
    }
}
