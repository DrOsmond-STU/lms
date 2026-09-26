<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Communication\Models\Announcement;
use App\Modules\Communication\Services\AnnouncementFeed;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Learning\Services\ClassAccess;
use App\Modules\Notification\Services\Notifier;
use App\Modules\Organization\Models\Organization;
use App\Support\Content\RichText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Pengumuman: dibaca semua pengguna (feed sesuai audiens), dikelola admin platform (semua lingkup),
 * Admin Organisasi (organisasinya), dan trainer pengampu (kelasnya). Isi Markdown → HTML tersanitasi.
 */
final class AnnouncementController
{
    public function __construct(
        private readonly AnnouncementFeed $feed,
        private readonly ClassAccess $access,
        private readonly Notifier $notifier,
        private readonly AuditLogger $audit,
    ) {}

    /** Feed untuk pengguna apa pun (workspace mengikuti peran utama). */
    public function index(Request $request): View
    {
        $user = $this->user($request);
        $announcements = $this->feed->for($user);

        return view('announcements.index', [
            'announcements' => $announcements,
            'workspace' => $user->defaultWorkspace() ?? 'participant',
            'manageUrl' => match (true) {
                $user->isPlatformStaff() && $user->hasPermission('announcement.manage') => route('admin.announcements.index'),
                $user->hasPermission('announcement.manage') && $user->tenantOrganizationIds() !== [] => route('org.announcements.index'),
                default => null,
            },
        ]);
    }

    /** Admin platform: semua pengumuman, semua lingkup. */
    public function adminIndex(Request $request): View
    {
        $organizations = Organization::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']);
        $classes = $this->classOptions(null);

        return view('announcements.manage', [
            'mode' => 'admin', 'workspace' => 'admin', 'class' => null,
            'announcements' => Announcement::query()->with('author:id,name', 'organization:id,name', 'courseClass:id,batch_name')->orderByDesc('is_pinned')->orderByDesc('publish_at')->paginate(30),
            'organizations' => $organizations, 'classes' => $classes, 'scopes' => Announcement::SCOPES,
            'action' => route('admin.announcements.store'),
        ]);
    }

    /** Admin Organisasi: pengumuman organisasinya sendiri. */
    public function orgIndex(Request $request): View
    {
        $user = $this->user($request);
        $organizationIds = $user->tenantOrganizationIds();
        abort_if($organizationIds === [], 404);

        return view('announcements.manage', [
            'mode' => 'organization', 'workspace' => 'organization', 'class' => null,
            'announcements' => Announcement::query()->with('author:id,name', 'organization:id,name')->where('scope', 'organization')->whereIn('organization_id', $organizationIds)->orderByDesc('is_pinned')->orderByDesc('publish_at')->paginate(30),
            'organizations' => Organization::query()->whereIn('id', $organizationIds)->orderBy('name')->get(['id', 'name', 'code']), 'classes' => collect(),
            'scopes' => ['organization' => Announcement::SCOPES['organization']],
            'action' => route('org.announcements.store'),
        ]);
    }

    /** Trainer pengampu / admin: pengumuman satu kelas (tab kelola kelas). */
    public function classIndex(Request $request, CourseClass $class): View
    {
        $user = $this->user($request);
        abort_unless($this->access->canView($user, $class), 404);
        $class->load('program', 'trainers');

        return view('announcements.manage', [
            'mode' => 'class', 'workspace' => $this->access->workspaceFor($user), 'class' => $class, 'tab' => 'announcements',
            'canManageSettings' => $this->access->canManageSettings($user),
            'canEdit' => $this->access->canManageContent($user, $class, 'announcement.manage'),
            'announcements' => Announcement::query()->with('author:id,name')->where('course_class_id', $class->id)->orderByDesc('is_pinned')->orderByDesc('publish_at')->paginate(30),
            'organizations' => collect(), 'classes' => collect([(object) ['id' => $class->id, 'label' => $class->program->name.' · '.$class->batch_name]]),
            'scopes' => ['class' => Announcement::SCOPES['class']],
            'action' => route('classes.announcements.store', $class),
        ]);
    }

    public function store(Request $request, ?CourseClass $class = null): RedirectResponse
    {
        $user = $this->user($request);
        $data = $this->validated($request, $user, $class);
        $announcement = new Announcement;
        $announcement->forceFill($data + ['body_html' => RichText::toHtml($data['body']), 'created_by' => $user->id])->save();
        $this->audit->record('announcement.created', $user, 'announcement', $announcement->id, ['scope' => $announcement->scope, 'title' => $announcement->title], null, $announcement->organization_id);
        if ($request->boolean('notify') && $announcement->isLive()) {
            $this->notifyAudience($announcement);
        }

        return back()->with('status', 'Pengumuman "'.$announcement->title.'" disimpan.');
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $user = $this->user($request);
        $this->assertOwns($user, $announcement);
        $data = $this->validated($request, $user, $announcement->courseClass);
        $announcement->forceFill($data + ['body_html' => RichText::toHtml($data['body'])])->save();
        $this->audit->record('announcement.updated', $user, 'announcement', $announcement->id, null, null, $announcement->organization_id);

        return back()->with('status', 'Pengumuman diperbarui.');
    }

    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        $user = $this->user($request);
        $this->assertOwns($user, $announcement);
        $announcement->delete();
        $this->audit->record('announcement.deleted', $user, 'announcement', $announcement->id, ['title' => $announcement->title], null, $announcement->organization_id);

        return back()->with('status', 'Pengumuman dihapus.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, User $user, ?CourseClass $class): array
    {
        $data = $request->validate([
            'scope' => ['required', Rule::in(array_keys(Announcement::SCOPES))],
            'organization_id' => ['nullable', 'uuid', Rule::exists('organizations', 'id')],
            'course_class_id' => ['nullable', 'uuid', Rule::exists('course_classes', 'id')],
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'body' => ['required', 'string', 'min:3', 'max:20000'],
            'is_pinned' => ['nullable', 'boolean'],
            'publish_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:publish_at'],
        ]);
        $scope = $data['scope'];
        $organizationId = $scope === 'organization' ? ($data['organization_id'] ?? null) : null;
        $classId = $scope === 'class' ? ($class !== null ? $class->id : ($data['course_class_id'] ?? null)) : null;
        if ($scope === 'organization' && $organizationId === null) {
            throw ValidationException::withMessages(['organization_id' => 'Pilih organisasi.']);
        }
        if ($scope === 'class' && $classId === null) {
            throw ValidationException::withMessages(['course_class_id' => 'Pilih kelas.']);
        }
        abort_unless($this->mayManage($user, $scope, $organizationId, $classId), 403);

        return [
            'scope' => $scope, 'organization_id' => $organizationId, 'course_class_id' => $classId,
            'title' => trim($data['title']), 'body' => $data['body'], 'is_pinned' => (bool) ($data['is_pinned'] ?? false),
            'publish_at' => ! empty($data['publish_at']) ? Carbon::parse($data['publish_at'], display_tz())->utc() : now(),
            'expires_at' => ! empty($data['expires_at']) ? Carbon::parse($data['expires_at'], display_tz())->utc() : null,
        ];
    }

    private function mayManage(User $user, string $scope, ?string $organizationId, ?string $classId): bool
    {
        if (! $user->hasPermission('announcement.manage')) {
            return false;
        }
        if ($user->isPlatformStaff()) {
            return true;
        }

        return match ($scope) {
            'organization' => $organizationId !== null && in_array($organizationId, $user->tenantOrganizationIds(), true),
            'class' => $classId !== null && $this->access->teaches($user, $classId),
            default => false,
        };
    }

    private function assertOwns(User $user, Announcement $announcement): void
    {
        abort_unless($this->mayManage($user, $announcement->scope, $announcement->organization_id, $announcement->course_class_id), 404);
    }

    /** Pemberitahuan in-app ke audiens (dibatasi 5.000 penerima per pengumuman). */
    private function notifyAudience(Announcement $announcement): void
    {
        $path = '/pengumuman';
        $query = match ($announcement->scope) {
            'class' => DB::table('enrollments')->where('course_class_id', $announcement->course_class_id)->whereIn('status', ['enrolled', 'in_progress', 'pending_approval'])->select('user_id'),
            'organization' => DB::table('organization_members')->where('organization_id', $announcement->organization_id)->where('status', 'active')->select('user_id'),
            default => DB::table('users')->where('status', 'active')->select('id as user_id'),
        };
        $recipients = User::query()->whereIn('id', $query->limit(5000))->where('status', 'active')->get();
        $recipients->each(fn (User $user) => $this->notifier->send($user, 'system', 'Pengumuman: '.$announcement->title, Str::limit(strip_tags($announcement->body_html), 200), $path));
    }

    /** @return Collection<int, object{id: string, label: string}> */
    private function classOptions(?string $organizationId): Collection
    {
        /** @var Collection<int, object{id: string, label: string}> */
        return DB::table('course_classes')->join('programs', 'programs.id', '=', 'course_classes.program_id')
            ->whereIn('course_classes.status', ['open', 'running'])->orderBy('programs.name')->orderBy('course_classes.batch_name')
            ->get(['course_classes.id', 'course_classes.batch_name', 'programs.name as program_name'])
            ->map(fn (object $c) => (object) ['id' => (string) $c->id, 'label' => $c->program_name.' · '.$c->batch_name]);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
