{{-- Field profil organisasi. $organization: model; $editing: bool (kode & tipe terkunci saat edit). --}}
<div>
    <label for="name" class="form-label">Nama Organisasi</label>
    <input id="name" name="name" type="text" value="{{ old('name', $organization->name) }}" required maxlength="200" class="form-input">
    <x-form-error field="name" />
</div>
@if (! $editing)
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="code" class="form-label">Kode (singkatan)</label>
            <input id="code" name="code" type="text" value="{{ old('code') }}" required maxlength="8" autocomplete="off" class="form-input font-mono uppercase" aria-describedby="code_help">
            <p id="code_help" class="mt-1 text-xs text-slate-500">2–8 huruf, dipakai di nomor sertifikat &amp; tidak dapat diubah.</p>
            <x-form-error field="code" />
        </div>
        <div>
            <label for="type" class="form-label">Tipe</label>
            <select id="type" name="type" required class="form-select">
                <option value="institution" @selected(old('type') === 'institution')>Institusi pendidikan</option>
                <option value="corporate" @selected(old('type') === 'corporate')>Korporat</option>
            </select>
            <x-form-error field="type" />
        </div>
    </div>
@endif
<div>
    <label for="city" class="form-label">Kota</label>
    <input id="city" name="city" type="text" value="{{ old('city', $organization->city) }}" maxlength="100" class="form-input">
    <x-form-error field="city" />
</div>
@if (! $editing || $organization->type === 'institution')
    <div>
        <label for="accreditation" class="form-label">Akreditasi {{ $editing ? '' : '(khusus institusi)' }}</label>
        <select id="accreditation" name="accreditation" class="form-select">
            <option value="">—</option>
            @foreach ($accreditations as $accreditation)
                <option value="{{ $accreditation }}" @selected(old('accreditation', $organization->accreditation) === $accreditation)>{{ $accreditation }}</option>
            @endforeach
        </select>
        <x-form-error field="accreditation" />
    </div>
@endif
@if (! $editing || $organization->type === 'corporate')
    <div>
        <label for="industry" class="form-label">Industri {{ $editing ? '' : '(khusus korporat)' }}</label>
        <input id="industry" name="industry" type="text" value="{{ old('industry', $organization->industry) }}" maxlength="120" class="form-input">
        <x-form-error field="industry" />
    </div>
@endif
