<x-layouts.app title="Laporan per Organisasi" workspace="admin">
    <x-slot:heading>Laporan per Organisasi</x-slot:heading>
    <x-slot:subtitle>Rekap anggota, enrollment, kelulusan, sertifikat, dan pembayaran lunas per organisasi untuk periode {{ $period['from']->translatedFormat('d M Y') }} – {{ $period['to']->translatedFormat('d M Y') }}.</x-slot:subtitle>

    @include('reports._period', ['action' => route('admin.reports.organizations'), 'exportRoute' => route('admin.reports.organizations.export'), 'placeholder' => 'Nama atau kode organisasi'])

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th scope="col">Organisasi</th><th scope="col">Anggota</th><th scope="col">Enrollment</th><th scope="col">Aktif</th><th scope="col">Lulus</th><th scope="col">Tidak Lulus</th><th scope="col">Batal</th><th scope="col">Kelulusan</th><th scope="col">Sertifikat</th><th scope="col">Pembayaran</th><th scope="col">Pendapatan</th></tr></thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="font-bold"><a href="{{ route('admin.reports.organizations.show', ['organization' => $row['id'], 'dari' => $period['from']->toDateString(), 'sampai' => $period['to']->toDateString()]) }}" class="text-link hover:underline">{{ $row['name'] }}</a><span class="block font-mono text-xs font-normal text-slate-500">{{ $row['code'] }} · {{ $row['type'] }}{{ $row['status'] !== 'active' ? ' · nonaktif' : '' }}</span></td>
                        <td>{{ $row['members'] }}</td>
                        <td>{{ $row['enrollments'] }}</td>
                        <td>{{ $row['active'] }}</td>
                        <td>{{ $row['passed'] }}</td>
                        <td>{{ $row['failed'] }}</td>
                        <td>{{ $row['cancelled'] }}</td>
                        <td>{{ $row['pass_rate'] === null ? '—' : str_replace('.', ',', (string) $row['pass_rate']).'%' }}</td>
                        <td>{{ $row['certificates'] }}</td>
                        <td>{{ $row['payments'] }}</td>
                        <td class="font-bold">{{ \App\Modules\Payment\Models\PaymentTransaction::rupiah((int) $row['revenue']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="py-8 text-center text-slate-500">Tidak ada organisasi yang cocok.</td></tr>
                @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot><tr class="font-bold"><td>Total ({{ $rows->count() }} organisasi)</td><td>{{ $rows->sum('members') }}</td><td>{{ $rows->sum('enrollments') }}</td><td>{{ $rows->sum('active') }}</td><td>{{ $rows->sum('passed') }}</td><td>{{ $rows->sum('failed') }}</td><td>{{ $rows->sum('cancelled') }}</td><td>—</td><td>{{ $rows->sum('certificates') }}</td><td>{{ $rows->sum('payments') }}</td><td>{{ \App\Modules\Payment\Models\PaymentTransaction::rupiah((int) $rows->sum('revenue')) }}</td></tr></tfoot>
            @endif
        </table>
    </div>
    <p class="mt-3 text-xs text-slate-500">Enrollment dihitung menurut tanggal pendaftaran; sertifikat menurut tanggal terbit; pembayaran menurut tanggal lunas. Peserta tanpa organisasi tidak termasuk.</p>
</x-layouts.app>
