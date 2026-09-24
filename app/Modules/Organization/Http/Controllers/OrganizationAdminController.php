<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Modules\Access\RoleCode;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Organization;
use App\Support\Database\Like;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Master organisasi (FR-ORG-001, docs/08 ADM-06). Arsip, bukan hapus. Kode organisasi
 * tidak dapat diubah setelah dibuat karena dipakai pada nomor sertifikat.
 */
final class OrganizationAdminController
{
    public const ACCREDITATIONS = ['Unggul', 'Baik Sekali', 'Baik', 'A', 'B', 'C'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $search = Str::limit(trim((string) $request->query('q', '')), 100, '');
        $type = in_array($request->query('tipe'), ['institution', 'corporate'], true) ? (string) $request->query('tipe') : null;
        $status = in_array($request->query('status'), ['active', 'inactive'], true) ? (string) $request->query('status') : null;

        $organizations = Organization::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($where) => $where
                ->where('name', 'ilike', Like::contains($search))
                ->orWhere('code', strtoupper($search))))
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->withCount(['members as active_members_count' => fn ($query) => $query->where('status', 'active')])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.organizations.index', compact('organizations', 'search', 'type', 'status'));
    }

    public function create(): View
    {
        return view('admin.organizations.form', ['organization' => new Organization, 'accreditations' => self::ACCREDITATIONS]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate($this->rules() + [
            'code' => ['required', 'string', 'regex:/^[A-Z]{2,8}$/', Rule::unique('organizations', 'code')],
            'type' => ['required', Rule::in(['institution', 'corporate'])],
        ], ['code.regex' => 'Kode terdiri dari 2–8 huruf.', 'code.unique' => 'Kode organisasi sudah dipakai.']);

        /** @var User $actor */
        $actor = $request->user();
        $organization = DB::transaction(function () use ($data, $actor): Organization {
            $organization = new Organization;
            $organization->forceFill($this->attributes($data) + ['code' => $data['code'], 'type' => $data['type'], 'status' => 'active'])->save();
            $this->audit->record('organization.created', $actor, 'organization', $organization->id, $organization->only(['name', 'code', 'type']), organizationId: $organization->id);

            return $organization;
        });

        return redirect()->route('admin.organizations.show', $organization)->with('status', 'Organisasi berhasil dibuat.');
    }

    public function show(Organization $organization): View
    {
        $domains = DB::table('organization_domains')->where('organization_id', $organization->id)->orderBy('domain')->get();
        $admins = User::query()
            ->whereIn('id', DB::table('role_user')
                ->join('roles', 'roles.id', '=', 'role_user.role_id')
                ->where('roles.code', RoleCode::OrgAdmin->value)
                ->where('role_user.organization_id', $organization->id)
                ->select('role_user.user_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'status']);
        $members = DB::table('organization_members')->where('organization_id', $organization->id)
            ->selectRaw("count(*) filter (where status = 'active') as active, count(*) filter (where status = 'pending') as pending")
            ->first();

        return view('admin.organizations.show', [
            'organization' => $organization,
            'domains' => $domains,
            'admins' => $admins,
            'members' => $members,
            'accreditations' => self::ACCREDITATIONS,
        ]);
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate($this->rules($organization->type));

        /** @var User $actor */
        $actor = $request->user();
        $organization->forceFill($this->attributes($data, $organization->type));
        $changes = $organization->getDirty();
        if ($changes !== []) {
            DB::transaction(function () use ($organization, $actor, $changes): void {
                $organization->save();
                $this->audit->record('organization.updated', $actor, 'organization', $organization->id, $changes, organizationId: $organization->id);
            });
        }

        return redirect()->route('admin.organizations.show', $organization)->with('status', 'Perubahan disimpan.');
    }

    public function toggleStatus(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $next = $organization->status === 'active' ? 'inactive' : 'active';

        /** @var User $actor */
        $actor = $request->user();
        DB::transaction(function () use ($organization, $next, $actor, $data): void {
            $organization->forceFill(['status' => $next])->save();
            $this->audit->record($next === 'inactive' ? 'organization.archived' : 'organization.reactivated', $actor, 'organization', $organization->id, ['status' => $next], $data['reason'], $organization->id);
        });

        return redirect()->route('admin.organizations.show', $organization)
            ->with('status', $next === 'inactive' ? 'Organisasi diarsipkan. Registrasi baru dengan kode ini ditolak.' : 'Organisasi diaktifkan kembali.');
    }

    public function addDomain(Request $request, Organization $organization): RedirectResponse
    {
        $request->merge(['domain' => mb_strtolower(trim((string) $request->input('domain')))]);
        $data = $request->validate([
            'domain' => [
                'required', 'string', 'max:253',
                'regex:/^(?=.{4,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/',
                Rule::notIn(config('security.public_email_domains', [])),
                Rule::unique('organization_domains', 'domain'),
            ],
        ], [
            'domain.regex' => 'Format domain tidak valid (contoh: kampus.ac.id).',
            'domain.not_in' => 'Domain email publik tidak dapat dipakai sebagai domain organisasi.',
            'domain.unique' => 'Domain sudah terdaftar pada organisasi lain.',
        ]);

        /** @var User $actor */
        $actor = $request->user();
        DB::transaction(function () use ($organization, $data, $actor): void {
            DB::table('organization_domains')->insert([
                'id' => (string) Str::uuid7(),
                'organization_id' => $organization->id,
                'domain' => $data['domain'],
                'verified_at' => now(),
                'method' => 'admin',
                'created_by' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->audit->record('organization.domain_added', $actor, 'organization', $organization->id, ['domain' => $data['domain']], organizationId: $organization->id);
        });

        return redirect()->route('admin.organizations.show', $organization)->with('status', "Domain {$data['domain']} ditambahkan.");
    }

    public function removeDomain(Request $request, Organization $organization, string $domain): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $row = DB::table('organization_domains')->where('organization_id', $organization->id)->where('id', $domain)->first();
        abort_if($row === null, 404);

        DB::transaction(function () use ($organization, $row, $actor): void {
            DB::table('organization_domains')->where('id', $row->id)->delete();
            $this->audit->record('organization.domain_removed', $actor, 'organization', $organization->id, ['domain' => $row->domain], organizationId: $organization->id);
        });

        return redirect()->route('admin.organizations.show', $organization)->with('status', "Domain {$row->domain} dihapus.");
    }

    /** @return array<string, list<mixed>> */
    private function rules(?string $type = null): array
    {
        $type ??= request()->input('type');

        return [
            'name' => ['required', 'string', 'min:3', 'max:200'],
            'city' => ['nullable', 'string', 'max:100'],
            'accreditation' => $type === 'institution' ? ['nullable', Rule::in(self::ACCREDITATIONS)] : ['exclude'],
            'industry' => $type === 'corporate' ? ['nullable', 'string', 'max:120'] : ['exclude'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, ?string $type = null): array
    {
        $type ??= $data['type'] ?? null;

        return [
            'name' => trim((string) $data['name']),
            'city' => isset($data['city']) ? trim((string) $data['city']) : null,
            'accreditation' => $type === 'institution' ? ($data['accreditation'] ?? null) : null,
            'industry' => $type === 'corporate' ? ($data['industry'] ?? null) : null,
        ];
    }
}
