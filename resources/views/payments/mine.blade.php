<x-layouts.app title="Transaksi Saya" workspace="participant">
    <x-slot:heading>Transaksi Saya</x-slot:heading>
    <x-slot:subtitle>Tagihan pelatihan berbayar, status verifikasi, dan invoice.</x-slot:subtitle>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th scope="col">Tagihan</th><th scope="col">Program</th><th scope="col">Nilai</th><th scope="col">Status</th><th scope="col">Batas / Lunas</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
            <tbody>
                @forelse ($transactions as $transaction)
                    <tr>
                        <td class="font-mono text-xs">{{ $transaction->order_id }}@if ($transaction->invoice_number)<span class="block text-slate-500">{{ $transaction->invoice_number }}</span>@endif</td>
                        <td>{{ $transaction->program->name }}<span class="block text-xs text-slate-500">{{ $transaction->courseClass->batch_name }}</span></td>
                        <td class="font-bold">{{ $transaction->amountLabel() }}</td>
                        <td><span class="badge chip-{{ $transaction->statusTone() }}">{{ $transaction->statusLabel() }}</span>@if ($transaction->isPending() && $transaction->needs_review)<span class="block text-xs text-slate-500">bukti sedang diverifikasi</span>@endif</td>
                        <td class="text-xs">{{ ($transaction->settled_at ?? $transaction->expires_at)->timezone(display_tz())->translatedFormat('d M Y H:i') }} {{ tz_label() }}</td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('payments.show', $transaction) }}" class="font-bold text-link hover:underline">Detail</a>
                            @if ($transaction->isSettled())· <a href="{{ route('payments.invoice', $transaction) }}" class="font-bold text-link hover:underline">Invoice</a>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-500">Belum ada transaksi. Program berbayar akan muncul di sini setelah Anda memilih “Daftar &amp; Bayar”.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $transactions->links() }}</div>
</x-layouts.app>
