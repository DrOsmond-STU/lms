@php($editing = $template->exists)
<x-layouts.app :title="$editing ? 'Ubah Template' : 'Template Baru'" workspace="admin">
    <x-slot:back><a href="{{ route('admin.templates.index') }}" class="hero-back">&larr; Template Sertifikat</a></x-slot:back>
    <x-slot:heading>{{ $editing ? 'Ubah Template v'.$template->version : 'Template Baru' }}</x-slot:heading>

    <form method="POST" action="{{ $editing ? route('admin.templates.update', $template) : route('admin.templates.store') }}" class="card max-w-3xl space-y-4 p-6" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif
        <div><label for="name" class="form-label">Nama template</label><input id="name" name="name" value="{{ old('name', $template->name) }}" maxlength="120" class="form-input"><x-form-error field="name" /></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label for="category" class="form-label">Kategori</label>
                <select id="category" name="category" class="form-select" @disabled($editing)>@foreach (\App\Modules\Catalog\Models\Program::CATEGORIES as $value => $label)<option value="{{ $value }}" @selected(old('category', $template->category) === $value)>{{ $label }}</option>@endforeach</select>
                @if ($editing)<input type="hidden" name="category" value="{{ $template->category }}">@endif
            </div>
            <div><label for="program_id" class="form-label">Program (opsional)</label>
                <select id="program_id" name="program_id" class="form-select" @disabled($editing)><option value="">Semua program kategori ini</option>@foreach ($programs as $program)<option value="{{ $program->id }}" @selected(old('program_id', $template->program_id) === $program->id)>{{ $program->name }}</option>@endforeach</select>
            </div>
        </div>
        <div><label for="title_text" class="form-label">Judul</label><input id="title_text" name="title_text" value="{{ old('title_text', $template->title_text) }}" maxlength="120" class="form-input"><x-form-error field="title_text" /></div>
        <div>
            <label for="body_text" class="form-label">Kalimat isi</label>
            <textarea id="body_text" name="body_text" rows="3" maxlength="500" class="form-input" aria-describedby="body_help">{{ old('body_text', $template->body_text) }}</textarea>
            <p id="body_help" class="mt-1 text-xs text-slate-500">Placeholder: {{ implode(' ', \App\Modules\Certification\Models\CertificateTemplate::PLACEHOLDERS) }}. Teks biasa — markup tidak dirender.</p>
            <x-form-error field="body_text" />
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div><label for="signatory_name" class="form-label">Nama penandatangan</label><input id="signatory_name" name="signatory_name" value="{{ old('signatory_name', $template->signatory_name) }}" maxlength="120" class="form-input"><x-form-error field="signatory_name" /></div>
            <div><label for="signatory_title" class="form-label">Jabatan</label><input id="signatory_title" name="signatory_title" value="{{ old('signatory_title', $template->signatory_title) }}" maxlength="120" class="form-input"><x-form-error field="signatory_title" /></div>
            <div><label for="accent_color" class="form-label">Warna aksen</label><input id="accent_color" name="accent_color" type="color" value="{{ old('accent_color', $template->accent_color) }}" class="form-input h-11 p-1"><x-form-error field="accent_color" /></div>
        </div>
        <button class="btn-primary w-auto">Simpan</button>
    </form>
</x-layouts.app>
