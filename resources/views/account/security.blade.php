<x-layouts.app title="Keamanan Akun" :workspace="$workspace">
    <h1 class="text-xl font-extrabold text-slate-800">Keamanan Akun</h1>
    <p class="mt-0.5 mb-4 text-sm text-slate-600">Kelola kata sandi, autentikasi dua faktor, dan pantau aktivitas masuk akun Anda.</p>
    @include('account._tabs')

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card p-6" aria-labelledby="password-heading">
            <h2 id="password-heading" class="font-bold text-slate-800">Ubah Kata Sandi</h2>
            <p class="mt-1 mb-4 text-sm text-slate-600">Setelah diubah, semua sesi di perangkat lain akan dikeluarkan.</p>
            <form method="POST" action="{{ route('account.password.update') }}" class="space-y-4" novalidate>
                @csrf
                <div>
                    <label for="current_password" class="form-label">Kata Sandi Saat Ini</label>
                    <input id="current_password" name="current_password" type="password" required autocomplete="current-password" maxlength="128" class="form-input">
                    <x-form-error field="current_password" />
                </div>
                <div>
                    <label for="password" class="form-label">Kata Sandi Baru</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password" minlength="{{ $minPassword }}" maxlength="{{ config('security.password.max') }}" class="form-input" aria-describedby="password_help">
                    <p id="password_help" class="mt-1 text-xs text-slate-500">Minimal {{ $minPassword }} karakter untuk peran Anda.</p>
                    <x-form-error field="password" />
                </div>
                <div>
                    <label for="password_confirmation" class="form-label">Ulangi Kata Sandi Baru</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" maxlength="{{ config('security.password.max') }}" class="form-input">
                </div>
                <button type="submit" class="btn-primary w-auto">Simpan Kata Sandi</button>
            </form>
        </section>

        <div class="space-y-6">
            <section class="card p-6" aria-labelledby="mfa-heading">
                <h2 id="mfa-heading" class="font-bold text-slate-800">Autentikasi Dua Faktor</h2>
                @if ($hasMfa)
                    <p class="mt-2 text-sm"><span class="badge bg-emerald-50 text-emerald-700">Aktif</span> <span class="ml-1 text-slate-600">Aplikasi authenticator terdaftar.</span></p>
                    <form method="POST" action="{{ route('mfa.recovery-codes.regenerate') }}" class="mt-3">
                        @csrf
                        <button type="submit" class="text-sm font-bold text-brand-700 hover:underline">Buat ulang kode pemulihan</button>
                        <p class="mt-1 text-xs text-slate-500">Kode lama langsung tidak berlaku. Memerlukan konfirmasi identitas.</p>
                    </form>
                @else
                    <p class="mt-2 text-sm"><span class="badge bg-amber-50 text-amber-700">Belum aktif</span> <span class="ml-1 text-slate-600">Lindungi akun Anda dengan aplikasi authenticator.</span></p>
                    <a href="{{ route('mfa.setup') }}" class="mt-3 inline-block text-sm font-bold text-brand-700 hover:underline">Aktifkan sekarang</a>
                @endif
            </section>

            <section class="card p-6" aria-labelledby="logins-heading">
                <h2 id="logins-heading" class="font-bold text-slate-800">Aktivitas Masuk Terakhir</h2>
                <p class="mt-1 mb-3 text-sm text-slate-600">Tidak mengenali aktivitas? Segera ubah kata sandi Anda.</p>
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse ($logins as $login)
                        <li class="flex items-start justify-between gap-3 py-2">
                            <span>
                                <span @class(['font-bold', 'text-emerald-700' => $login['success'], 'text-rose-700' => ! $login['success']])>{{ $login['success'] ? 'Berhasil' : 'Gagal' }}</span>
                                <span class="block text-xs text-slate-500">{{ $login['agent'] }}</span>
                            </span>
                            <span class="text-right text-xs text-slate-600">{{ $login['at']->translatedFormat('d M Y H:i') }} WIB<span class="block">{{ $login['ip'] }}</span></span>
                        </li>
                    @empty
                        <li class="py-2 text-slate-500">Belum ada aktivitas tercatat.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</x-layouts.app>
