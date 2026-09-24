<?php

declare(strict_types=1);

namespace App\Modules\Learning\Http\Controllers;

use App\Modules\Assessment\Models\Assessment;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Learning\Services\ClassAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $enrollments = Enrollment::query()->with('user:id,name,email')->where('course_class_id', $class->id)
            ->orderBy('status')->orderByDesc('progress_percent')->paginate(50);

        return view('classes.participants', $this->common($user, $class) + ['tab' => 'participants', 'enrollments' => $enrollments]);
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
