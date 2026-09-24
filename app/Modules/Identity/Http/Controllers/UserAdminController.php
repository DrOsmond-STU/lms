<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Access\RoleCode;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\UserAdministration;
use App\Modules\Organization\Models\Organization;
use App\Support\Database\Like;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Master pengguna (docs/08 ADM-07). Email disamarkan di tabel; aksi sensitif
 * (peran, status, reset MFA) memerlukan re-autentikasi (middleware `reauth`).
 */
final class UserAdminController
{
    public function __construct(private readonly UserAdministration $admin) {}

    public function index(Request $request): View
    {
        $search = Str::limit(trim((string) $request->query('q', '')), 100, '');
        $role = RoleCode::tryFrom((string) $request->query('peran', ''));
        $roleCode = $role?->value;
        $status = in_array($request->query('status'), ['active', 'pending_verification', 'deactivated', 'suspended'], true) ? (string) $request->query('status') : null;

        $users = User::query()
            ->with('roles')
            ->when($search !== '', fn ($query) => $query->where(fn ($where) => $where
                ->where('name', 'ilike', Like::contains($search))
                ->orWhere('email', User::normalizeEmail($search))))
            ->when($roleCode !== null, fn ($query) => $query->whereHas('roles', fn ($roles) => $roles->where('code', $roleCode)))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
            'role' => $role,
            'status' => $status,
            'roles' => RoleCode::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        /** @var User $actor */
        $actor = $request->user();

        return view('admin.users.invite', [
            'assignable' => $this->admin->assignableRoles($actor),
            'organizations' => $this->activeOrganizations(),
            'selectedRole' => (string) $request->query('peran', ''),
            'selectedOrganization' => (string) $request->query('organisasi', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'role' => ['required', 'string', Rule::in(array_map(fn (RoleCode $role) => $role->value, RoleCode::cases()))],
            'organization_id' => ['nullable', 'string'],
        ]);

        $user = $this->admin->invite($actor, $data);

        return redirect()->route('admin.users.show', $user)->with('status', 'Undangan dikirim ke '.$user->email.' (berlaku '.config('security.invitation.ttl_hours').' jam).');
    }

    public function show(Request $request, User $user): View
    {
        /** @var User $actor */
        $actor = $request->user();
        $user->load('roles');

        $organizationIds = $user->roles->map(fn ($role) => $role->getRelationValue('pivot')?->getAttribute('organization_id'))->filter()->push($user->primary_organization_id)->filter()->unique()->all();
        $organizationNames = Organization::query()->whereIn('id', $organizationIds)->pluck('name', 'id');
        $memberships = DB::table('organization_members')
            ->join('organizations', 'organizations.id', '=', 'organization_members.organization_id')
            ->where('organization_members.user_id', $user->id)
            ->orderBy('organizations.name')
            ->get(['organizations.name', 'organizations.code', 'organization_members.status']);

        return view('admin.users.show', [
            'user' => $user,
            'canManage' => $this->admin->canManage($actor, $user),
            'assignable' => $this->admin->assignableRoles($actor),
            'awaitingInvitation' => $this->admin->isAwaitingInvitation($user),
            'hasMfa' => $user->hasConfirmedMfa(),
            'organizationNames' => $organizationNames,
            'memberships' => $memberships,
            'organizations' => $this->activeOrganizations(),
        ]);
    }

    public function update(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($this->admin->canManage($actor, $user), 403);
        $data = $request->validate(['name' => ['required', 'string', 'min:3', 'max:120']]);

        $old = $user->name;
        $user->name = trim($data['name']);
        if ($user->isDirty('name')) {
            DB::transaction(function () use ($user, $actor, $audit, $old): void {
                $user->save();
                $audit->record('user.updated', $actor, 'user', $user->id, ['name' => ['from' => $old, 'to' => $user->name]]);
            });
        }

        return redirect()->route('admin.users.show', $user)->with('status', 'Profil pengguna disimpan.');
    }

    public function resendInvitation(Request $request, User $user): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->admin->resendInvitation($actor, $user);

        return redirect()->route('admin.users.show', $user)->with('status', 'Undangan baru dikirim. Tautan sebelumnya tidak berlaku lagi.');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        if ($user->status === 'deactivated') {
            $this->admin->reactivate($actor, $user, $data['reason']);
            $message = 'Akun diaktifkan kembali.';
        } else {
            $this->admin->deactivate($actor, $user, $data['reason']);
            $message = 'Akun dinonaktifkan dan semua sesinya dicabut.';
        }

        return redirect()->route('admin.users.show', $user)->with('status', $message);
    }

    public function assignRole(Request $request, User $user): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validate([
            'role' => ['required', 'string', 'max:32'],
            'organization_id' => ['nullable', 'string'],
        ]);

        $this->admin->assignRole($actor, $user, $data['role'], $data['organization_id'] ?? null);

        return redirect()->route('admin.users.show', $user)->with('status', 'Peran ditetapkan. Sesi pengguna diperbarui.');
    }

    public function revokeRole(Request $request, User $user, string $assignment): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        $this->admin->revokeRole($actor, $user, $assignment, $data['reason']);

        return redirect()->route('admin.users.show', $user)->with('status', 'Peran dicabut. Sesi pengguna diperbarui.');
    }

    public function requestSuperAdmin(Request $request, User $user): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);
        $this->admin->requestSuperAdmin($actor, $user, $data['reason']);

        return redirect()->route('admin.users.show', $user)->with('status', 'Permintaan penetapan Super Admin diajukan. Menunggu persetujuan Super Admin kedua.');
    }

    public function resetMfa(Request $request, User $user): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validate([
            'ticket_reference' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9#\/_.-]+$/'],
            'verification_method' => ['required', Rule::in(['video_call', 'document', 'in_person'])],
            'note' => ['nullable', 'string', 'max:500'],
            'confirm_identity' => ['accepted'],
        ], [
            'confirm_identity.accepted' => 'Konfirmasi bahwa identitas pemohon telah diverifikasi.',
        ]);

        $this->admin->resetMfa($actor, $user, $data['ticket_reference'], $data['verification_method'], $data['note'] ?? null);

        return redirect()->route('admin.users.show', $user)->with('status', 'MFA pengguna direset. Pengguna wajib mendaftarkan ulang saat masuk.');
    }

    /** @return Collection<int, Organization> */
    private function activeOrganizations(): Collection
    {
        return Organization::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']);
    }
}
