<x-layouts.app title="Detail Referrer" workspace="admin">
    <x-slot:back><a href="{{ route('admin.reports.referral') }}" class="hero-back">&larr; Laporan Referral</a></x-slot:back>
    <x-slot:heading>{{ $referrer->name }}</x-slot:heading>
    <x-slot:subtitle><span class="font-mono">{{ \App\Support\Privacy\Mask::email($referrer->email) }}</span> · kode <span class="font-mono">{{ $profile->code }}</span> · {{ $stats['registered'] }} akun terdaftar · {{ $stats['visits'] }} kunjungan</x-slot:subtitle>
    <x-slot:aside><div class="num">{{ \App\Modules\Payment\Models\PaymentTransaction::rupiah($stats['pending']) }}</div><div class="lbl">Komisi tertunda</div></x-slot:aside>

    <x-form-error field="reference" />
    <x-form-error field="reason" />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card overflow-x-auto">
                <h2 class="px-5 pt-5 font-bold text-slate-800">Komisi</h2>
                <table class="data-table mt-3">
                    <thead><tr><th scope="col">Tanggal</th><th scope="col">Peserta</th><th scope="col">Transaksi</th><th scope="col">Komisi</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
                    <tbody>
                        @forelse ($commissions as $commission)
                            <tr>
                                <td class="text-xs">{{ $commission->created_at->timezone(display_tz())->translatedFormat('d M Y') }}</td>
                                <td>{{ $commission->referredUser->name }}</td>
                                <td>{{ $commission->transaction->program->name }}<span class="block font-mono text-xs text-slate-500">{{ $commission->transaction->invoice_number ?? $commission->transaction->order_id }}</span></td>
                                <td class="font-bold">{{ \App\Modules\Payment\Models\PaymentTransaction::rupiah($commission->amount) }}<span class="block text-xs font-normal text-slate-500">{{ $commission->rate_percent }}% × {{ \App\Modules\Payment\Models\PaymentTransaction::rupiah($commission->base_amount) }}</span></td>
                                <td><span class="badge chip-{{ $commission->statusTone() }}">{{ $commission->statusLabel() }}</span>@if ($commission->payout)<span class="block text-xs text-slate-500">{{ $commission->payout->reference }}</span>@endif @if ($commission->void_reason)<span class="block text-xs text-slate-500">{{ $commission->void_reason }}</span>@endif</td>
                                <td class="text-right">
                                    @if ($commission->status === 'pending')
                                        @can('referral.pay')
                                            <form method="POST" action="{{ route('admin.reports.referral.void', $commission) }}" class="flex items-center justify-end gap-1" data-confirm="Batalkan komisi ini?">
                                                @csrf<input name="reason" required minlength="5" maxlength="500" placeholder="Alasan" class="form-input w-36 py-1 text-xs"><button type="submit" class="btn-mini-danger">Batalkan</button>
                                            </form>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-8 text-center text-slate-500">Belum ada komisi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            <section class="card overflow-x-auto">
                <h2 class="px-5 pt-5 font-bold text-slate-800">Riwayat pencairan</h2>
                <table class="data-table mt-3">
                    <thead><tr><th scope="col">Tanggal</th><th scope="col">Jumlah</th><th scope="col">Komisi</th><th scope="col">Referensi</th><th scope="col">Oleh</th></tr></thead>
                    <tbody>
                        @forelse ($payouts as $payout)
                            <tr><td class="text-xs">{{ $payout->paid_at->timezone(display_tz())->translatedFormat('d M Y H:i') }}</td><td class="font-bold">{{ \App\Modules\Payment\Models\PaymentTransaction::rupiah($payout->amount) }}</td><td>{{ $payout->commission_count }}</td><td class="font-mono text-xs">{{ $payout->reference }}@if ($payout->note)<span class="block font-sans text-slate-500">{{ $payout->note }}</span>@endif</td><td>{{ $payout->payer->name }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-slate-500">Belum ada pencairan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="card p-5 text-sm">
                <h2 class="font-bold text-slate-800">Rekening pencairan</h2>
                @if ($profile->hasPayoutAccount())
                    <p class="mt-2">{{ $profile->bank_name }}<span class="block font-mono">{{ $profile->bankAccount() }}</span><span class="block text-slate-600">a.n. {{ $profile->bank_account_name }}</span></p>
                @else
                    <p class="mt-2 text-amber-700">Referrer belum mengisi rekening pencairan.</p>
                @endif
            </section>
            @can('referral.pay')
                <section class="card p-5">
                    <h2 class="font-bold text-slate-800">Catat pencairan</h2>
                    <p class="mt-1 text-xs text-slate-500">Semua komisi tertunda ({{ \App\Modules\Payment\Models\PaymentTransaction::rupiah($stats['pending']) }}) ditandai dibayar dalam satu batch. Transfer dilakukan di luar sistem; catat referensinya. Memerlukan konfirmasi identitas.</p>
                    <form method="POST" action="{{ route('admin.reports.referral.payout', $referrer) }}" class="mt-3 space-y-3" data-confirm="Catat pencairan {{ \App\Modules\Payment\Models\PaymentTransaction::rupiah($stats['pending']) }} kepada {{ $referrer->name }}?">
                        @csrf
                        <div><label for="reference" class="form-label">Referensi transfer</label><input id="reference" name="reference" required minlength="3" maxlength="120" class="form-input" placeholder="Nomor mutasi / bukti"></div>
                        <div><label for="note" class="form-label">Catatan (opsional)</label><input id="note" name="note" maxlength="300" class="form-input"></div>
                        <button type="submit" class="btn-primary w-full" @disabled($stats['pending'] <= 0 || ! $profile->hasPayoutAccount())>Tandai Dibayar</button>
                    </form>
                </section>
            @endcan
        </aside>
    </div>
</x-layouts.app>
