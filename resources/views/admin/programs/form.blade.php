@php($editing = $program->exists)
<x-layouts.app :title="$editing ? 'Ubah Program' : 'Tambah Program'" workspace="admin">
    <a href="{{ $editing ? route('admin.programs.show', $program) : route('admin.programs.index') }}" class="text-sm font-bold text-brand-700 hover:underline">&larr; {{ $editing ? $program->name : 'Program Pelatihan' }}</a>
    <h1 class="mt-2 mb-6 text-xl font-extrabold text-slate-800">{{ $editing ? 'Ubah Program' : 'Tambah Program' }}</h1>

    <form method="POST" action="{{ $editing ? route('admin.programs.update', $program) : route('admin.programs.store') }}" class="card max-w-3xl space-y-4 p-6" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif
        <div>
            <label for="name" class="form-label">Nama Program</label>
            <input id="name" name="name" type="text" value="{{ old('name', $program->name) }}" required maxlength="200" class="form-input">
            <x-form-error field="name" />
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="category" class="form-label">Kategori</label>
                <select id="category" name="category" class="form-select">
                    @foreach (\App\Modules\Catalog\Models\Program::CATEGORIES as $value => $label)
                        <option value="{{ $value }}" @selected(old('category', $program->category) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-form-error field="category" />
            </div>
            <div>
                <label for="short_code" class="form-label">Kode Singkat</label>
                @if ($editing)
                    <input id="short_code" type="text" value="{{ $program->short_code }}" disabled class="form-input bg-slate-50 font-mono">
                @else
                    <input id="short_code" name="short_code" type="text" value="{{ old('short_code') }}" required maxlength="16" class="form-input font-mono uppercase" aria-describedby="short_code_help">
                    <p id="short_code_help" class="mt-1 text-xs text-slate-500">Dipakai di nomor sertifikat, tidak dapat diubah.</p>
                @endif
                <x-form-error field="short_code" />
            </div>
            <div>
                <label for="scheme_code" class="form-label">Kode Skema (opsional)</label>
                <input id="scheme_code" name="scheme_code" type="text" value="{{ old('scheme_code', $program->scheme_code) }}" maxlength="60" class="form-input">
                <x-form-error field="scheme_code" />
            </div>
        </div>
        <div>
            <label for="provider_name" class="form-label">Penyelenggara / LSP</label>
            <input id="provider_name" name="provider_name" type="text" value="{{ old('provider_name', $program->provider_name) }}" required maxlength="200" class="form-input">
            <x-form-error field="provider_name" />
        </div>
        <div class="grid gap-4 sm:grid-cols-4">
            <div>
                <label for="level" class="form-label">Level</label>
                <select id="level" name="level" class="form-select">
                    <option value="">—</option>
                    @foreach (\App\Modules\Catalog\Models\Program::LEVELS as $value => $label)
                        <option value="{{ $value }}" @selected(old('level', $program->level) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="default_mode" class="form-label">Mode</label>
                <select id="default_mode" name="default_mode" class="form-select">
                    @foreach (\App\Modules\Catalog\Models\Program::MODES as $value => $label)
                        <option value="{{ $value }}" @selected(old('default_mode', $program->default_mode ?? 'online') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="language" class="form-label">Bahasa</label>
                <select id="language" name="language" class="form-select">
                    <option value="id" @selected(old('language', $program->language ?? 'id') === 'id')>Indonesia</option>
                    <option value="en" @selected(old('language', $program->language) === 'en')>Inggris</option>
                </select>
            </div>
            <div>
                <label for="duration_hours" class="form-label">Durasi (jam)</label>
                <input id="duration_hours" name="duration_hours" type="number" min="0" max="2000" value="{{ old('duration_hours', $program->duration_hours ?? 0) }}" class="form-input">
                <x-form-error field="duration_hours" />
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="passing_score" class="form-label">Skor Minimal Lulus</label>
                <input id="passing_score" name="passing_score" type="number" min="0" max="100" step="0.01" value="{{ old('passing_score', $program->passing_score) }}" class="form-input">
                <x-form-error field="passing_score" />
            </div>
            <div>
                <label for="certificate_validity_months" class="form-label">Masa Berlaku Sertifikat (bulan)</label>
                <input id="certificate_validity_months" name="certificate_validity_months" type="number" min="0" max="240" value="{{ old('certificate_validity_months', $program->certificate_validity_months ?? 36) }}" class="form-input" aria-describedby="validity_help">
                <p id="validity_help" class="mt-1 text-xs text-slate-500">0 = tanpa kedaluwarsa.</p>
                <x-form-error field="certificate_validity_months" />
            </div>
            <div>
                <label for="price" class="form-label">Harga (IDR)</label>
                <input id="price" name="price" type="number" min="0" step="1000" value="{{ old('price', $program->price ?? 0) }}" class="form-input" aria-describedby="price_help">
                <p id="price_help" class="mt-1 text-xs text-slate-500">0 = gratis / ditanggung organisasi.</p>
                <x-form-error field="price" />
            </div>
        </div>
        <div>
            <label for="description_md" class="form-label">Deskripsi (Markdown)</label>
            <textarea id="description_md" name="description_md" rows="8" maxlength="20000" class="form-input font-mono text-xs" aria-describedby="desc_help">{{ old('description_md', $program->description_md) }}</textarea>
            <p id="desc_help" class="mt-1 text-xs text-slate-500">Mendukung **tebal**, *miring*, daftar, judul (##), dan tautan https. HTML tidak dirender.</p>
            <x-form-error field="description_md" />
        </div>
        <div>
            <label for="tags" class="form-label">Tag (pisahkan dengan koma, maks. 10)</label>
            <input id="tags" name="tags" type="text" value="{{ old('tags', $tags) }}" maxlength="400" class="form-input">
            <x-form-error field="tags" />
        </div>
        <button type="submit" class="btn-primary w-auto">Simpan</button>
    </form>
</x-layouts.app>
