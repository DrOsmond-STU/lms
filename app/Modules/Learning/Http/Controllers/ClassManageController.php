<?php

declare(strict_types=1);

namespace App\Modules\Learning\Http\Controllers;

use App\Modules\Assessment\Models\Assessment;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Services\EnrollmentService;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Learning\Services\ClassAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Halaman kelola kelas bersama untuk staf platform & trainer pengampu (docs/08 TRN-03,
 * ADM-05). Kelas di luar lingkup → 404 (SEC-AUTHZ-03).
 */
final class ClassManageController
{
    public function __construct(private readonly ClassAccess $access) {}

    public function show(Request $request, CourseClass $class): View
    {
        $user = $this->authorize($request, $class);
        $class->load(['program', 'trainers', 'modules.chapters.lessons.media', 'modules.chapters.lessons.assessment', 'restrictedOrganization']);

        return view('classes.manage', $this->common($user, $class) + [
            'tab' => 'content',
            'quizzes' => Assessment::query()->where('course_class_id', $class->id)->where('kind', 'quiz')->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function participants(Request $request, CourseClass $class): View
    {
        $user = $this->authorize($request, $class);
        $class->load('program', 'trainers');
        $enrollments = Enrollment::query()->with('user:id,name,email', 'group:id,name')->where('course_class_id', $class->id)
            ->orderByRaw("CASE WHEN status = 'applied' THEN 0 ELSE 1 END")->orderBy('status')->orderByDesc('progress_percent')->paginate(50);

        return view('classes.participants', $this->common($user, $class) + [
            'tab' => 'participants', 'enrollments' => $enrollments,
            'canApprove' => $this->access->canApproveEnrollment($user, $class),
        ]);
    }

    /** Trainer pengampu/admin menyetujui atau menolak pendaftaran yang menunggu (kelas dengan approval). */
    public function decideEnrollment(Request $request, CourseClass $class, Enrollment $enrollment, EnrollmentService $enrollments): RedirectResponse
    {
        $user = $this->authorize($request, $class);
        abort_unless($this->access->canApproveEnrollment($user, $class) && $enrollment->course_class_id === $class->id, 404);
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'reason' => ['required_if:decision,reject', 'nullable', 'string', 'min:5', 'max:300']]);
        $enrollment->load('program', 'user');
        if ($data['decision'] === 'approve') {
            $enrollments->approve($enrollment, $user);
            $message = 'Pendaftaran '.$enrollment->user->name.' disetujui.';
        } else {
            $enrollments->reject($enrollment, $user, (string) $data['reason']);
            $message = 'Pendaftaran '.$enrollment->user->name.' ditolak.';
        }

        return redirect()->route('classes.participants', $class)->with('status', $message);
    }

    /** Admin membatalkan enrollment peserta (dengan alasan); kuota kelas kembali. */
    public function cancelEnrollment(Request $request, CourseClass $class, Enrollment $enrollment, EnrollmentService $enrollments): RedirectResponse
    {
        $user = $this->authorize($request, $class);
        abort_unless($user->can('enrollment.cancel') && $enrollment->course_class_id === $class->id, 404);
        if (! in_array($enrollment->status, Enrollment::CANCELLABLE, true)) {
            throw ValidationException::withMessages(['enrollment' => 'Pendaftaran dengan status '.$enrollment->statusLabel().' tidak dapat dibatalkan.']);
        }
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:300']]);
        $enrollments->cancel($enrollment->load('program', 'user'), $user, $data['reason']);

        return redirect()->route('classes.participants', $class)->with('status', 'Enrollment '.$enrollment->user->name.' dibatalkan.');
    }

    public function assessments(Request $request, CourseClass $class): View
    {
        $user = $this->authorize($request, $class);
        $class->load('program', 'trainers');
        $assessments = Assessment::query()->with('bank:id,name')->where('course_class_id', $class->id)->orderBy('kind')->orderBy('title')->get();
        $pendingGrading = DB::table('exam_attempts')->where('course_class_id', $class->id)->whereIn('status', ['submitted', 'auto_submitted'])->count();

        return view('classes.assessments', $this->common($user, $class) + ['tab' => 'assessments', 'assessments' => $assessments, 'pendingGrading' => $pendingGrading]);
    }

    private function authorize(Request $request, CourseClass $class): User
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($this->access->canView($user, $class), 404);

        return $user;
    }

    /** @return array<string, mixed> */
    private function common(User $user, CourseClass $class): array
    {
        return [
            'class' => $class,
            'workspace' => $this->access->workspaceFor($user),
            'canEditContent' => $this->access->canManageContent($user, $class),
            'canEditAssessments' => $this->access->canManageContent($user, $class, 'assessment.update'),
            'canManageSettings' => $this->access->canManageSettings($user),
            'canEnroll' => $user->isPlatformStaff() && $user->hasPermission('enrollment.create'),
        ];
    }
}
