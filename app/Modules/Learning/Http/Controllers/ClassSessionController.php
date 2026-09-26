<?php

declare(strict_types=1);

namespace App\Modules\Learning\Http\Controllers;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\AttendanceRecord;
use App\Modules\Learning\Models\ClassSession;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Learning\Services\ClassAccess;
use App\Modules\Notification\Services\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sesi kelas (jadwal pertemuan / live class) & presensi — dikelola trainer pengampu atau staf
 * platform dalam lingkup kelas (izin live_session.* dan attendance.*).
 */
final class ClassSessionController
{
    public function __construct(
        private readonly ClassAccess $access,
        private readonly AuditLogger $audit,
        private readonly Notifier $notifier,
    ) {}

    public function index(Request $request, CourseClass $class): View
    {
        $user = $this->authorize($request, $class, 'live_session.view');
        $class->load('program', 'trainers');
        $sessions = ClassSession::query()->withCount(['attendance as present_count' => fn ($q) => $q->whereIn('status', ['present', 'late'])])
            ->where('course_class_id', $class->id)->orderBy('starts_at')->get();

        return view('classes.sessions', [
            'class' => $class, 'sessions' => $sessions, 'tab' => 'sessions',
            'workspace' => $this->access->workspaceFor($user),
            'canEditSessions' => $this->access->canManageContent($user, $class, 'live_session.create'),
            'canManageSettings' => $this->access->canManageSettings($user),
            'activeCount' => Enrollment::query()->where('course_class_id', $class->id)->whereIn('status', Enrollment::ACTIVE)->count(),
        ]);
    }

    public function store(Request $request, CourseClass $class): RedirectResponse
    {
        $user = $this->authorize($request, $class, 'live_session.create');
        $data = $this->validated($request);

        $session = new ClassSession;
        $session->forceFill($this->attributes($data) + ['course_class_id' => $class->id, 'created_by' => $user->id])->save();
        $this->audit->record('class.session_created', $user, 'course_class', $class->id, ['session_id' => $session->id, 'title' => $session->title]);
        $this->notifyParticipants($class, $session, 'Sesi baru dijadwalkan');

        return back()->with('status', 'Sesi "'.$session->title.'" dijadwalkan.');
    }

    public function update(Request $request, CourseClass $class, ClassSession $session): RedirectResponse
    {
        $user = $this->authorize($request, $class, 'live_session.update');
        abort_unless($session->course_class_id === $class->id, 404);
        $data = $this->validated($request);
        $session->forceFill($this->attributes($data))->save();
        $this->audit->record('class.session_updated', $user, 'course_class', $class->id, ['session_id' => $session->id]);
        if ($session->wasChanged(['starts_at', 'ends_at', 'meeting_url', 'location'])) {
            $this->notifyParticipants($class, $session, 'Jadwal sesi diperbarui');
        }

        return back()->with('status', 'Sesi diperbarui.');
    }

    public function destroy(Request $request, CourseClass $class, ClassSession $session): RedirectResponse
    {
        $user = $this->authorize($request, $class, 'live_session.delete');
        abort_unless($session->course_class_id === $class->id, 404);
        if (AttendanceRecord::query()->where('class_session_id', $session->id)->exists()) {
            throw ValidationException::withMessages(['session' => 'Sesi yang sudah memiliki presensi tidak dapat dihapus.']);
        }
        $session->delete();
        $this->audit->record('class.session_deleted', $user, 'course_class', $class->id, ['session_id' => $session->id]);

        return redirect()->route('classes.sessions', $class)->with('status', 'Sesi dihapus.');
    }

    public function attendance(Request $request, CourseClass $class, ClassSession $session): View
    {
        $user = $this->authorize($request, $class, 'attendance.view_any');
        abort_unless($session->course_class_id === $class->id, 404);
        $class->load('program', 'trainers');
        $enrollments = Enrollment::query()->with('user:id,name,email')->where('course_class_id', $class->id)
            ->whereNotIn('status', ['cancelled', 'awaiting_payment'])->get()->sortBy(fn (Enrollment $e) => $e->user->name)->values();
        $records = AttendanceRecord::query()->where('class_session_id', $session->id)->get()->keyBy('enrollment_id');

        return view('classes.attendance', [
            'class' => $class, 'session' => $session, 'enrollments' => $enrollments, 'records' => $records, 'tab' => 'sessions',
            'workspace' => $this->access->workspaceFor($user),
            'canRecord' => $this->access->canManageContent($user, $class, 'attendance.record_manual'),
            'canManageSettings' => $this->access->canManageSettings($user),
        ]);
    }

    public function storeAttendance(Request $request, CourseClass $class, ClassSession $session): RedirectResponse
    {
        $user = $this->authorize($request, $class, 'attendance.record_manual');
        abort_unless($session->course_class_id === $class->id, 404);
        $data = $request->validate([
            'status' => ['required', 'array'],
            'status.*' => ['nullable', Rule::in(array_keys(AttendanceRecord::STATUSES))],
            'note' => ['nullable', 'array'],
            'note.*' => ['nullable', 'string', 'max:300'],
        ]);
        $enrollments = Enrollment::query()->where('course_class_id', $class->id)->whereIn('id', array_keys($data['status']))->get()->keyBy('id');

        DB::transaction(function () use ($data, $enrollments, $session, $user): void {
            foreach ($data['status'] as $enrollmentId => $status) {
                $enrollment = $enrollments->get($enrollmentId);
                if (! $enrollment instanceof Enrollment || $status === null || $status === '') {
                    continue;
                }
                AttendanceRecord::query()->upsert([[
                    'id' => (string) Str::uuid7(),
                    'organization_id' => $enrollment->organization_id,
                    'course_class_id' => $enrollment->course_class_id,
                    'class_session_id' => $session->id,
                    'enrollment_id' => $enrollment->id,
                    'user_id' => $enrollment->user_id,
                    'status' => $status,
                    'method' => 'manual',
                    'checked_in_at' => in_array($status, ['present', 'late'], true) ? now() : null,
                    'note' => Str::limit(trim((string) ($data['note'][$enrollmentId] ?? '')), 300, '') ?: null,
                    'recorded_by' => $user->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]], ['class_session_id', 'enrollment_id'], ['status', 'method', 'checked_in_at', 'note', 'recorded_by', 'updated_at']);
            }
        });
        $this->audit->record('attendance.recorded', $user, 'class_session', $session->id, ['class_id' => $class->id, 'count' => count($data['status'])]);

        return back()->with('status', 'Presensi disimpan.');
    }

    public function exportAttendance(Request $request, CourseClass $class): StreamedResponse
    {
        $user = $this->authorize($request, $class, 'attendance.view_any');
        $sessions = ClassSession::query()->where('course_class_id', $class->id)->orderBy('starts_at')->get();
        $enrollments = Enrollment::query()->with('user:id,name,email')->where('course_class_id', $class->id)->whereNotIn('status', ['cancelled', 'awaiting_payment'])->get();
        $records = AttendanceRecord::query()->where('course_class_id', $class->id)->get()->groupBy('enrollment_id');
        $this->audit->record('attendance.exported', $user, 'course_class', $class->id);

        return response()->streamDownload(function () use ($sessions, $enrollments, $records): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_merge(['Peserta', 'Email'], $sessions->map(fn (ClassSession $s) => $s->title.' ('.$s->starts_at->timezone(display_tz())->format('d/m/Y').')')->all(), ['Hadir', 'Persentase']), ';');
            foreach ($enrollments as $enrollment) {
                $byId = ($records->get($enrollment->id) ?? collect())->keyBy('class_session_id');
                $present = 0;
                $cells = [];
                foreach ($sessions as $session) {
                    $record = $byId->get($session->id);
                    $status = $record instanceof AttendanceRecord ? $record->status : '';
                    $cells[] = $status === '' ? '-' : AttendanceRecord::STATUSES[$status];
                    if (in_array($status, ['present', 'late'], true)) {
                        $present++;
                    }
                }
                $percent = $sessions->isEmpty() ? '' : (string) round($present * 100 / $sessions->count());
                fputcsv($out, array_map(fn ($v) => preg_match('/^[=+\-@]/', (string) $v) === 1 ? "'".$v : $v, array_merge([$enrollment->user->name, $enrollment->user->email], $cells, [(string) $present, $percent])), ';');
            }
            fclose($out);
        }, 'presensi-'.Str::slug($class->batch_name).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
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
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::in(array_keys(ClassSession::TYPES))],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'meeting_url' => ['nullable', 'string', 'max:500', 'url:https'],
            'location' => ['nullable', 'string', 'max:200'],
            'attendance_mode' => ['required', Rule::in(array_keys(ClassSession::ATTENDANCE_MODES))],
            'checkin_code' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{4,8}$/'],
            'checkin_opens_before' => ['nullable', 'integer', 'between:0,1440'],
            'checkin_closes_after' => ['nullable', 'integer', 'between:0,1440'],
        ], ['meeting_url.url' => 'Tautan meeting harus diawali https://.', 'checkin_code.regex' => 'Kode cek-in 4–8 huruf/angka.']);
        if ($data['type'] === 'online' && empty($data['meeting_url'])) {
            throw ValidationException::withMessages(['meeting_url' => 'Live class memerlukan tautan meeting (Zoom/Meet/Teams).']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'title' => trim((string) $data['title']),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'type' => $data['type'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'meeting_url' => $data['type'] === 'online' ? $data['meeting_url'] : null,
            'location' => $data['type'] === 'offline' ? ($data['location'] ?? null) : null,
            'attendance_mode' => $data['attendance_mode'],
            'checkin_code' => ! empty($data['checkin_code']) ? strtoupper((string) $data['checkin_code']) : null,
            'checkin_opens_before' => (int) ($data['checkin_opens_before'] ?? 15),
            'checkin_closes_after' => (int) ($data['checkin_closes_after'] ?? 30),
        ];
    }

    private function notifyParticipants(CourseClass $class, ClassSession $session, string $title): void
    {
        $when = $session->starts_at->timezone(display_tz())->translatedFormat('l, d M Y H:i').' '.tz_label();
        Enrollment::query()->with('user')->where('course_class_id', $class->id)->whereIn('status', Enrollment::ACTIVE)->get()
            ->each(fn (Enrollment $e) => $this->notifier->send($e->user, 'content', $title, $session->title.' — '.$when.'.', '/peserta/kelas/'.$e->id));
    }
}
