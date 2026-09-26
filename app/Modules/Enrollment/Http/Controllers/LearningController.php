<?php

declare(strict_types=1);

namespace App\Modules\Enrollment\Http\Controllers;

use App\Modules\Assessment\Models\Assessment;
use App\Modules\Assessment\Models\Assignment;
use App\Modules\Assessment\Models\AssignmentSubmission;
use App\Modules\Assessment\Models\ExamAttempt;
use App\Modules\Assessment\Services\AttemptService;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Services\CompletionEvaluator;
use App\Modules\Enrollment\Services\EnrollmentService;
use App\Modules\Enrollment\Services\ProgressService;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\AttendanceRecord;
use App\Modules\Learning\Models\ClassSession;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\Module;
use App\Modules\Learning\Services\LessonAvailability;
use App\Modules\Learning\Services\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Ruang belajar peserta (docs/08 PST-04/05, FR-ENR-008, FR-CNT-004/005). Enrollment milik
 * orang lain → 404; media hanya via URL bertanda tangan berumur pendek.
 */
final class LearningController
{
    public function __construct(
        private readonly ProgressService $progress,
        private readonly AttemptService $attempts,
        private readonly LessonAvailability $availability,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $enrollments = Enrollment::query()->with(['program:id,name,category,slug', 'courseClass:id,batch_name,starts_on,ends_on', 'payment:id,enrollment_id,status,expires_at'])
            ->where('user_id', $user->id)->orderByRaw("CASE WHEN status IN ('enrolled','in_progress') THEN 0 WHEN status = 'pending_approval' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')->get();

        return view('learning.index', ['enrollments' => $enrollments]);
    }

    public function classroom(Request $request, Enrollment $enrollment): View
    {
        $this->own($request, $enrollment);
        abort_unless($enrollment->canAccessContent() || $enrollment->status === 'failed', 404);
        $enrollment->load('program', 'courseClass.trainers:id,name');
        $modules = Module::query()->with('chapters.lessons')->where('course_class_id', $enrollment->course_class_id)->orderBy('position')->get();
        $done = DB::table('lesson_progress')->where('enrollment_id', $enrollment->id)->where('status', 'completed')->pluck('lesson_id')->flip();
        $assessments = Assessment::query()->where('course_class_id', $enrollment->course_class_id)->orderBy('kind')->orderBy('title')->get();
        $attemptStats = ExamAttempt::query()->where('enrollment_id', $enrollment->id)->where('status', '<>', 'voided')
            ->selectRaw('assessment_id, count(*) as used, max(score) as best, bool_or(passed) as passed, bool_or(status = \'in_progress\') as active')
            ->groupBy('assessment_id')->get()->keyBy('assessment_id');
        $ordered = $modules->flatMap(fn (Module $m) => $m->chapters->flatMap(fn ($c) => $c->lessons))->values();
        $sessions = ClassSession::query()->where('course_class_id', $enrollment->course_class_id)->where('ends_at', '>=', now()->subDay())->orderBy('starts_at')->limit(8)->get();

        return view('learning.classroom', [
            'enrollment' => $enrollment,
            'modules' => $modules,
            'done' => $done,
            'locks' => $this->availability->locks($enrollment, $ordered, $done->all()),
            'assignments' => Assignment::query()->where('course_class_id', $enrollment->course_class_id)->orderBy('position')->get(),
            'submissions' => AssignmentSubmission::query()->where('enrollment_id', $enrollment->id)->get()->keyBy('assignment_id'),
            'sessions' => $sessions,
            'attendance' => AttendanceRecord::query()->where('enrollment_id', $enrollment->id)->whereIn('class_session_id', $sessions->pluck('id'))->get()->keyBy('class_session_id'),
            'assessments' => $assessments,
            'attemptStats' => $attemptStats,
            'check' => CompletionEvaluator::check($enrollment),
            'remaining' => $assessments->mapWithKeys(fn (Assessment $a) => [$a->id => $this->attempts->remainingAttempts($enrollment, $a)]),
        ]);
    }

    public function lesson(Request $request, Enrollment $enrollment, Lesson $lesson, MediaStorage $media): View|RedirectResponse
    {
        $user = $this->own($request, $enrollment);
        abort_unless($enrollment->canAccessContent() && $lesson->courseClassId() === $enrollment->course_class_id, 404);
        if ($enrollment->isActive() && ($reason = $this->availability->lockReason($enrollment, $lesson)) !== null) {
            return redirect()->route('learning.classroom', $enrollment)->with('status', 'Materi "'.$lesson->title.'" masih terkunci. '.$reason);
        }
        $progress = $enrollment->isActive() ? $this->progress->open($enrollment, $lesson) : null;
        if ($enrollment->status === 'enrolled') {
            $enrollment->refresh();
        }

        $ordered = Lesson::query()->join('chapters', 'chapters.id', '=', 'lessons.chapter_id')->join('modules', 'modules.id', '=', 'chapters.module_id')
            ->where('modules.course_class_id', $enrollment->course_class_id)
            ->orderBy('modules.position')->orderBy('chapters.position')->orderBy('lessons.position')
            ->pluck('lessons.id')->values();
        $index = $ordered->search($lesson->id);

        $mediaUrl = null;
        if ($lesson->media !== null && $lesson->media->isServable()) {
            $mediaUrl = $media->signedUrl($lesson->media, $user, 'lesson:'.$lesson->id);
        }

        return view('learning.lesson', [
            'enrollment' => $enrollment->load('program', 'courseClass'),
            'lesson' => $lesson->load('assessment'),
            'progress' => $progress,
            'mediaUrl' => $mediaUrl,
            'previousId' => $index !== false && $index > 0 ? $ordered[$index - 1] : null,
            'nextId' => $index !== false && $index < $ordered->count() - 1 ? $ordered[$index + 1] : null,
        ]);
    }

    public function heartbeat(Request $request, Enrollment $enrollment, Lesson $lesson): JsonResponse
    {
        $this->own($request, $enrollment);
        abort_unless($enrollment->isActive(), 409);
        $this->assertUnlocked($enrollment, $lesson);
        $data = $request->validate(['position' => ['required', 'integer', 'between:0,86400']]);
        $progress = $this->progress->heartbeat($enrollment, $lesson, (int) $data['position']);
        if ($progress->wasChanged('status') && $progress->status === 'completed') {
            $this->progress->recalculate($enrollment);
        }

        return response()->json(['completed' => $progress->status === 'completed', 'watched' => $progress->watched_seconds]);
    }

    /** Ping aktivitas lesson non-media (durasi belajar). */
    public function ping(Request $request, Enrollment $enrollment, Lesson $lesson): JsonResponse
    {
        $this->own($request, $enrollment);
        abort_unless($enrollment->isActive() && $lesson->courseClassId() === $enrollment->course_class_id, 409);
        $progress = $this->progress->ping($enrollment, $lesson);

        return response()->json(['time_spent' => $progress->time_spent_seconds]);
    }

    public function complete(Request $request, Enrollment $enrollment, Lesson $lesson): RedirectResponse
    {
        $this->own($request, $enrollment);
        abort_unless($enrollment->isActive(), 409);
        $this->assertUnlocked($enrollment, $lesson);
        $this->progress->markComplete($enrollment, $lesson);
        $this->progress->recalculate($enrollment);

        return back()->with('status', 'Lesson ditandai selesai.');
    }

    /** Peserta membatalkan pendaftarannya sendiri (belum lulus/menunggu sertifikat); kuota kelas kembali. */
    public function cancel(Request $request, Enrollment $enrollment, EnrollmentService $enrollments): RedirectResponse
    {
        $user = $this->own($request, $enrollment);
        abort_unless($user->can('enrollment.cancel'), 403);
        if (! in_array($enrollment->status, ['enrolled', 'in_progress', 'awaiting_payment'], true)) {
            throw ValidationException::withMessages(['enrollment' => 'Pendaftaran dengan status '.$enrollment->statusLabel().' tidak dapat dibatalkan.']);
        }
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:300']]);
        $enrollments->cancel($enrollment->load('program', 'user'), $user, trim((string) ($data['reason'] ?? '')) ?: 'Dibatalkan peserta');

        return redirect()->route('learning.index')->with('status', 'Pendaftaran pada '.$enrollment->program->name.' dibatalkan.');
    }

    private function assertUnlocked(Enrollment $enrollment, Lesson $lesson): void
    {
        if ($lesson->courseClassId() !== $enrollment->course_class_id) {
            abort(404);
        }
        if (($reason = $this->availability->lockReason($enrollment, $lesson)) !== null) {
            throw ValidationException::withMessages(['lesson' => 'Materi terkunci. '.$reason]);
        }
    }

    private function own(Request $request, Enrollment $enrollment): User
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($enrollment->user_id === $user->id, 404);

        return $user;
    }
}
