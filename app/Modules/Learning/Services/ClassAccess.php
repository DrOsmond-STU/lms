<?php

declare(strict_types=1);

namespace App\Modules\Learning\Services;

use App\Modules\Catalog\Models\Program;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\CourseClass;
use Illuminate\Support\Facades\DB;

/**
 * Otorisasi berbasis lingkup kelas (docs/07 §5 simbol 📚): staf platform dengan izin
 * terkait, atau trainer yang mengampu kelas tersebut. Di luar lingkup → 404.
 */
final class ClassAccess
{
    public function teaches(User $user, CourseClass|string $class): bool
    {
        $classId = $class instanceof CourseClass ? $class->id : $class;

        return DB::table('class_trainers')->where('course_class_id', $classId)->where('user_id', $user->id)->exists();
    }

    public function teachesProgram(User $user, Program $program): bool
    {
        return DB::table('class_trainers')
            ->join('course_classes', 'course_classes.id', '=', 'class_trainers.course_class_id')
            ->where('course_classes.program_id', $program->id)
            ->where('class_trainers.user_id', $user->id)
            ->exists();
    }

    public function canView(User $user, CourseClass $class): bool
    {
        return ($user->isPlatformStaff() && $user->hasPermission('course_class.view_any'))
            || ($user->hasPermission('course_class.view') && $this->teaches($user, $class));
    }

    /** Konten, asesmen, penilaian (izin `content.*`/`assessment.*` dalam lingkup kelas). */
    public function canManageContent(User $user, CourseClass $class, string $permission = 'content.update'): bool
    {
        if (! $user->hasPermission($permission)) {
            return false;
        }

        return $user->isPlatformStaff() || $this->teaches($user, $class);
    }

    /** Pengaturan kelas, trainer, status — hanya staf platform. */
    public function canManageSettings(User $user): bool
    {
        return $user->isPlatformStaff() && $user->hasPermission('course_class.update');
    }

    /** Bank soal & kunci jawaban (SEC-EXAM-10): Admin Akademik atau trainer pengampu program. */
    public function canManageQuestionBank(User $user, Program $program): bool
    {
        if (! $user->hasPermission('assessment.view_answer_key')) {
            return false;
        }

        return $user->isPlatformStaff() || $this->teachesProgram($user, $program);
    }

    /** Area tata letak untuk halaman kelola kelas. */
    public function workspaceFor(User $user): string
    {
        return $user->isPlatformStaff() ? 'admin' : 'trainer';
    }
}
