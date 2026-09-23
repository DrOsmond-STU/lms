<x-layouts.guest title="Konfirmasi Akses">
    <h2 class="text-2xl font-extrabold text-slate-800">Konfirmasi Akses</h2>
    <p class="mt-1 mb-6 text-sm text-slate-600">Tindakan ini sensitif. Konfirmasi identitas Anda untuk melanjutkan.</p>
    <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-4">
        @csrf
        <div>
            <label for="password" class="form-label">Kata Sandi</label>
            <input id="password" name="password" type="password" required autocomplete="current-password" maxlength="128" class="form-input">
            <x-form-error field="password" />
        </div>
        @if ($needsCode)
            <div>
                <label for="code" class="form-label">Kode Autentikasi</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required class="form-input text-center font-mono tracking-[0.4em]">
            </div>
        @endif
        <button type="submit" class="btn-primary">Konfirmasi</button>
    </form>
</x-layouts.guest>
