<x-layouts.guest title="Lupa Kata Sandi">
    <h2 class="text-2xl font-extrabold text-slate-800">Lupa Kata Sandi</h2>
    <p class="mt-1 mb-6 text-sm text-slate-600">Masukkan email akun Anda. Jika terdaftar, kami akan mengirimkan tautan untuk mengatur ulang kata sandi.</p>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="form-label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" maxlength="254" class="form-input">
            <x-form-error field="email" />
        </div>
        <button type="submit" class="btn-primary">Kirim Tautan</button>
    </form>
    <p class="mt-6 text-center text-xs text-slate-500"><a href="{{ route('login') }}" class="font-bold text-link hover:underline">Kembali ke halaman masuk</a></p>
</x-layouts.guest>
