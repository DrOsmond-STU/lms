<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Http\Controllers;

use App\Modules\Assessment\Models\Assignment;
use App\Modules\Assessment\Models\AssignmentSubmission;
use App\Modules\Assessment\Services\AssignmentService;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Learning\Services\ClassAccess;
use App\Modules\Learning\Services\MediaStorage;
use App\Modules\Notification\Services\Notifier;
use App\Support\Content\RichText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Tugas kelas: kelola (trainer/admin), daftar pengumpulan, dan penilaian. */
final class AssignmentManageController
{
    public function __construct(
        private readonly ClassAccess $access,
        private readonly AuditLogger $audit,
        private readonly AssignmentService $assignments,
    ) {}

    public function index(Request $request, CourseClass $class): View
    {
        $user = $this->authorize($request, $class, 'assignment.view');
        $class->load('program', 'trainers');
        $assignments = Assignment::query()->withCount(['submissions', 'submissions as pending_count' => fn ($q) => $q->where('status', 'submitted')])
            ->where('course_class_id', $class->id)->orderBy('position')->orderBy('created_at')->get();

        return view('classes.assignments', $this->common($user, $class) + ['tab' => 'assignments', 'assignments' => $assignments]);
    }

    public function create(Request $request, CourseClass $class): View
    {
        $user = $this->authorize($request, $class, 'assignment.create');
        $class->load('program');

        return view('classes.assignment-form', ['class' => $class, 'assignment' => (new Assignment)->forceFill(['max_score' => 100, 'is_required' => true, 'allow_text' => true, 'allow_file' => true, 'allow_late' => false]), 'workspace' => $this->access->workspaceFor($user)]);
    }

    public function store(Request $request, CourseClass $class): RedirectResponse
    {
        $user = $this->authorize($request, $class, 'assignment.create');
        $assignment = new Assignment;
        $assignment->forceFill($this->validated($request) + ['course_class_id' => $class->id, 'created_by' => $user->id, 'position' => (int) Assignment::query()->where('course_class_id', $class->id)->max('position') + 1])->save();
        $this->audit->record('assignment.created', $user, 'course_class', $class->id, ['assignment_id' => $assignment->id, 'title' => $assignment->title]);
        Enrollment::query()->with('user')->where('course_class_id', $class->id)->whereIn('status', Enrollment::ACTIVE)->get()
            ->each(fn (Enrollment $e) => app(Notifier::class)->send($e->user, 'content', 'Tugas baru: '.$assignment->title,
                $assignment->due_at !== null ? 'Tenggat '.$assignment->due_at->timezone(display_tz())->translatedFormat('d M Y H:i').' '.tz_label().'.' : 'Tanpa tenggat.', '/peserta/kelas/'.$e->id.'/tugas/'.$assignment->id));

        return redirect()->route('classes.assignments', $class)->with('status', 'Tugas "'.$assignment->title.'" dibuat.');
    }

    public function edit(Request $request, CourseClass $class, Assignment $assignment): View
    {
        $user = $this->authorize($request, $class, 'assignment.update');
        abort_unless($assignment->course_class_id === $class->id, 404);
        $class->load('program');

        return view('classes.assignment-form', ['class' => $class, 'assignment' => $assignment, 'workspace' => $this->access->workspaceFor($user)]);
    }

    public function update(Request $request, CourseClass $class, Assignment $assignment): RedirectResponse
    {
        $user = $this->authorize($request, $class, 'assignment.update');
        abort_unless($assignment->course_class_id === $class->id, 404);
        $assignment->forceFill($this->validated($request))->save();
        $this->audit->record('assignment.updated', $user, 'course_class', $class->id, ['assignment_id' => $assignment->id]);

        return redirect()->route('classes.assignments', $class)->with('status', 'Tugas diperbarui.');
    }

    public function destroy(Request $request, CourseClass $class, Assignment $assignment): RedirectResponse
    {
        $user = $this->authorize($request, $class, 'assignment.delete');
        abort_unless($assignment->course_class_id === $class->id, 404);
        if (AssignmentSubmission::query()->where('assignment_id', $assignment->id)->exists()) {
            throw ValidationException::withMessages(['assignment' => 'Tugas yang sudah dikumpulkan peserta tidak dapat dihapus. Jadikan tidak wajib.']);
        }
        $assignment->delete();
        $this->audit->record('assignment.deleted', $user, 'course_class', $class->id, ['assignment_id' => $assignment->id]);

        return redirect()->route('classes.assignments', $class)->with('status', 'Tugas dihapus.');
    }

    public function submissions(Request $request, CourseClass $class, Assignment $assignment, MediaStorage $media): View
    {
        $user = $this->authorize($request, $class, 'submission.view_any');
        abort_unless($assignment->course_class_id === $class->id, 404);
        $class->load('program', 'trainers');
        $enrollments = Enrollment::query()->with('user:id,name,email')->where('course_class_id', $class->id)->whereNotIn('status', ['cancelled', 'awaiting_payment'])->get()->sortBy(fn (Enrollment $e) => $e->user->name)->values();
        $submissions = AssignmentSubmission::query()->with('media')->where('assignment_id', $assignment->id)->get()->keyBy('enrollment_id');
        $fileUrls = collect();
        foreach ($submissions as $submission) {
            $asset = $submission->media;
            if ($asset !== null && $asset->isServable()) {
                $fileUrls->put($submission->id, $media->signedUrl($asset, $user, 'submission:'.$submission->id));
            }
        }

        return view('classes.submissions', $this->common($user, $class) + ['tab' => 'assignments', 'assignment' => $assignment, 'enrollments' => $enrollments, 'submissions' => $submissions, 'fileUrls' => $fileUrls,
            'canGrade' => $this->access->canManageContent($user, $class, 'submission.review')]);
    }

    public function grade(Request $request, CourseClass $class, AssignmentSubmission $submission): RedirectResponse
    {
        $user = $this->authorize($request, $class, 'submission.review');
        abort_unless($submission->course_class_id === $class->id, 404);
        $data = $request->validate([
            'score' => ['nullable', 'numeric', 'min:0', 'max:'.$submission->assignment->max_score],
            'feedback' => ['nullable', 'string', 'max:5000'],
            'rubric' => ['nullable', 'array'],
            'rubric.*' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'action' => ['nullable', 'in:grade,return'],
        ]);
        $rubric = isset($data['rubric']) ? array_map('floatval', array_filter($data['rubric'], fn ($v) => $v !== null && $v !== '')) : null;
        $this->assignments->grade($submission, $user, isset($data['score']) && $data['score'] !== '' ? (float) $data['score'] : null, $data['feedback'] ?? null, $rubric === [] ? null : $rubric, ($data['action'] ?? 'grade') === 'return');

        return back()->with('status', ($data['action'] ?? 'grade') === 'return' ? 'Tugas dikembalikan untuk revisi.' : 'Penilaian disimpan.');
    }

    private function authorize(Request $request, CourseClass $class, string $permission): User
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($this->access->canView($user, $class), 404);
        abort_unless($this->access->canManageContent($user, $class, $permission), 403);

        return $user;
    }

    /** @return array<string, mixed> */
    private function common(User $user, CourseClass $class): array
    {
        return [
            'class' => $class,
            'workspace' => $this->access->workspaceFor($user),
            'canEditAssignments' => $this->access->canManageContent($user, $class, 'assignment.update'),
            'canManageSettings' => $this->access->canManageSettings($user),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'instructions_md' => ['nullable', 'string', 'max:20000'],
            'due_at' => ['nullable', 'date'],
            'max_score' => ['required', 'numeric', 'between:1,1000'],
            'passing_score' => ['nullable', 'numeric', 'min:0'],
            'is_required' => ['nullable', 'boolean'],
            'allow_late' => ['nullable', 'boolean'],
            'allow_text' => ['nullable', 'boolean'],
            'allow_file' => ['nullable', 'boolean'],
            'rubric' => ['nullable', 'array', 'max:8'],
            'rubric.*.name' => ['nullable', 'string', 'max:120'],
            'rubric.*.max' => ['nullable', 'numeric', 'between:0,1000'],
            'rubric.*.description' => ['nullable', 'string', 'max:300'],
        ]);
        if (! ($data['allow_text'] ?? false) && ! ($data['allow_file'] ?? false)) {
            throw ValidationException::withMessages(['allow_text' => 'Izinkan minimal satu bentuk pengumpulan (teks atau berkas).']);
        }
        if (isset($data['passing_score']) && $data['passing_score'] !== '' && (float) $data['passing_score'] > (float) $data['max_score']) {
            throw ValidationException::withMessages(['passing_score' => 'Skor minimal tidak boleh melebihi skor maksimal.']);
        }
        $rubric = [];
        foreach ($data['rubric'] ?? [] as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $rubric[] = array_filter(['name' => $name, 'max' => (float) ($row['max'] ?? 0), 'description' => trim((string) ($row['description'] ?? '')) ?: null], fn ($v) => $v !== null);
        }

        return [
            'title' => trim((string) $data['title']),
            'instructions_md' => $data['instructions_md'] ?? null,
            'instructions_html' => RichText::toHtml($data['instructions_md'] ?? null) ?: null,
            'due_at' => ! empty($data['due_at']) ? $data['due_at'] : null,
            'max_score' => $data['max_score'],
            'passing_score' => isset($data['passing_score']) && $data['passing_score'] !== '' ? $data['passing_score'] : null,
            'is_required' => (bool) ($data['is_required'] ?? false),
            'allow_late' => (bool) ($data['allow_late'] ?? false),
            'allow_text' => (bool) ($data['allow_text'] ?? false),
            'allow_file' => (bool) ($data['allow_file'] ?? false),
            'rubric' => $rubric === [] ? null : $rubric,
        ];
    }
}
