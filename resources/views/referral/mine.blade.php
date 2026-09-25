<x-layouts.app title="Referral Saya" workspace="participant">
    <x-slot:heading>Referral Saya</x-slot:heading>
    <x-slot:subtitle>Bagikan kode Anda; dapatkan komisi {{ $percent }}% dari setiap pembayaran pelatihan yang dikonfirmasi lunas.</x-slot:subtitle>
    <x-slot:aside><div class="num">{{ \App\Modules\Payment\Models\PaymentTransaction::rupiah($stats['pending']) }}</div><div class="lbl">Komisi menunggu pencairan</div></x-slot:aside>

    @unless ($enabled)
        <p class="mb-6 rounded-lg bg-amber-50 p-4 text-sm text-amber-800">Program referral sedang tidak aktif. Kode Anda tetap tersimpan dan berlaku kembali saat program dibuka.</p>
    @endunless

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card p-6">
                <h2 class="font-bold text-slate-800">Kode &amp; tautan Anda</h2>
                <div class="mt-3 grid gap-4 sm:grid-cols-[auto_1fr] sm:items-center">
                    <span class="rounded-lg bg-brand-50 px-4 py-2 font-mono text-2xl font-extrabold tracking-widest text-brand-700">{{ $profile->code }}</span>
                    <div>
                        <label for="referral-link" class="form-label">Tautan pendaftaran</label>
                        <input id="referral-link" value="{{ $link }}" readonly class="form-input font-mono text-xs" data-select-on-focus>
                        <p class="mt-1 text-xs text-slate-500">Teman yang mendaftar lewat tautan ini (atau memasukkan kode Anda saat mendaftar) tercatat sebagai referral Anda. Tautan halaman program mana pun dengan <span class="font-mono">?ref={{ $profile->code }}</span> juga berlaku.</p>
                    </div>
                </div>
            </section>

            <div class="grid gap-4 sm:grid-cols-3">
                @include('dashboards._tile', ['label' => 'Kunjungan tautan', 'value' => $stats['visits'], 'icon' => 'globe', 'note' => 'Klik pada tautan referral'])
                @include('dashboards._tile', ['label' => 'Akun terdaftar', 'value' => $stats['registered'], 'icon' => 'users', 'note' => $stats['paid_users'].' sudah membayar'])
                @include('dashboards._tile', ['label' => 'Komisi dibayar', 'value' => \App\Modules\Payment\Models\PaymentTransaction::rupiah($stats['paid']), 'icon' => 'card', 'note' => 'Total yang telah dicairkan'])
            </div>

            <section class="card overflow-x-auto">
                <h2 class="px-5 pt-5 font-bold text-slate-800">Komisi</h2>
                <table class="data-table mt-3">
                    <thead><tr><th scope="col">Tanggal</th><th scope="col">Dari</th><th scope="col">Program</th><th scope="col">Komisi</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                        @forelse ($commissions as $commission)
                            <tr>
                                <td class="text-xs">{{ $commission->created_at->timezone(display_tz())->translatedFormat('d M Y') }}</td>
                                <td>{{ $commission->referredUser->name }}</td>
                                <td>{{ $programNames[$commission->payment_transaction_id] ?? '—' }}</td>
                                <td class="font-bold">{{ \App\Modules\Payment\Models\PaymentTransaction::rupiah($commission->amount) }}<span class="block text-xs font-normal text-slate-500">{{ $commission->rate_percent }}% dari {{ \App\Modules\Payment\Models\PaymentTransaction::rupiah($commission->base_amount) }}</span></td>
                                <td><span class="badge chip-{{ $commission->statusTone() }}">{{ $commission->statusLabel() }}</span>@if ($commission->payout)<span class="block text-xs text-slate-500">{{ $commission->payout->paid_at->timezone(display_tz())->translatedFormat('d M Y') }} · {{ $commission->payout->reference }}</span>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-slate-500">Belum ada komisi. Komisi muncul saat pembayaran pelatihan dari referral Anda dikonfirmasi lunas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            <section class="card overflow-x-auto">
                <h2 class="px-5 pt-5 font-bold text-slate-800">Akun yang mendaftar dengan kode Anda</h2>
                <table class="data-table mt-3">
                    <thead><tr><th scope="col">Nama</th><th scope="col">Mendaftar</th><th scope="col">Status akun</th></tr></thead>
                    <tbody>
                        @forelse ($referred as $person)
                            <tr><td class="font-bold">{{ $person->name }}</td><td class="text-xs">{{ $person->referred_at?->timezone(display_tz())->translatedFormat('d M Y') }}</td><td class="text-xs">{{ $person->status === 'active' ? 'Aktif' : 'Belum verifikasi' }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="py-6 text-center text-slate-500">Belum ada yang mendaftar lewat referral Anda.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="card p-5">
                <h2 class="font-bold text-slate-800">Rekening pencairan</h2>
                @if ($profile->hasPayoutAccount())
                    <p class="mt-2 text-sm text-slate-600">{{ $profile->bank_name }} · <span class="font-mono">{{ $profile->bankAccountMasked() }}</span> a.n. {{ $profile->bank_account_name }}</p>
                @else
                    <p class="mt-2 text-sm text-amber-700">Isi rekening agar komisi dapat dicairkan.</p>
                @endif
                <form method="POST" action="{{ route('referral.account') }}" class="mt-4 space-y-3">
                    @csrf
                    <div><label for="bank_name" class="form-label">Bank</label><input id="bank_name" name="bank_name" value="{{ old('bank_name', $profile->bank_name) }}" maxlength="60" required class="form-input"><x-form-error field="bank_name" /></div>
                    <div><label for="bank_account" class="form-label">Nomor rekening</label><input id="bank_account" name="bank_account" value="{{ old('bank_account') }}" maxlength="30" required inputmode="numeric" autocomplete="off" class="form-input font-mono"><x-form-error field="bank_account" /></div>
                    <div><label for="bank_account_name" class="form-label">Atas nama</label><input id="bank_account_name" name="bank_account_name" value="{{ old('bank_account_name', $profile->bank_account_name) }}" maxlength="100" required class="form-input"><x-form-error field="bank_account_name" /></div>
                    <button type="submit" class="btn-secondary w-full">Simpan Rekening</button>
                </form>
            </section>
            <section class="card p-5 text-sm">
                <h2 class="font-bold text-slate-800">Ketentuan</h2>
                <p class="mt-2 whitespace-pre-line text-slate-600">{{ $terms }}</p>
            </section>
        </aside>
    </div>
</x-layouts.app>
