@php($editing = $partner->exists)
<x-layouts.app :title="$editing ? 'Ubah Mitra' : 'Tambah Mitra'" workspace="admin">
    <x-slot:back><a href="{{ route('admin.landing.partners.index') }}" class="hero-back">&larr; Mitra pengguna</a></x-slot:back>
    <x-slot:heading>{{ $editing ? 'Ubah Mitra' : 'Tambah Mitra' }}</x-slot:heading>
    <x-slot:subtitle>Logo PNG/WebP berlatar transparan paling rapi (disarankan tinggi ≥ 120 px). SVG tidak diterima demi keamanan.</x-slot:subtitle>

    <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('admin.landing.partners.update', $partner) : route('admin.landing.partners.store') }}" class="card max-w-3xl space-y-4 p-6" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2"><label for="name" class="form-label">Nama instansi</label><input id="name" name="name" value="{{ old('name', $partner->name) }}" maxlength="160" required class="form-input"><x-form-error field="name" /></div>
            <div><label for="type" class="form-label">Jenis</label><select id="type" name="type" class="form-select">@foreach (\App\Modules\Cms\Models\LandingPartner::TYPES as $value => $label)<option value="{{ $value }}" @selected(old('type', $partner->type) === $value)>{{ $label }}</option>@endforeach</select><x-form-error field="type" /></div>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2"><label for="website_url" class="form-label">Situs web (opsional)</label><input id="website_url" name="website_url" value="{{ old('website_url', $partner->website_url) }}" maxlength="300" class="form-input" placeholder="https://"><x-form-error field="website_url" /></div>
            <div><label for="position" class="form-label">Urutan</label><input id="position" name="position" type="number" min="0" max="99" value="{{ old('position', $partner->position) }}" class="form-input"><x-form-error field="position" /></div>
        </div>
        <div>
            <label for="logo" class="form-label">Logo (JPG/PNG/WebP, maks. 5 MB)</label>
            <input id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp" class="form-input">
            <x-form-error field="logo" />
            @if ($partner->logo_path)
                <div class="mt-3 flex items-center gap-4">@include('cms._partner-mark', ['partner' => $partner])<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remove_logo" value="1"> Hapus logo</label></div>
            @endif
        </div>
        <label class="flex items-center gap-2 text-sm font-semibold text-slate-800"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $partner->is_active))> Tampilkan di beranda</label>
        <button type="submit" class="btn-primary w-auto">Simpan</button>
    </form>
</x-layouts.app>
