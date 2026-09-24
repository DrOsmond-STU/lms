@php($editing = $testimonial->exists)
<x-layouts.app :title="$editing ? 'Ubah Testimoni' : 'Tambah Testimoni'" workspace="admin">
    <x-slot:back><a href="{{ route('admin.landing.testimonials.index') }}" class="hero-back">&larr; Testimoni</a></x-slot:back>
    <x-slot:heading>{{ $editing ? 'Ubah Testimoni' : 'Tambah Testimoni' }}</x-slot:heading>
    <x-slot:subtitle>Nama dan kutipan adalah data pribadi: pastikan ada persetujuan tertulis (mis. email/formulir) sebelum diterbitkan.</x-slot:subtitle>

    <form method="POST" action="{{ $editing ? route('admin.landing.testimonials.update', $testimonial) : route('admin.landing.testimonials.store') }}" class="card max-w-3xl space-y-4 p-6" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif
        @if ($testimonial->is_sample)
            <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Ini testimoni <b>contoh</b> untuk uji coba. Menyimpan perubahan menjadikannya testimoni biasa.</p>
        @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label for="name" class="form-label">Nama</label><input id="name" name="name" value="{{ old('name', $testimonial->name) }}" maxlength="120" required class="form-input"><x-form-error field="name" /></div>
            <div><label for="role_title" class="form-label">Jabatan / peran (opsional)</label><input id="role_title" name="role_title" value="{{ old('role_title', $testimonial->role_title) }}" maxlength="120" class="form-input" placeholder="Mahasiswa, HR Manager, …"><x-form-error field="role_title" /></div>
            <div><label for="organization_name" class="form-label">Instansi (opsional)</label><input id="organization_name" name="organization_name" value="{{ old('organization_name', $testimonial->organization_name) }}" maxlength="160" class="form-input"><x-form-error field="organization_name" /></div>
            <div><label for="program_name" class="form-label">Program yang diikuti (opsional)</label><input id="program_name" name="program_name" value="{{ old('program_name', $testimonial->program_name) }}" maxlength="200" class="form-input"><x-form-error field="program_name" /></div>
        </div>
        <div><label for="quote" class="form-label">Kutipan</label><textarea id="quote" name="quote" maxlength="600" rows="4" required class="form-input">{{ old('quote', $testimonial->quote) }}</textarea><x-form-error field="quote" /></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label for="rating" class="form-label">Nilai</label><select id="rating" name="rating" class="form-select">@foreach ([5, 4, 3, 2, 1] as $star)<option value="{{ $star }}" @selected((int) old('rating', $testimonial->rating) === $star)>{{ $star }} bintang</option>@endforeach</select><x-form-error field="rating" /></div>
            <div><label for="position" class="form-label">Urutan</label><input id="position" name="position" type="number" min="0" max="99" value="{{ old('position', $testimonial->position) }}" class="form-input"><x-form-error field="position" /></div>
        </div>
        <label class="flex items-start gap-2 text-sm text-slate-800"><input type="checkbox" name="consent_confirmed" value="1" class="mt-0.5" @checked(old('consent_confirmed', $testimonial->consent_confirmed))> <span><b>Pemberi testimoni telah menyetujui</b> nama, jabatan, instansi, dan kutipannya ditampilkan di situs publik.</span></label>
        <x-form-error field="consent_confirmed" />
        <label class="flex items-center gap-2 text-sm font-semibold text-slate-800"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $testimonial->is_published))> Terbitkan di beranda</label>
        <button type="submit" class="btn-primary w-auto">Simpan</button>
    </form>
</x-layouts.app>
