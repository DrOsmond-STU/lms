<x-layouts.guest title="Verifikasi Dua Faktor">
    <h2 class="text-2xl font-extrabold text-slate-800">Verifikasi Dua Faktor</h2>
    <p class="mt-1 mb-6 text-sm text-slate-600">Masukkan kode 6 digit dari aplikasi autentikator Anda.</p>

    <form method="POST" action="{{ route('mfa.challenge.store') }}" class="space-y-4">
        @csrf
        <div>
            <label for="code" class="form-label">Kode Autentikasi</label>
            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" autofocus class="form-input text-center font-mono tracking-[0.4em]">
            <x-form-error field="code" />
        </div>
        <details class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm">
            <summary class="cursor-pointer font-bold text-slate-700">Tidak dapat mengakses aplikasi autentikator?</summary>
            <label for="recovery_code" class="form-label mt-3">Kode Pemulihan</label>
            <input id="recovery_code" name="recovery_code" type="text" autocomplete="off" maxlength="16" placeholder="XXXXX-XXXXX" class="form-input font-mono uppercase">
            <x-form-error field="recovery_code" />
        </details>
        <button type="submit" class="btn-primary">Verifikasi</button>
    </form>
    <p class="mt-6 text-center text-xs text-slate-500"><a href="{{ route('login') }}" class="font-bold text-brand-700 hover:underline">Kembali ke halaman masuk</a></p>
</x-layouts.guest>
