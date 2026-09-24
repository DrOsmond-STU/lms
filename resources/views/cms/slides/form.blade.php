@php($editing = $slide->exists)
<x-layouts.app :title="$editing ? 'Ubah Slide' : 'Tambah Slide'" workspace="admin">
    <x-slot:back><a href="{{ route('admin.landing.slides.index') }}" class="hero-back">&larr; Slide beranda</a></x-slot:back>
    <x-slot:heading>{{ $editing ? 'Ubah Slide' : 'Tambah Slide' }}</x-slot:heading>
    <x-slot:subtitle>Gunakan foto berorientasi lanskap (disarankan 1920×900). Gambar diproses ulang otomatis; metadata foto dihapus.</x-slot:subtitle>

    <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('admin.landing.slides.update', $slide) : route('admin.landing.slides.store') }}" class="card max-w-3xl space-y-4 p-6" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2"><label for="eyebrow" class="form-label">Label kecil (opsional)</label><input id="eyebrow" name="eyebrow" value="{{ old('eyebrow', $slide->eyebrow) }}" maxlength="80" class="form-input" placeholder="SERTIFIKASI BNSP"><x-form-error field="eyebrow" /></div>
            <div><label for="position" class="form-label">Urutan</label><input id="position" name="position" type="number" min="0" max="99" value="{{ old('position', $slide->position) }}" class="form-input"><x-form-error field="position" /></div>
        </div>
        <div><label for="title" class="form-label">Judul</label><input id="title" name="title" value="{{ old('title', $slide->title) }}" maxlength="120" required class="form-input"><x-form-error field="title" /></div>
        <div><label for="subtitle" class="form-label">Deskripsi (opsional)</label><textarea id="subtitle" name="subtitle" maxlength="300" rows="3" class="form-input">{{ old('subtitle', $slide->subtitle) }}</textarea><x-form-error field="subtitle" /></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label for="cta_label" class="form-label">Teks tombol (opsional)</label><input id="cta_label" name="cta_label" value="{{ old('cta_label', $slide->cta_label) }}" maxlength="40" class="form-input" placeholder="Lihat Pelatihan"><x-form-error field="cta_label" /></div>
            <div><label for="cta_url" class="form-label">Tautan tombol</label><input id="cta_url" name="cta_url" value="{{ old('cta_url', $slide->cta_url) }}" maxlength="300" class="form-input" placeholder="/#pelatihan"><x-form-error field="cta_url" /></div>
        </div>
        <div>
            <label for="image" class="form-label">Gambar latar (JPG/PNG/WebP, maks. 5 MB)</label>
            <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="form-input">
            <x-form-error field="image" />
            @if ($slide->image_path)
                <label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_image" value="1"> Hapus gambar saat ini</label>
            @endif
        </div>
        <label class="flex items-center gap-2 text-sm font-semibold text-slate-800"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $slide->is_active))> Tampilkan di beranda</label>
        <button type="submit" class="btn-primary w-auto">Simpan</button>
    </form>
</x-layouts.app>
