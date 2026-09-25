<x-layouts.app title="Pembayaran" workspace="admin">
    <x-slot:heading>Pembayaran</x-slot:heading>
    <x-slot:subtitle>Tagihan transfer manual. Konfirmasi lunas di atas {{ \App\Modules\Payment\Models\PaymentTransaction::rupiah($threshold) }} memerlukan persetujuan admin kedua.</x-slot:subtitle>
    <x-slot:aside><div class="num">{{ $reviewCount }}</div><div class="lbl">Bukti menunggu verifikasi</div></x-slot:aside>

    <form method="GET" action="{{ route('admin.payments.index') }}" class="card mb-6 grid gap-3 p-4 sm:grid-cols-4" role="search">
        <div class="sm:col-span-2"><label for="q" class="form-label">Cari</label><input id="q" name="q" type="search" value="{{ $search }}" placeholder="Nama, email, nomor tagihan/invoice" class="form-input"></div>
        <div>
            <label for="status" class="form-label">Status</label>
            <select id="status" name="status" class="form-input">
                <option value="">Semua</option>
                @foreach (\App\Modules\Payment\Models\PaymentTransaction::STATUSES as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <label class="flex items-center gap-2 text-sm font-semibold text-slate-800"><input type="checkbox" name="tinjau" value="1" @checked($review)> Perlu verifikasi</label>
            <button type="submit" class="btn-secondary w-auto">Terapkan</button>
        </div>
    </form>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th scope="col">Tagihan</th><th scope="col">Peserta</th><th scope="col">Program</th><th scope="col">Nilai</th><th scope="col">Status</th><th scope="col">Batas / Lunas</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
            <tbody>
                @forelse ($transactions as $transaction)
                    <tr @class(['bg-amber-50/40' => $transaction->needs_review && $transaction->isPending()])>
                        <td class="font-mono text-xs">{{ $transaction->order_id }}@if ($transaction->invoice_number)<span class="block text-slate-500">{{ $transaction->invoice_number }}</span>@endif</td>
                        <td class="font-bold">{{ $transaction->user->name }}<span class="block font-mono text-xs font-normal text-slate-500">{{ \App\Support\Privacy\Mask::email($transaction->user->email) }}</span></td>
                        <td>{{ $transaction->program->name }}<span class="block text-xs text-slate-500">{{ $transaction->courseClass->batch_name }}</span></td>
                        <td class="font-bold">{{ $transaction->amountLabel() }}</td>
                        <td><span class="badge chip-{{ $transaction->statusTone() }}">{{ $transaction->statusLabel() }}</span>@if ($transaction->needs_review && $transaction->isPending())<span class="block text-xs font-bold text-amber-700">perlu verifikasi</span>@endif</td>
                        <td class="text-xs">{{ ($transaction->settled_at ?? $transaction->expires_at)->timezone(display_tz())->translatedFormat('d M Y H:i') }}</td>
                        <td class="text-right"><a href="{{ route('admin.payments.show', $transaction) }}" class="font-bold text-link hover:underline">Tinjau</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-slate-500">Tidak ada tagihan yang cocok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $transactions->links() }}</div>
</x-layouts.app>
