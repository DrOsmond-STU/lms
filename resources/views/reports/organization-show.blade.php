<x-layouts.app title="Laporan Organisasi" workspace="admin">
    <x-slot:back><a href="{{ route('admin.reports.organizations', ['dari' => $period['from']->toDateString(), 'sampai' => $period['to']->toDateString()]) }}" class="hero-back">&larr; Laporan per Organisasi</a></x-slot:back>
    <x-slot:heading>{{ $organization->name }}</x-slot:heading>
    <x-slot:subtitle>{{ $organization->code }} · {{ $organization->type }}{{ $organization->city ? ' · '.$organization->city : '' }} · periode {{ $period['from']->translatedFormat('d M Y') }} – {{ $period['to']->translatedFormat('d M Y') }}</x-slot:subtitle>

    <form method="GET" action="{{ route('admin.reports.organizations.show', $organization) }}" class="card mb-6 grid gap-3 p-4 sm:grid-cols-4">
        <div><label for="dari" class="form-label">Dari</label><input id="dari" name="dari" type="date" value="{{ $period['from']->toDateString() }}" class="form-input"></div>
        <div><label for="sampai" class="form-label">Sampai</label><input id="sampai" name="sampai" type="date" value="{{ $period['to']->toDateString() }}" class="form-input"></div>
        <div class="flex items-end"><button type="submit" class="btn-secondary w-auto">Terapkan</button></div>
    </form>

    @if ($summary)
        <div class="mb-6 grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
            @include('dashboards._tile', ['label' => 'Anggota aktif', 'value' => $summary['members'], 'icon' => 'users'])
            @include('dashboards._tile', ['label' => 'Enrollment', 'value' => $summary['enrollments'], 'icon' => 'book', 'note' => $summary['active'].' aktif'])
            @include('dashboards._tile', ['label' => 'Lulus', 'value' => $summary['passed'], 'icon' => 'graduation', 'note' => $summary['failed'].' tidak lulus'])
            @include('dashboards._tile', ['label' => 'Kelulusan', 'value' => $summary['pass_rate'] === null ? '—' : str_replace('.', ',', (string) $summary['pass_rate']).'%', 'icon' => 'chart'])
            @include('dashboards._tile', ['label' => 'Sertifikat', 'value' => $summary['certificates'], 'icon' => 'cert'])
            @include('dashboards._tile', ['label' => 'Pendapatan', 'value' => \App\Modules\Payment\Models\PaymentTransaction::rupiah((int) $summary['revenue']), 'icon' => 'card', 'note' => $summary['payments'].' pembayaran lunas'])
        </div>
    @endif

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th scope="col">Peserta</th><th scope="col">Program / Kelas</th><th scope="col">Status</th><th scope="col">Progres</th><th scope="col">Skor</th><th scope="col">Mendaftar</th></tr></thead>
            <tbody>
                @forelse ($enrollments as $enrollment)
                    <tr>
                        <td class="font-bold">{{ $enrollment->user->name }}<span class="block font-mono text-xs font-normal text-slate-500">{{ \App\Support\Privacy\Mask::email($enrollment->user->email) }}</span></td>
                        <td>{{ $enrollment->program->name }}<span class="block text-xs text-slate-500">{{ $enrollment->courseClass->batch_name }}</span></td>
                        <td><span class="badge chip-{{ \App\Modules\Enrollment\Models\Enrollment::STATUS_TONES[$enrollment->status] ?? 'neutral' }}">{{ $enrollment->statusLabel() }}</span></td>
                        <td>{{ $enrollment->progress_percent }}%</td>
                        <td>{{ $enrollment->final_score !== null ? fmt_score($enrollment->final_score) : '—' }}</td>
                        <td class="text-xs">{{ $enrollment->created_at->timezone(display_tz())->translatedFormat('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-500">Tidak ada enrollment pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $enrollments->links() }}</div>
</x-layouts.app>
