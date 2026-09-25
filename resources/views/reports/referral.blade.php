<x-layouts.app title="Laporan Referral" workspace="admin">
    <x-slot:heading>Laporan Referral</x-slot:heading>
    <x-slot:subtitle>Kinerja program referral per referrer untuk periode {{ $period['from']->translatedFormat('d M Y') }} – {{ $period['to']->translatedFormat('d M Y') }}. Komisi {{ (int) config('lms.referral_commission_percent') }}% dari pembayaran lunas{{ $enabled ? '' : ' · program sedang nonaktif' }}.</x-slot:subtitle>
    <x-slot:aside><div class="num">{{ \App\Modules\Payment\Models\PaymentTransaction::rupiah($totals['pending']) }}</div><div class="lbl">Komisi menunggu pencairan (periode)</div></x-slot:aside>

    @include('reports._period', ['action' => route('admin.reports.referral'), 'exportRoute' => route('admin.reports.referral.export'), 'placeholder' => 'Nama, email, atau kode referral'])

    <div class="mb-6 grid gap-4 sm:grid-cols-4">
        @include('dashboards._tile', ['label' => 'Referrer aktif', 'value' => $rows->count(), 'icon' => 'users'])
        @include('dashboards._tile', ['label' => 'Akun terdaftar', 'value' => $totals['registered'], 'icon' => 'user'])
        @include('dashboards._tile', ['label' => 'Transaksi berkomisi', 'value' => $totals['transactions'], 'icon' => 'card'])
        @include('dashboards._tile', ['label' => 'Komisi dibayar', 'value' => \App\Modules\Payment\Models\PaymentTransaction::rupiah($totals['paid']), 'icon' => 'check'])
    </div>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th scope="col">Referrer</th><th scope="col">Kode</th><th scope="col">Kunjungan</th><th scope="col">Terdaftar</th><th scope="col">Transaksi</th><th scope="col">Tertunda</th><th scope="col">Dibayar</th><th scope="col">Dibatalkan</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="font-bold">{{ $row['name'] }}<span class="block font-mono text-xs font-normal text-slate-500">{{ \App\Support\Privacy\Mask::email($row['email']) }}</span></td>
                        <td class="font-mono">{{ $row['code'] }}</td>
                        <td>{{ $row['visits'] }}</td>
                        <td>{{ $row['registered'] }}</td>
                        <td>{{ $row['transactions'] }}</td>
                        <td class="font-bold text-amber-700">{{ \App\Modules\Payment\Models\PaymentTransaction::rupiah((int) $row['pending']) }}@if ((int) $row['pending_all_time'] > (int) $row['pending'])<span class="block text-xs font-normal text-slate-500">{{ \App\Modules\Payment\Models\PaymentTransaction::rupiah((int) $row['pending_all_time']) }} seluruh waktu</span>@endif</td>
                        <td>{{ \App\Modules\Payment\Models\PaymentTransaction::rupiah((int) $row['paid']) }}</td>
                        <td class="text-slate-500">{{ \App\Modules\Payment\Models\PaymentTransaction::rupiah((int) $row['void']) }}</td>
                        <td class="text-right"><a href="{{ route('admin.reports.referral.show', $row['id']) }}" class="font-bold text-link hover:underline">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-8 text-center text-slate-500">Belum ada referrer (kode dibuat saat peserta membuka halaman Referral Saya).</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
