<x-layouts.app :title="'Laporan Konten '.$class->batch_name" :workspace="$member['workspace']">
    <x-slot:back><a href="{{ route('classes.manage', $class) }}" class="hero-back">&larr; Kelola {{ $class->batch_name }}</a></x-slot:back>
    <x-slot:heading>Laporan Konten — {{ $class->batch_name }}</x-slot:heading>
    <x-slot:subtitle>Konten yang dilaporkan peserta. Buka utasnya untuk menyembunyikan atau mengunci.</x-slot:subtitle>
    @include('discussion._nav', ['tab' => 'reports'])
    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th scope="col">Waktu</th><th scope="col">Pelapor</th><th scope="col">Utas</th><th scope="col">Alasan</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
            <tbody>
                @forelse ($reports as $report)
                    <tr>
                        <td class="text-xs whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($report->created_at)->timezone(display_tz())->format('d M H:i') }}</td>
                        <td>{{ $report->reporter_name }}</td>
                        <td><a href="{{ route('discussion.show', [$class, $report->thread_id]) }}" class="font-bold text-link hover:underline">{{ $report->thread_title ?? 'Komentar materi' }}</a>{{ $report->post_id ? ' (balasan)' : '' }}</td>
                        <td class="text-sm">{{ $report->reason }}</td>
                        <td><span @class(['badge', 'bg-amber-50 text-amber-800' => $report->status === 'open', 'bg-emerald-50 text-emerald-700' => $report->status !== 'open'])>{{ $report->status === 'open' ? 'Terbuka' : 'Selesai' }}</span></td>
                        <td class="text-right">@if ($report->status === 'open')<form method="POST" action="{{ route('discussion.reports.resolve', [$class, $report->id]) }}">@csrf<button class="btn-mini">Tandai selesai</button></form>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-500">Tidak ada laporan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
