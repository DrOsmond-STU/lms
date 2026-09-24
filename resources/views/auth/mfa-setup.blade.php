<x-layouts.guest title="Aktifkan Autentikasi Dua Faktor">
    <h2 class="text-2xl font-extrabold text-slate-800">Autentikasi Dua Faktor</h2>
    @if ($alreadyEnabled)
        <p class="mt-1 mb-4 text-sm text-slate-600">Autentikasi dua faktor sudah aktif. Mendaftarkan ulang akan mengganti aplikasi autentikator dan kode pemulihan lama.</p>
    @else
        <p class="mt-1 mb-4 text-sm text-slate-600">Pindai kode QR dengan aplikasi autentikator (mis. Google Authenticator, Microsoft Authenticator, 1Password), lalu masukkan kode 6 digit yang muncul.</p>
    @endif

    <div class="card mb-5 flex flex-col items-center gap-3 p-5">
        <img src="{{ $qrDataUri }}" alt="Kode QR pendaftaran autentikator" width="200" height="200" class="h-48 w-48">
        <p class="text-xs text-slate-600">Atau masukkan kunci secara manual:</p>
        <code class="rounded bg-slate-100 px-3 py-1.5 font-mono text-sm tracking-wider text-slate-800 select-all">{{ $secret }}</code>
    </div>

    <form method="POST" action="{{ route('mfa.setup.store') }}" class="space-y-4">
        @csrf
        <div>
            <label for="password" class="form-label">Kata Sandi Saat Ini</label>
            <input id="password" name="password" type="password" required autocomplete="current-password" maxlength="128" class="form-input">
            <x-form-error field="password" />
        </div>
        <div>
            <label for="code" class="form-label">Kode dari Aplikasi</label>
            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required class="form-input text-center font-mono tracking-[0.4em]">
            <x-form-error field="code" />
        </div>
        <button type="submit" class="btn-primary">Aktifkan</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-6 text-center">
        @csrf
        <button type="submit" class="btn-mini">Keluar</button>
    </form>
</x-layouts.guest>
