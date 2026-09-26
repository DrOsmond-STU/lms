<?php

declare(strict_types=1);

namespace App\Modules\Learning\Http\Controllers;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\AcademicEvent;
use App\Modules\Organization\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Kalender akademik (admin): libur, periode ujian, pendaftaran, acara. */
final class AcademicCalendarController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $events = AcademicEvent::query()->where('ends_on', '>=', now()->subMonths(2)->toDateString())->orderBy('starts_on')->get();
        $organizations = Organization::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']);
        $classes = DB::table('course_classes')->join('programs', 'programs.id', '=', 'course_classes.program_id')
            ->whereIn('course_classes.status', ['open', 'running'])->orderBy('programs.name')
            ->get(['course_classes.id', 'course_classes.batch_name', 'programs.name as program_name']);
        $names = $organizations->pluck('name', 'id')->merge($classes->mapWithKeys(fn ($c) => [$c->id => $c->program_name.' · '.$c->batch_name]));

        return view('admin.calendar.index', ['events' => $events, 'organizations' => $organizations, 'classes' => $classes, 'names' => $names]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $event = new AcademicEvent;
        $event->forceFill($this->validated($request) + ['created_by' => $user->id])->save();
        $this->audit->record('calendar.event_created', $user, 'academic_event', $event->id, ['title' => $event->title]);

        return back()->with('status', 'Agenda "'.$event->title.'" ditambahkan.');
    }

    public function update(Request $request, AcademicEvent $event): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $event->forceFill($this->validated($request))->save();
        $this->audit->record('calendar.event_updated', $user, 'academic_event', $event->id);

        return back()->with('status', 'Agenda diperbarui.');
    }

    public function destroy(Request $request, AcademicEvent $event): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $event->delete();
        $this->audit->record('calendar.event_deleted', $user, 'academic_event', $event->id, ['title' => $event->title]);

        return back()->with('status', 'Agenda dihapus.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'kind' => ['required', Rule::in(array_keys(AcademicEvent::KINDS))],
            'scope' => ['required', Rule::in(array_keys(AcademicEvent::SCOPES))],
            'organization_id' => ['nullable', 'uuid', Rule::exists('organizations', 'id')],
            'course_class_id' => ['nullable', 'uuid', Rule::exists('course_classes', 'id')],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ]);
        if ($data['scope'] === 'organization' && empty($data['organization_id'])) {
            throw ValidationException::withMessages(['organization_id' => 'Pilih organisasi.']);
        }
        if ($data['scope'] === 'class' && empty($data['course_class_id'])) {
            throw ValidationException::withMessages(['course_class_id' => 'Pilih kelas.']);
        }

        return [
            'title' => trim((string) $data['title']),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'kind' => $data['kind'],
            'scope' => $data['scope'],
            'organization_id' => $data['scope'] === 'organization' ? $data['organization_id'] : null,
            'course_class_id' => $data['scope'] === 'class' ? $data['course_class_id'] : null,
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
        ];
    }
}
