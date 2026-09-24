<?php

declare(strict_types=1);

namespace App\Modules\Learning\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\CourseClass;
use App\Support\Database\Like;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Daftar kelas: semua kelas untuk staf platform (docs/08 ADM-05) dan kelas yang diampu
 * untuk trainer (TRN-02).
 */
final class ClassListController
{
    public function admin(Request $request): View
    {
        $search = Str::limit(trim((string) $request->query('q', '')), 100, '');
        $status = array_key_exists((string) $request->query('status'), CourseClass::STATUSES) ? (string) $request->query('status') : null;
        $classes = CourseClass::query()->with('program:id,name')
            ->when($search !== '', fn ($query) => $query->where(fn ($where) => $where->where('batch_name', 'ilike', Like::contains($search))
                ->orWhereHas('program', fn ($program) => $program->where('name', 'ilike', Like::contains($search)))))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderByDesc('starts_on')->paginate(25)->withQueryString();

        return view('classes.index', ['classes' => $classes, 'search' => $search, 'status' => $status, 'workspace' => 'admin']);
    }

    public function trainer(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $classes = CourseClass::query()->with('program:id,name')
            ->whereIn('id', DB::table('class_trainers')->where('user_id', $user->id)->select('course_class_id'))
            ->orderByDesc('starts_on')->paginate(25);

        return view('classes.index', ['classes' => $classes, 'search' => '', 'status' => null, 'workspace' => 'trainer']);
    }
}
