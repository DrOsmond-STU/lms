<x-layouts.guest title="Verifikasi Email">
    <h2 class="text-2xl font-extrabold text-slate-800">Verifikasi Email</h2>
    <p class="mt-1 mb-6 text-sm text-slate-600">Masukkan kode 6 digit yang kami kirim ke <span class="font-bold">{{ $maskedEmail }}</span>. Kode berlaku {{ config('security.registration.otp_ttl_minutes') }} menit.</p>

    <form method="POST" action="{{ route('register.verify.store') }}" class="space-y-4" novalidate>
        @csrf
        <div>
            <label for="code" class="form-label">Kode Verifikasi</label>
            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus class="form-input text-center font-mono text-lg tracking-[0.5em]">
            <x-form-error field="code" />
        </div>
        <button type="submit" class="btn-primary">Verifikasi</button>
    </form>

    <form method="POST" action="{{ route('register.resend') }}" class="mt-4">
        @csrf
        <p class="text-sm text-slate-600">Tidak menerima kode? <button type="submit" class="font-bold text-brand-700 hover:underline">Kirim ulang</button> <span class="text-xs text-slate-500">(maks. {{ config('security.registration.otp_resend_per_hour') }} kali per jam)</span></p>
    </form>
    <p class="mt-2 text-sm text-slate-600">Salah alamat email? <a href="{{ route('register') }}" class="font-bold text-brand-700 hover:underline">Daftar ulang</a></p>
</x-layouts.guest>
