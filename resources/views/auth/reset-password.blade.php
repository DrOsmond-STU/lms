<x-layouts.guest title="Atur Ulang Kata Sandi">
    <h2 class="text-2xl font-extrabold text-slate-800">Atur Ulang Kata Sandi</h2>
    <p class="mt-1 mb-6 text-sm text-slate-600">Gunakan minimal 8 karakter (12 untuk trainer &amp; admin). Frasa panjang yang mudah diingat lebih aman daripada kombinasi rumit yang pendek.</p>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label for="email" class="form-label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="username" maxlength="254" class="form-input">
            <x-form-error field="email" />
        </div>
        <div>
            <label for="password" class="form-label">Kata Sandi Baru</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" maxlength="128" class="form-input">
            <x-form-error field="password" />
        </div>
        <div>
            <label for="password_confirmation" class="form-label">Ulangi Kata Sandi Baru</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" maxlength="128" class="form-input">
        </div>
        <button type="submit" class="btn-primary">Simpan Kata Sandi</button>
    </form>
</x-layouts.guest>
