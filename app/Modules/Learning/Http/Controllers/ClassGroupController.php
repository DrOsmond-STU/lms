<?php

declare(strict_types=1);

namespace App\Modules\Learning\Http\Controllers;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\ClassGroup;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Learning\Services\ClassAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Kelompok belajar dalam kelas: dibuat trainer pengampu/admin, peserta dibagi ke kelompok
 * (izin `course_class.update` dalam lingkup kelas). Peserta melihat nama kelompoknya.
 */
final class ClassGroupController
{
    public function __construct(private readonly ClassAccess $access, private readonly AuditLogger $audit) {}

    public function index(Request $request, CourseClass $class): View
    {
        $user = $this->authorize($request, $class);
        $class->load('program', 'trainers');
        $groups = ClassGroup::query()->with('mentor:id,name')->withCount('enrollments')->where('course_class_id', $class->id)->orderBy('name')->get();
        $enrollments = Enrollment::query()->with('user:id,name,email')->where('course_class_id', $class->id)
            ->whereIn('status', ['enrolled', 'in_progress', 'pending_approval', 'passed'])->get()
            ->sortBy(fn (Enrollment $e) => $e->user->name, SORT_NATURAL | SORT_FLAG_CASE)->values();
        $mentors = $class->trainers->map(fn (User $t) => ['id' => $t->id, 'name' => $t->name])
            ->concat($enrollments->map(fn (Enrollment $e) => ['id' => $e->user_id, 'name' => $e->user->name.' (peserta)']))->values();

        return view('classes.groups', [
            'class' => $class, 'tab' => 'groups', 'groups' => $groups, 'enrollments' => $enrollments, 'mentors' => $mentors,
            'workspace' => $this->access->workspaceFor($user),
            'canManageSettings' => $this->access->canManageSettings($user),
            'canEdit' => $this->access->canManageContent($user, $class, 'course_class.update'),
        ]);
    }

    public function store(Request $request, CourseClass $class): RedirectResponse
    {
        $user = $this->authorize($request, $class, true);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:80', Rule::unique('class_groups', 'name')->where('course_class_id', $class->id)],
            'description' => ['nullable', 'string', 'max:300'],
            'mentor_id' => ['nullable', 'uuid', Rule::exists('users', 'id')],
        ]);
        $group = new ClassGroup;
        $group->forceFill(['course_class_id' => $class->id, 'name' => trim($data['name']), 'description' => $data['description'] ?? null, 'mentor_id' => $data['mentor_id'] ?? null])->save();
        $this->audit->record('class_group.created', $user, 'course_class', $class->id, ['group_id' => $group->id, 'name' => $group->name]);

        return back()->with('status', 'Kelompok "'.$group->name.'" dibuat.');
    }

    public function destroy(Request $request, CourseClass $class, ClassGroup $group): RedirectResponse
    {
        $user = $this->authorize($request, $class, true);
        abort_unless($group->course_class_id === $class->id, 404);
        $group->delete(); // enrollments.group_id → NULL (ON DELETE SET NULL)
        $this->audit->record('class_group.deleted', $user, 'course_class', $class->id, ['group_id' => $group->id, 'name' => $group->name]);

        return back()->with('status', 'Kelompok dihapus; anggotanya kembali tanpa kelompok.');
    }

    /** Pembagian anggota massal: assignments[enrollment_id] = group_id|'' (kosong = tanpa kelompok). */
    public function assign(Request $request, CourseClass $class): RedirectResponse
    {
        $user = $this->authorize($request, $class, true);
        $data = $request->validate(['assignments' => ['required', 'array', 'max:500'], 'assignments.*' => ['nullable', 'uuid']]);
        $groupIds = ClassGroup::query()->where('course_class_id', $class->id)->pluck('id')->flip();
        $changed = 0;
        DB::transaction(function () use ($data, $class, $groupIds, &$changed): void {
            foreach ($data['assignments'] as $enrollmentId => $groupId) {
                if (! is_string($enrollmentId)) {
                    continue;
                }
                $target = is_string($groupId) && $groupIds->has($groupId) ? $groupId : null;
                $changed += Enrollment::query()->whereKey($enrollmentId)->where('course_class_id', $class->id)
                    ->where(fn ($q) => $target === null ? $q->whereNotNull('group_id') : $q->where(fn ($w) => $w->whereNull('group_id')->orWhere('group_id', '<>', $target)))
                    ->update(['group_id' => $target, 'updated_at' => now()]);
            }
        });
        $this->audit->record('class_group.assigned', $user, 'course_class', $class->id, ['changed' => $changed]);

        return back()->with('status', 'Pembagian kelompok disimpan ('.$changed.' perubahan).');
    }

    private function authorize(Request $request, CourseClass $class, bool $edit = false): User
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($this->access->canView($user, $class), 404);
        if ($edit) {
            abort_unless($this->access->canManageContent($user, $class, 'course_class.update'), 403);
        }

        return $user;
    }
}
