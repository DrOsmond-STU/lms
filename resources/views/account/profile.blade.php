@php($locked = $profile !== null && $profile->source !== 'self')
<x-layouts.app title="Profil" :workspace="$workspace">
    <x-slot:heading>Akun Saya</x-slot:heading>
    <x-slot:subtitle>Nama Anda dicetak pada sertifikat — pastikan sesuai identitas.</x-slot:subtitle>

    @include('account._tabs')
    <form method="POST" action="{{ route('account.profile.update') }}" class="card max-w-2xl space-y-4 p-6" novalidate>
        @csrf
        @method('PUT')
        <div><label for="name" class="form-label">Nama Lengkap</label><input id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="120" class="form-input"><x-form-error field="name" /></div>
        <div><label for="email" class="form-label">Email</label><input id="email" value="{{ $user->email }}" disabled class="form-input bg-slate-50"><p class="mt-1 text-xs text-slate-500">Perubahan email (dengan verifikasi ke alamat baru) tersedia pada rilis berikutnya — hubungi admin bila perlu.</p></div>
        <div><label for="phone" class="form-label">Nomor HP</label><input id="phone" name="phone" type="tel" value="{{ old('phone', $phone) }}" maxlength="20" class="form-input"><x-form-error field="phone" /></div>
        @if ($isParticipant)
            <fieldset class="space-y-4 rounded-lg border border-slate-200 p-4" @disabled($locked)>
                <legend class="px-1 text-xs font-bold text-slate-600">Data Peserta {{ $locked ? '(dikelola organisasi — baca-saja)' : '' }}</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label for="participant_number" class="form-label">Nomor induk (NIM/NIK karyawan)</label><input id="participant_number" name="participant_number" value="{{ old('participant_number', $profile?->participant_number) }}" maxlength="40" class="form-input"></div>
                    <div><label for="department" class="form-label">Departemen / unit</label><input id="department" name="department" value="{{ old('department', $profile?->department) }}" maxlength="120" class="form-input"></div>
                    <div><label for="study_program" class="form-label">Program studi</label><input id="study_program" name="study_program" value="{{ old('study_program', $profile?->study_program) }}" maxlength="120" class="form-input"></div>
                    <div><label for="semester" class="form-label">Semester</label><input id="semester" name="semester" type="number" min="1" max="14" value="{{ old('semester', $profile?->semester) }}" class="form-input"><x-form-error field="semester" /></div>
                </div>
            </fieldset>
        @endif
        <button class="btn-primary w-auto">Simpan</button>
    </form>
</x-layouts.app>
