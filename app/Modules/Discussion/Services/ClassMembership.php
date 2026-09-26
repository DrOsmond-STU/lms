<?php

declare(strict_types=1);

namespace App\Modules\Discussion\Services;

use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Learning\Services\ClassAccess;

/**
 * Keanggotaan ruang interaktif kelas: peserta (enrollment aktif/lulus), trainer pengampu,
 * atau staf platform dengan izin diskusi. Menentukan peran moderasi dan tata letak.
 */
final class ClassMembership
{
    public function __construct(private readonly ClassAccess $access) {}

    /**
     * @return array{role: 'participant'|'moderator'|'staff', workspace: string, enrollment: Enrollment|null}|null
     */
    public function resolve(User $user, CourseClass $class): ?array
    {
        $enrollment = Enrollment::query()->where('user_id', $user->id)->where('course_class_id', $class->id)
            ->whereIn('status', ['enrolled', 'in_progress', 'pending_approval', 'passed'])->first();
        if ($enrollment !== null && $user->hasPermission('discussion.view')) {
            return ['role' => 'participant', 'workspace' => 'participant', 'enrollment' => $enrollment];
        }
        if ($this->access->canView($user, $class) && $user->hasPermission('discussion.view')) {
            $moderator = $this->access->canManageContent($user, $class, 'discussion.moderate');

            return ['role' => $moderator ? 'moderator' : 'staff', 'workspace' => $this->access->workspaceFor($user), 'enrollment' => null];
        }

        return null;
    }

    /** @return array{role: 'participant'|'moderator'|'staff', workspace: string, enrollment: Enrollment|null} */
    public function require(User $user, CourseClass $class): array
    {
        return $this->resolve($user, $class) ?? abort(404);
    }

    public function backUrl(string $workspace, CourseClass $class, ?Enrollment $enrollment): string
    {
        if ($enrollment !== null) {
            return route('learning.classroom', $enrollment);
        }

        return route('classes.manage', $class);
    }
}
