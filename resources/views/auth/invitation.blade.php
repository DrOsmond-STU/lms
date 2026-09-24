<x-layouts.guest title="Undangan Akun">
    @if ($user === null)
        <h2 class="text-2xl font-extrabold text-slate-800">Undangan Tidak Berlaku</h2>
        <p class="mt-2 text-sm text-slate-600">{{ \App\Modules\Identity\Http\Controllers\InvitationController::INVALID }}</p>
        <a href="{{ route('login') }}" class="btn-primary mt-6">Ke Halaman Masuk</a>
    @else
        <h2 class="text-2xl font-extrabold text-slate-800">Atur Kata Sandi</h2>
        <p class="mt-1 mb-6 text-sm text-slate-600">Halo {{ $user->name }}, buat kata sandi untuk mengaktifkan akun Anda.</p>
        <form method="POST" action="{{ route('invitation.accept', $token) }}" class="space-y-4" novalidate>
            @csrf
            <div>
                <label for="password" class="form-label">Kata Sandi</label>
                <input id="password" name="password" type="password" required autocomplete="new-password" minlength="{{ $minPassword }}" maxlength="{{ config('security.password.max') }}" class="form-input" aria-describedby="password_help">
                <p id="password_help" class="mt-1 text-xs text-slate-500">Minimal {{ $minPassword }} karakter. Gunakan frasa sandi yang panjang dan unik.</p>
                <x-form-error field="password" />
            </div>
            <div>
                <label for="password_confirmation" class="form-label">Ulangi Kata Sandi</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" maxlength="{{ config('security.password.max') }}" class="form-input">
            </div>
            <fieldset class="space-y-2 rounded-lg border border-slate-200 p-3">
                <legend class="px-1 text-xs font-bold text-slate-600">Persetujuan</legend>
                <label class="flex items-start gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="accept_terms" value="1" class="mt-0.5">
                    <span>Saya menyetujui <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener" class="font-bold text-brand-700 hover:underline">Syarat &amp; Ketentuan</a>.</span>
                </label>
                <x-form-error field="accept_terms" />
                <label class="flex items-start gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="accept_privacy" value="1" class="mt-0.5">
                    <span>Saya menyetujui <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener" class="font-bold text-brand-700 hover:underline">Kebijakan Privasi</a>.</span>
                </label>
                <x-form-error field="accept_privacy" />
            </fieldset>
            <button type="submit" class="btn-primary">Aktifkan Akun</button>
        </form>
    @endif
</x-layouts.guest>
