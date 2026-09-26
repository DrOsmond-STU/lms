<?php

declare(strict_types=1);

namespace App\Modules\Learning\Http\Controllers;

use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\AcademicEvent;
use App\Modules\Learning\Models\AttendanceRecord;
use App\Modules\Learning\Models\ClassSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Jadwal pembelajaran: sesi kelas (tatap muka/live class), tenggat tugas, dan kalender akademik
 * dalam satu tampilan per bulan — untuk peserta (kelas yang diikuti) dan trainer (kelas diampu).
 */
final class ScheduleController
{
    public function participant(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $month = self::month($request);
        $enrollments = Enrollment::query()->with('program:id,name', 'courseClass:id,batch_name')->where('user_id', $user->id)
            ->whereIn('status', ['enrolled', 'in_progress', 'pending_approval'])->get()->keyBy('course_class_id');
        $classIds = $enrollments->keys()->map(fn ($id): string => (string) $id)->values()->all();

        $sessions = ClassSession::query()->whereIn('course_class_id', $classIds)
            ->whereBetween('starts_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->orderBy('starts_at')->get();
        $attendance = AttendanceRecord::query()->where('user_id', $user->id)->whereIn('class_session_id', $sessions->pluck('id'))->get()->keyBy('class_session_id');
        $deadlines = self::assignmentDeadlines($classIds, $month);

        return view('schedule.index', [
            'workspace' => 'participant',
            'month' => $month,
            'items' => self::merge($sessions, $deadlines, self::events($month, $classIds, $user->tenantOrganizationIds()), $enrollments),
            'attendance' => $attendance,
            'enrollments' => $enrollments,
        ]);
    }

    public function trainer(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $month = self::month($request);
        $classIds = DB::table('class_trainers')->where('user_id', $user->id)->pluck('course_class_id')->map(fn ($id): string => (string) $id)->values()->all();
        $classes = DB::table('course_classes')->join('programs', 'programs.id', '=', 'course_classes.program_id')
            ->whereIn('course_classes.id', $classIds)->get(['course_classes.id', 'course_classes.batch_name', 'programs.name as program_name'])->keyBy('id');
        $sessions = ClassSession::query()->whereIn('course_class_id', $classIds)
            ->whereBetween('starts_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->orderBy('starts_at')->get();

        return view('schedule.index', [
            'workspace' => 'trainer',
            'month' => $month,
            'items' => self::merge($sessions, self::assignmentDeadlines($classIds, $month), self::events($month, $classIds, []), $classes),
            'attendance' => collect(),
            'enrollments' => collect(),
        ]);
    }

    /** Cek-in mandiri peserta pada sesi (jendela waktu & kode diperiksa server). */
    public function checkIn(Request $request, Enrollment $enrollment, ClassSession $session): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($enrollment->user_id === $user->id && $session->course_class_id === $enrollment->course_class_id, 404);
        if (! $enrollment->isActive()) {
            throw ValidationException::withMessages(['checkin' => 'Enrollment tidak aktif.']);
        }
        if (! $session->checkinOpen()) {
            throw ValidationException::withMessages(['checkin' => 'Cek-in belum dibuka atau sudah ditutup untuk sesi ini.']);
        }
        $data = $request->validate(['code' => ['nullable', 'string', 'max:8']]);
        if ($session->checkin_code !== null && strtoupper(trim((string) ($data['code'] ?? ''))) !== $session->checkin_code) {
            throw ValidationException::withMessages(['checkin' => 'Kode cek-in salah.']);
        }
        $late = now()->greaterThan($session->starts_at->copy()->addMinutes(10));
        AttendanceRecord::query()->upsert([[
            'id' => (string) Str::uuid7(), 'organization_id' => $enrollment->organization_id, 'course_class_id' => $enrollment->course_class_id,
            'class_session_id' => $session->id, 'enrollment_id' => $enrollment->id, 'user_id' => $user->id,
            'status' => $late ? 'late' : 'present', 'method' => 'self', 'checked_in_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]], ['class_session_id', 'enrollment_id'], ['status', 'method', 'checked_in_at', 'updated_at']);

        return back()->with('status', 'Kehadiran Anda tercatat'.($late ? ' (terlambat)' : '').'.');
    }

    private static function month(Request $request): Carbon
    {
        $raw = (string) $request->query('bulan', '');

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $raw) === 1
            ? Carbon::parse($raw.'-01', display_tz())->startOfMonth()
            : now()->timezone(display_tz())->startOfMonth();
    }

    /**
     * @param  array<int, string>  $classIds
     * @return Collection<int, \stdClass>
     */
    private static function assignmentDeadlines(array $classIds, Carbon $month): Collection
    {
        if ($classIds === [] || ! DB::getSchemaBuilder()->hasTable('assignments')) {
            return collect();
        }

        return DB::table('assignments')->whereIn('course_class_id', $classIds)->whereNotNull('due_at')
            ->whereBetween('due_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->orderBy('due_at')->get(['id', 'course_class_id', 'title', 'due_at']);
    }

    /**
     * @param  array<int, string>  $classIds
     * @param  list<string>  $organizationIds
     * @return Collection<int, AcademicEvent>
     */
    private static function events(Carbon $month, array $classIds, array $organizationIds): Collection
    {
        return AcademicEvent::query()
            ->where('starts_on', '<=', $month->copy()->endOfMonth()->toDateString())->where('ends_on', '>=', $month->copy()->startOfMonth()->toDateString())
            ->where(function ($q) use ($classIds, $organizationIds): void {
                $q->where('scope', 'platform');
                if ($organizationIds !== []) {
                    $q->orWhere(fn ($o) => $o->where('scope', 'organization')->whereIn('organization_id', $organizationIds));
                }
                if ($classIds !== []) {
                    $q->orWhere(fn ($c) => $c->where('scope', 'class')->whereIn('course_class_id', $classIds));
                }
            })->orderBy('starts_on')->get();
    }

    /**
     * Gabungkan semua ke satu daftar urut tanggal (tampilan).
     *
     * @param  Collection<int, ClassSession>  $sessions
     * @param  Collection<int, \stdClass>  $deadlines
     * @param  Collection<int, AcademicEvent>  $events
     * @param  Collection<int|string, mixed>  $classes
     * @return Collection<int, array<string, mixed>>
     */
    private static function merge(Collection $sessions, Collection $deadlines, Collection $events, Collection $classes): Collection
    {
        $label = function (string $classId) use ($classes): string {
            $class = $classes->get($classId);
            if ($class instanceof Enrollment) {
                return $class->program->name.' · '.$class->courseClass->batch_name;
            }

            return is_object($class) ? ($class->program_name ?? '').' · '.($class->batch_name ?? '') : '';
        };
        $items = collect();
        foreach ($sessions as $session) {
            $items->push(['kind' => 'session', 'at' => $session->starts_at, 'title' => $session->title, 'class' => $label($session->course_class_id), 'session' => $session, 'class_id' => $session->course_class_id]);
        }
        foreach ($deadlines as $deadline) {
            $items->push(['kind' => 'deadline', 'at' => Carbon::parse((string) $deadline->due_at), 'title' => 'Tenggat tugas: '.$deadline->title, 'class' => $label((string) $deadline->course_class_id), 'class_id' => (string) $deadline->course_class_id, 'assignment_id' => (string) $deadline->id]);
        }
        foreach ($events as $event) {
            $items->push(['kind' => 'event', 'at' => $event->starts_on->copy()->startOfDay(), 'title' => $event->title, 'class' => $event->kindLabel().($event->ends_on->ne($event->starts_on) ? ' · s.d. '.$event->ends_on->translatedFormat('d M') : ''), 'event' => $event, 'class_id' => $event->course_class_id]);
        }

        return $items->sortBy(fn (array $i) => $i['at']->getTimestamp())->values();
    }
}
