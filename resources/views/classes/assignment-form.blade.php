@php($editing = $assignment->exists)
@php($rubricRows = old('rubric', $assignment->rubric ?? []))
<x-layouts.app :title="$editing ? 'Ubah Tugas' : 'Tugas Baru'" :workspace="$workspace">
    <x-slot:back><a href="{{ route('classes.assignments', $class) }}" class="hero-back">&larr; Tugas {{ $class->batch_name }}</a></x-slot:back>
    <x-slot:heading>{{ $editing ? 'Ubah Tugas' : 'Tugas Baru' }}</x-slot:heading>
    <x-slot:subtitle>{{ $class->program->name }} — {{ $class->batch_name }}</x-slot:subtitle>

    <form method="POST" action="{{ $editing ? route('assignments.update', [$class, $assignment]) : route('assignments.store', $class) }}" class="card max-w-3xl space-y-4 p-6" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif
        <div><label for="title" class="form-label">Judul</label><input id="title" name="title" value="{{ old('title', $assignment->title) }}" required maxlength="200" class="form-input"><x-form-error field="title" /></div>
        <div><label for="instructions_md" class="form-label">Instruksi (Markdown)</label><textarea id="instructions_md" name="instructions_md" rows="8" maxlength="20000" class="form-input">{{ old('instructions_md', $assignment->instructions_md) }}</textarea><x-form-error field="instructions_md" /></div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div><label for="due_at" class="form-label">Tenggat (opsional)</label><input id="due_at" name="due_at" type="datetime-local" value="{{ old('due_at', $assignment->due_at?->timezone(display_tz())->format('Y-m-d\TH:i')) }}" class="form-input"><x-form-error field="due_at" /></div>
            <div><label for="max_score" class="form-label">Skor maksimal</label><input id="max_score" name="max_score" type="number" min="1" max="1000" step="0.5" value="{{ old('max_score', $assignment->max_score) }}" class="form-input"><x-form-error field="max_score" /></div>
            <div><label for="passing_score" class="form-label">Skor minimal (opsional)</label><input id="passing_score" name="passing_score" type="number" min="0" step="0.5" value="{{ old('passing_score', $assignment->passing_score) }}" class="form-input"><x-form-error field="passing_score" /></div>
        </div>
        <div class="grid gap-2 text-sm sm:grid-cols-2">
            <label class="flex items-center gap-2"><input type="checkbox" name="is_required" value="1" @checked(old('is_required', $assignment->is_required))> Wajib (syarat kelulusan)</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="allow_late" value="1" @checked(old('allow_late', $assignment->allow_late))> Terima pengumpulan terlambat</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="allow_text" value="1" @checked(old('allow_text', $assignment->allow_text))> Terima jawaban teks</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="allow_file" value="1" @checked(old('allow_file', $assignment->allow_file))> Terima unggahan berkas (PDF/dokumen/gambar/zip, maks. {{ config('media.types.submission.max_mb') }} MB)</label>
        </div>
        <x-form-error field="allow_text" />
        <fieldset class="rounded-lg border border-slate-200 p-4">
            <legend class="px-1 text-xs font-bold text-slate-600">Rubrik penilaian (opsional)</legend>
            <p class="mb-2 text-xs text-slate-500">Skor akhir = jumlah skor kriteria diskalakan ke skor maksimal. Kosongkan nama untuk baris yang tidak dipakai.</p>
            @for ($i = 0; $i < max(3, count($rubricRows) + 1) && $i < 8; $i++)
                <div class="mb-2 grid gap-2 sm:grid-cols-[2fr_1fr_3fr]">
                    <input name="rubric[{{ $i }}][name]" value="{{ $rubricRows[$i]['name'] ?? '' }}" maxlength="120" class="form-input py-1.5" placeholder="Kriteria {{ $i + 1 }}" aria-label="Nama kriteria {{ $i + 1 }}">
                    <input name="rubric[{{ $i }}][max]" type="number" min="0" max="1000" step="0.5" value="{{ $rubricRows[$i]['max'] ?? '' }}" class="form-input py-1.5" placeholder="Poin maks." aria-label="Poin maksimal kriteria {{ $i + 1 }}">
                    <input name="rubric[{{ $i }}][description]" value="{{ $rubricRows[$i]['description'] ?? '' }}" maxlength="300" class="form-input py-1.5" placeholder="Deskripsi singkat" aria-label="Deskripsi kriteria {{ $i + 1 }}">
                </div>
            @endfor
        </fieldset>
        <button type="submit" class="btn-primary w-auto">Simpan</button>
    </form>
</x-layouts.app>
