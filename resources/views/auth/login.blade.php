<x-layouts.guest title="Masuk">
    <h2 class="text-2xl font-extrabold text-slate-800">Masuk</h2>
    <p class="mt-1 mb-6 text-sm text-slate-600">Gunakan email dan kata sandi akun Anda.</p>

    <form method="POST" action="{{ route('login.store') }}" class="space-y-4" novalidate>
        @csrf
        <div>
            <label for="email" class="form-label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" maxlength="254" class="form-input">
            <x-form-error field="email" />
        </div>
        <div>
            <div class="mb-1 flex items-center justify-between">
                <label for="password" class="form-label mb-0">Kata Sandi</label>
                <a href="{{ route('password.request') }}" class="text-xs font-bold text-link hover:underline">Lupa kata sandi?</a>
            </div>
            <input id="password" name="password" type="password" required autocomplete="current-password" maxlength="128" class="form-input">
            <x-form-error field="password" />
        </div>
        <button type="submit" class="btn-primary">Masuk</button>
    </form>

    @if (config('security.registration.enabled'))
        <p class="mt-6 text-sm text-slate-600">Belum punya akun? <a href="{{ route('register') }}" class="font-bold text-link hover:underline">Daftar sebagai peserta</a></p>
    @endif
    <p class="mt-3 text-xs text-slate-500">Akun trainer &amp; admin dibuat oleh administrator dan wajib memakai autentikasi dua faktor.</p>
</x-layouts.guest>
