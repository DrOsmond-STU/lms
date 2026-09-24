{{-- Pilihan peran & organisasi. $assignable: list<RoleCode>; $organizations; $selectedRole; $selectedOrganization --}}
<div>
    <label for="role" class="form-label">Peran</label>
    <select id="role" name="role" required class="form-select">
        @foreach ($assignable as $item)
            <option value="{{ $item->value }}" @selected(old('role', $selectedRole ?? '') === $item->value)>{{ $item->label() }}{{ $item->isPlatform() ? ' (platform)' : '' }}</option>
        @endforeach
    </select>
    <x-form-error field="role" />
</div>
<div>
    <label for="organization_id" class="form-label">Organisasi</label>
    <select id="organization_id" name="organization_id" class="form-select" aria-describedby="organization_help">
        <option value="">— Tanpa organisasi (STU / umum) —</option>
        @foreach ($organizations as $organization)
            <option value="{{ $organization->id }}" @selected(old('organization_id', $selectedOrganization ?? '') === $organization->id)>{{ $organization->name }} ({{ $organization->code }})</option>
        @endforeach
    </select>
    <p id="organization_help" class="mt-1 text-xs text-slate-500">Wajib untuk Admin Organisasi. Diabaikan untuk peran platform.</p>
    <x-form-error field="organization_id" />
</div>
