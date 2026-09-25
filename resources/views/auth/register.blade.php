<x-layouts.guest title="Daftar">
    <h2 class="text-2xl font-extrabold text-slate-800">Daftar sebagai Peserta</h2>
    <p class="mt-1 mb-6 text-sm text-slate-600">Buat akun untuk mengikuti pelatihan &amp; sertifikasi. Akun trainer dan admin dibuat oleh administrator.</p>

    <form method="POST" action="{{ route('register.store') }}" class="space-y-4" novalidate>
        @csrf
        <div>
            <label for="name" class="form-label">Nama Lengkap</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name" maxlength="120" class="form-input">
            <p class="mt-1 text-xs text-slate-500">Sesuai identitas — nama ini dicetak pada sertifikat.</p>
            <x-form-error field="name" />
        </div>
        <div>
            <label for="email" class="form-label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" maxlength="254" class="form-input">
            <x-form-error field="email" />
        </div>
        <div>
            <label for="phone" class="form-label">Nomor HP <span class="font-normal text-slate-500">(opsional)</span></label>
            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" inputmode="tel" maxlength="20" placeholder="08xxxxxxxxxx" class="form-input">
            <x-form-error field="phone" />
        </div>
        <div>
            <label for="organization_code" class="form-label">Kode Organisasi <span class="font-normal text-slate-500">(opsional)</span></label>
            <input id="organization_code" name="organization_code" type="text" value="{{ old('organization_code') }}" maxlength="8" autocomplete="off" class="form-input uppercase" aria-describedby="organization_code_help">
            <p id="organization_code_help" class="mt-1 text-xs text-slate-500">Diberikan oleh kampus/perusahaan Anda. Keanggotaan aktif otomatis bila email Anda memakai domain resmi organisasi; selain itu menunggu persetujuan Admin Organisasi.</p>
            <x-form-error field="organization_code" />
        </div>
        <div>
            <label for="referral_code" class="form-label">Kode Referral <span class="font-normal text-slate-500">(opsional)</span></label>
            <input id="referral_code" name="referral_code" type="text" value="{{ old('referral_code', strtoupper((string) (request()->query('ref') ?: request()->cookie(\App\Modules\Referral\Services\ReferralService::COOKIE)))) }}" maxlength="12" autocomplete="off" class="form-input uppercase" aria-describedby="referral_code_help">
            <p id="referral_code_help" class="mt-1 text-xs text-slate-500">Kode dari teman yang merekomendasikan platform ini; terisi otomatis bila Anda datang lewat tautan referral.</p>
            <x-form-error field="referral_code" />
        </div>
        <div>
            <label for="password" class="form-label">Kata Sandi</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" minlength="{{ config('security.password.min_participant') }}" maxlength="{{ config('security.password.max') }}" class="form-input" aria-describedby="password_help">
            <p id="password_help" class="mt-1 text-xs text-slate-500">Minimal {{ config('security.password.min_participant') }} karakter. Frasa sandi panjang lebih aman daripada kata acak pendek.</p>
            <x-form-error field="password" />
        </div>
        <div>
            <label for="password_confirmation" class="form-label">Ulangi Kata Sandi</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" maxlength="{{ config('security.password.max') }}" class="form-input">
        </div>
        <fieldset class="space-y-2 rounded-lg border border-slate-200 p-3">
            <legend class="px-1 text-xs font-bold text-slate-600">Persetujuan</legend>
            <label class="flex items-start gap-2 text-sm text-slate-700">
                <input type="checkbox" name="accept_terms" value="1" @checked(old('accept_terms')) class="mt-0.5">
                <span>Saya menyetujui <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener" class="font-bold text-link hover:underline">Syarat &amp; Ketentuan</a>.</span>
            </label>
            <x-form-error field="accept_terms" />
            <label class="flex items-start gap-2 text-sm text-slate-700">
                <input type="checkbox" name="accept_privacy" value="1" @checked(old('accept_privacy')) class="mt-0.5">
                <span>Saya telah membaca dan menyetujui <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener" class="font-bold text-link hover:underline">Kebijakan Privasi</a>, termasuk pemrosesan data pribadi saya untuk layanan pelatihan.</span>
            </label>
            <x-form-error field="accept_privacy" />
        </fieldset>
        <button type="submit" class="btn-primary">Daftar</button>
    </form>

    <p class="mt-6 text-sm text-slate-600">Sudah punya akun? <a href="{{ route('login') }}" class="font-bold text-link hover:underline">Masuk</a></p>
</x-layouts.guest>
