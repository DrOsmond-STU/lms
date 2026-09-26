<?php

declare(strict_types=1);

namespace App\Modules\Discussion\Http\Controllers;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Discussion\Models\DiscussionPost;
use App\Modules\Discussion\Models\DiscussionThread;
use App\Modules\Discussion\Services\ClassMembership;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Notification\Services\Notifier;
use App\Support\Content\RichText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Forum diskusi, tanya jawab, dan komentar materi per kelas. Satu set rute untuk peserta,
 * trainer, dan admin; peran moderasi ditentukan ClassMembership. Konten ditulis Markdown
 * dan disanitasi saat simpan; balasan pada utas terkunci ditolak; pelaporan konten oleh
 * peserta ditinjau moderator.
 */
final class DiscussionController
{
    public function __construct(
        private readonly ClassMembership $membership,
        private readonly Notifier $notifier,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request, CourseClass $class): View
    {
        $member = $this->member($request, $class);
        $kind = in_array($request->query('jenis'), ['discussion', 'question'], true) ? (string) $request->query('jenis') : 'discussion';
        $threads = DiscussionThread::query()->with('author:id,name')->where('course_class_id', $class->id)->where('kind', $kind)
            ->when($member['role'] === 'participant', fn (Builder $q) => $q->where('is_hidden', false))
            ->orderByDesc('is_pinned')->orderByDesc('last_post_at')->orderByDesc('created_at')->paginate(20)->withQueryString();
        $openReports = $member['role'] === 'moderator' ? DB::table('discussion_reports')->where('course_class_id', $class->id)->where('status', 'open')->count() : 0;

        return view('discussion.index', ['class' => $class->load('program'), 'member' => $member, 'kind' => $kind, 'threads' => $threads, 'openReports' => $openReports,
            'enabled' => $class->discussion_enabled]);
    }

    public function store(Request $request, CourseClass $class): RedirectResponse
    {
        $user = $this->user($request);
        $member = $this->member($request, $class);
        $this->assertEnabled($class);
        $data = $request->validate([
            'kind' => ['required', Rule::in(['discussion', 'question'])],
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'body' => ['required', 'string', 'min:2', 'max:20000'],
        ]);
        $thread = $this->createThread($class, $user, $data['kind'], null, $data['title'], $data['body']);
        if ($data['kind'] === 'question') {
            $this->notifyTrainers($class, 'Pertanyaan baru: '.$thread->title, $user->name.' mengajukan pertanyaan di kelas '.$class->batch_name.'.', $thread);
        }

        return redirect()->route('discussion.show', [$class, $thread])->with('status', 'Utas dibuat.');
    }

    /** Komentar pada materi (ditampilkan di halaman lesson). */
    public function storeComment(Request $request, CourseClass $class, Lesson $lesson): RedirectResponse
    {
        $user = $this->user($request);
        $this->member($request, $class);
        $this->assertEnabled($class);
        abort_unless($lesson->courseClassId() === $class->id, 404);
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:5000']]);
        $this->createThread($class, $user, 'comment', $lesson->id, null, $data['body']);
        $this->notifyTrainers($class, 'Komentar materi: '.$lesson->title, $user->name.' berkomentar pada materi "'.$lesson->title.'".', null, $lesson);

        return back()->with('status', 'Komentar dikirim.');
    }

    public function show(Request $request, CourseClass $class, DiscussionThread $thread): View
    {
        $member = $this->member($request, $class);
        abort_unless($thread->course_class_id === $class->id && ($member['role'] !== 'participant' || ! $thread->is_hidden), 404);
        $posts = DiscussionPost::query()->with('author:id,name')->where('thread_id', $thread->id)
            ->when($member['role'] === 'participant', fn (Builder $q) => $q->where('is_hidden', false))
            ->orderByDesc('is_answer')->orderBy('created_at')->get();

        return view('discussion.show', ['class' => $class->load('program'), 'member' => $member, 'thread' => $thread->load('author:id,name', 'lesson:id,title'), 'posts' => $posts]);
    }

    public function reply(Request $request, CourseClass $class, DiscussionThread $thread): RedirectResponse
    {
        $user = $this->user($request);
        $member = $this->member($request, $class);
        $this->assertEnabled($class);
        abort_unless($thread->course_class_id === $class->id, 404);
        if ($thread->is_locked && $member['role'] !== 'moderator') {
            throw ValidationException::withMessages(['body' => 'Utas ini dikunci.']);
        }
        $data = $request->validate(['body' => ['required', 'string', 'min:1', 'max:20000']]);

        $post = DB::transaction(function () use ($thread, $user, $data): DiscussionPost {
            $post = new DiscussionPost;
            $post->forceFill(['thread_id' => $thread->id, 'author_id' => $user->id, 'body' => $data['body'], 'body_html' => RichText::toHtml($data['body'])])->save();
            $thread->forceFill(['replies_count' => $thread->replies_count + 1, 'last_post_at' => now()])->save();

            return $post;
        });
        if ($thread->author_id !== $user->id) {
            $this->notifier->send($thread->author, 'content', 'Balasan baru: '.($thread->title ?? 'komentar Anda'), $user->name.' membalas utas Anda.', $this->threadPath($class, $thread));
        }
        if ($thread->kind === 'question' && $member['role'] === 'moderator') {
            // Balasan moderator pada pertanyaan otomatis ditandai sebagai jawaban bila belum ada.
            if (! DiscussionPost::query()->where('thread_id', $thread->id)->where('is_answer', true)->exists()) {
                $post->forceFill(['is_answer' => true])->save();
                $thread->forceFill(['is_resolved' => true])->save();
            }
        }

        return redirect()->to($this->threadPath($class, $thread))->with('status', 'Balasan dikirim.');
    }

    /** Penulis pertanyaan atau moderator menandai balasan sebagai jawaban. */
    public function markAnswer(Request $request, CourseClass $class, DiscussionThread $thread, DiscussionPost $post): RedirectResponse
    {
        $user = $this->user($request);
        $member = $this->member($request, $class);
        abort_unless($thread->course_class_id === $class->id && $post->thread_id === $thread->id && $thread->kind === 'question', 404);
        abort_unless($member['role'] === 'moderator' || $thread->author_id === $user->id, 403);
        DB::transaction(function () use ($thread, $post): void {
            DiscussionPost::query()->where('thread_id', $thread->id)->update(['is_answer' => false]);
            $post->forceFill(['is_answer' => true])->save();
            $thread->forceFill(['is_resolved' => true])->save();
        });
        if ($post->author_id !== $user->id) {
            $this->notifier->send($post->author, 'content', 'Balasan Anda ditandai sebagai jawaban', 'Pada pertanyaan "'.$thread->title.'".', $this->threadPath($class, $thread));
        }

        return back()->with('status', 'Ditandai sebagai jawaban.');
    }

    /** Moderasi: sematkan/kunci/sembunyikan utas, sembunyikan balasan. */
    public function moderate(Request $request, CourseClass $class, DiscussionThread $thread): RedirectResponse
    {
        $user = $this->user($request);
        $member = $this->member($request, $class);
        abort_unless($member['role'] === 'moderator', 403);
        abort_unless($thread->course_class_id === $class->id, 404);
        $data = $request->validate(['action' => ['required', Rule::in(['pin', 'unpin', 'lock', 'unlock', 'hide', 'unhide', 'hide_post', 'unhide_post'])], 'post_id' => ['nullable', 'uuid'], 'reason' => ['nullable', 'string', 'max:300']]);
        $action = $data['action'];
        if (in_array($action, ['hide_post', 'unhide_post'], true)) {
            $post = DiscussionPost::query()->where('thread_id', $thread->id)->findOrFail((string) ($data['post_id'] ?? ''));
            $post->forceFill(['is_hidden' => $action === 'hide_post', 'hidden_by' => $action === 'hide_post' ? $user->id : null, 'hidden_reason' => $action === 'hide_post' ? ($data['reason'] ?? null) : null])->save();
        } else {
            $thread->forceFill(match ($action) {
                'pin' => ['is_pinned' => true], 'unpin' => ['is_pinned' => false],
                'lock' => ['is_locked' => true], 'unlock' => ['is_locked' => false],
                'hide' => ['is_hidden' => true], default => ['is_hidden' => false],
            })->save();
        }
        DB::table('discussion_reports')->where('thread_id', $thread->id)->where('status', 'open')
            ->when(in_array($action, ['hide', 'hide_post'], true), fn ($q) => $q->update(['status' => 'resolved', 'resolved_by' => $user->id, 'resolved_at' => now(), 'updated_at' => now()]));
        $this->audit->record('discussion.moderated', $user, 'discussion_thread', $thread->id, ['action' => $action, 'post_id' => $data['post_id'] ?? null], $data['reason'] ?? null);

        return back()->with('status', 'Tindakan moderasi disimpan.');
    }

    /** Peserta melaporkan utas/balasan bermasalah. */
    public function report(Request $request, CourseClass $class, DiscussionThread $thread): RedirectResponse
    {
        $user = $this->user($request);
        $this->member($request, $class);
        abort_unless($thread->course_class_id === $class->id && $user->hasPermission('discussion.report'), 404);
        $data = $request->validate(['post_id' => ['nullable', 'uuid'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        $exists = DB::table('discussion_reports')->where('thread_id', $thread->id)->where('reporter_id', $user->id)->where('status', 'open')
            ->where('post_id', $data['post_id'] ?? null)->exists();
        if (! $exists) {
            DB::table('discussion_reports')->insert(['id' => (string) Str::uuid7(), 'course_class_id' => $class->id, 'thread_id' => $thread->id, 'post_id' => $data['post_id'] ?? null,
                'reporter_id' => $user->id, 'reason' => $data['reason'], 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
            $this->notifyTrainers($class, 'Laporan konten diskusi', $user->name.' melaporkan konten pada "'.($thread->title ?? 'komentar materi').'": '.Str::limit($data['reason'], 120), $thread);
        }

        return back()->with('status', 'Laporan dikirim ke trainer/moderator.');
    }

    public function chat(Request $request, CourseClass $class): View
    {
        $member = $this->member($request, $class);

        return view('discussion.chat', ['class' => $class->load('program'), 'member' => $member]);
    }

    /** Daftar laporan terbuka untuk moderator. */
    public function reports(Request $request, CourseClass $class): View
    {
        $member = $this->member($request, $class);
        abort_unless($member['role'] === 'moderator', 403);
        $reports = DB::table('discussion_reports')->join('users', 'users.id', '=', 'discussion_reports.reporter_id')
            ->join('discussion_threads', 'discussion_threads.id', '=', 'discussion_reports.thread_id')
            ->where('discussion_reports.course_class_id', $class->id)->orderByRaw("CASE WHEN discussion_reports.status = 'open' THEN 0 ELSE 1 END")->orderByDesc('discussion_reports.created_at')->limit(100)
            ->get(['discussion_reports.*', 'users.name as reporter_name', 'discussion_threads.title as thread_title', 'discussion_threads.kind as thread_kind']);

        return view('discussion.reports', ['class' => $class->load('program'), 'member' => $member, 'reports' => $reports]);
    }

    public function resolveReport(Request $request, CourseClass $class, string $report): RedirectResponse
    {
        $user = $this->user($request);
        $member = $this->member($request, $class);
        abort_unless($member['role'] === 'moderator', 403);
        DB::table('discussion_reports')->where('id', $report)->where('course_class_id', $class->id)->where('status', 'open')
            ->update(['status' => 'resolved', 'resolved_by' => $user->id, 'resolved_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'Laporan ditandai selesai.');
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /** @return array{role: 'participant'|'moderator'|'staff', workspace: string, enrollment: Enrollment|null} */
    private function member(Request $request, CourseClass $class): array
    {
        return $this->membership->require($this->user($request), $class);
    }

    private function assertEnabled(CourseClass $class): void
    {
        if (! $class->discussion_enabled) {
            throw ValidationException::withMessages(['body' => 'Forum diskusi kelas ini sedang dinonaktifkan.']);
        }
    }

    private function createThread(CourseClass $class, User $user, string $kind, ?string $lessonId, ?string $title, string $body): DiscussionThread
    {
        $thread = new DiscussionThread;
        $thread->forceFill([
            'course_class_id' => $class->id, 'lesson_id' => $lessonId, 'author_id' => $user->id, 'kind' => $kind,
            'title' => $title !== null ? trim($title) : null, 'body' => $body, 'body_html' => RichText::toHtml($body), 'last_post_at' => now(),
        ])->save();

        return $thread;
    }

    private function threadPath(CourseClass $class, DiscussionThread $thread): string
    {
        return '/diskusi/kelas/'.$class->id.'/utas/'.$thread->id;
    }

    private function notifyTrainers(CourseClass $class, string $title, string $body, ?DiscussionThread $thread, ?Lesson $lesson = null): void
    {
        $path = $thread !== null ? $this->threadPath($class, $thread) : '/kelola/kelas/'.$class->id;
        $class->trainers->each(fn (User $trainer) => $this->notifier->send($trainer, 'content', $title, $body, $path));
    }
}
