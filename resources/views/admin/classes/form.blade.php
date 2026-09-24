@php($editing = $class->exists)
@php($rules = $class->completion_rules ?? [])
<x-layouts.app :title="$editing ? 'Pengaturan Kelas' : 'Tambah Kelas'" workspace="admin">
    <x-slot:back><a href="{{ $editing ? route('classes.manage', $class) : route('admin.programs.show', $program) }}" class="hero-back">&larr; {{ $editing ? 'Kelola kelas' : $program->name }}</a></x-slot:back>
    <x-slot:heading>{{ $editing ? 'Pengaturan Kelas' : 'Tambah Kelas' }}</x-slot:heading>
    <x-slot:subtitle>{{ $program->name }}</x-slot:subtitle>

    <form method="POST" action="{{ $editing ? route('admin.classes.update', $class) : route('admin.classes.store', $program) }}" class="card max-w-3xl space-y-4 p-6" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif
        <div>
            <label for="batch_name" class="form-label">Nama Batch</label>
            <input id="batch_name" name="batch_name" value="{{ old('batch_name', $class->batch_name) }}" required maxlength="120" class="form-input" placeholder="Batch 1 — Januari 2027">
            <x-form-error field="batch_name" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="starts_on" class="form-label">Mulai</label>
                <input id="starts_on" name="starts_on" type="date" value="{{ old('starts_on', $class->starts_on?->format('Y-m-d')) }}" required class="form-input">
                <x-form-error field="starts_on" />
            </div>
            <div>
                <label for="ends_on" class="form-label">Selesai</label>
                <input id="ends_on" name="ends_on" type="date" value="{{ old('ends_on', $class->ends_on?->format('Y-m-d')) }}" required class="form-input">
                <x-form-error field="ends_on" />
            </div>
            <div>
                <label for="enroll_opens_at" class="form-label">Pendaftaran dibuka (opsional)</label>
                <input id="enroll_opens_at" name="enroll_opens_at" type="datetime-local" value="{{ old('enroll_opens_at', $class->enroll_opens_at?->format('Y-m-d\TH:i')) }}" class="form-input">
            </div>
            <div>
                <label for="enroll_closes_at" class="form-label">Pendaftaran ditutup (opsional)</label>
                <input id="enroll_closes_at" name="enroll_closes_at" type="datetime-local" value="{{ old('enroll_closes_at', $class->enroll_closes_at?->format('Y-m-d\TH:i')) }}" class="form-input">
                <x-form-error field="enroll_closes_at" />
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="quota" class="form-label">Kuota</label>
                <input id="quota" name="quota" type="number" min="1" max="5000" value="{{ old('quota', $class->quota) }}" class="form-input">
                <x-form-error field="quota" />
            </div>
            <div>
                <label for="mode" class="form-label">Mode</label>
                <select id="mode" name="mode" class="form-select">
                    @foreach (\App\Modules\Catalog\Models\Program::MODES as $value => $label)
                        <option value="{{ $value }}" @selected(old('mode', $class->mode) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="location" class="form-label">Lokasi (offline)</label>
                <input id="location" name="location" value="{{ old('location', $class->location) }}" maxlength="200" class="form-input">
            </div>
        </div>
        <div>
            <label for="restricted_organization_id" class="form-label">Khusus organisasi (opsional)</label>
            <select id="restricted_organization_id" name="restricted_organization_id" class="form-select">
                <option value="">— Terbuka untuk umum —</option>
                @foreach ($organizations as $organization)
                    <option value="{{ $organization->id }}" @selected(old('restricted_organization_id', $class->restricted_organization_id) === $organization->id)>{{ $organization->name }} ({{ $organization->code }})</option>
                @endforeach
            </select>
        </div>
        <fieldset class="rounded-lg border border-slate-200 p-4">
            <legend class="px-1 text-xs font-bold text-slate-600">Syarat kelulusan</legend>
            <p class="mb-3 text-xs text-slate-500">Semua lesson wajib & kuis wajib harus selesai. Tambahan:</p>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="require_final_exam" value="1" @checked(old('require_final_exam', $rules['require_final_exam'] ?? true))> Wajib lulus ujian akhir</label>
            <div class="mt-3 max-w-xs">
                <label for="min_final_score" class="form-label">Skor minimal ujian akhir (kosong = skor program: {{ fmt_score($program->passing_score) }})</label>
                <input id="min_final_score" name="min_final_score" type="number" min="0" max="100" step="0.01" value="{{ old('min_final_score', $rules['min_final_score'] ?? '') }}" class="form-input">
            </div>
        </fieldset>
        <button type="submit" class="btn-primary w-auto">Simpan</button>
    </form>

    @if ($editing)
        <section class="card mt-6 max-w-3xl p-6" aria-labelledby="enroll-heading">
            <h2 id="enroll-heading" class="font-bold text-slate-800">Daftarkan Peserta</h2>
            <p class="mt-1 mb-3 text-sm text-slate-600">Untuk program ditanggung organisasi/beasiswa. Peserta harus sudah memiliki akun peserta.</p>
            <form method="POST" action="{{ route('admin.classes.enroll', $class) }}" class="grid gap-3 sm:grid-cols-2" novalidate>@csrf
                <div><label for="enroll_email" class="form-label">Email peserta</label><input id="enroll_email" name="email" type="email" maxlength="254" class="form-input"><x-form-error field="email" /></div>
                <div><label for="enroll_reason" class="form-label">Alasan</label><input id="enroll_reason" name="reason" maxlength="500" class="form-input"><x-form-error field="reason" /></div>
                <x-form-error field="class" />
                <div><button class="btn-secondary">Daftarkan</button></div>
            </form>
        </section>
    @endif
</x-layouts.app>
