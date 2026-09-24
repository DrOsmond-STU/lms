@php($max = max(1, collect($enrollmentsPerMonth)->max('total')))
<x-layouts.app title="Dashboard Administrator" workspace="admin">
    <h1 class="text-xl font-extrabold text-slate-800">Dashboard Administrator</h1>
    <p class="mt-0.5 mb-6 text-sm text-slate-600">Selamat datang, {{ $user->name }}.</p>
    @if (($approvalQueue ?? 0) > 0 || $secondApprovals > 0 || ($programsInReview ?? 0) > 0)
        <div class="mb-6 flex flex-wrap gap-3">
            @if (($approvalQueue ?? 0) > 0)<a href="{{ route('admin.approvals.index') }}" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-bold text-amber-800">{{ $approvalQueue }} sertifikat menunggu approval</a>@endif
            @if ($secondApprovals > 0)<a href="{{ route('admin.second-approvals.index') }}" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-bold text-amber-800">{{ $secondApprovals }} persetujuan kedua</a>@endif
            @if (($programsInReview ?? 0) > 0)<a href="{{ route('admin.programs.index', ['status' => 'in_review']) }}" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-bold text-amber-800">{{ $programsInReview }} program menunggu review</a>@endif
        </div>
    @endif
    <div class="mb-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        @include('dashboards._stat', ['label' => 'Peserta', 'value' => number_format($participants, 0, ',', '.')])
        @include('dashboards._stat', ['label' => 'Organisasi Aktif', 'value' => $organizations])
        @include('dashboards._stat', ['label' => 'Kelas Berjalan/Dibuka', 'value' => $classes])
        @include('dashboards._stat', ['label' => 'Sertifikat Aktif', 'value' => number_format($certificates, 0, ',', '.')])
    </div>
    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card p-6 lg:col-span-2" aria-labelledby="trend-heading">
            <h2 id="trend-heading" class="font-bold text-slate-800">Pendaftaran per Bulan</h2>
            <table class="mt-4 w-full text-sm">
                <caption class="sr-only">Jumlah enrollment baru enam bulan terakhir</caption>
                <tbody>
                    @foreach ($enrollmentsPerMonth as $row)
                        <tr>
                            <th scope="row" class="w-24 py-1.5 pr-3 text-left text-xs font-bold text-slate-500">{{ $row['label'] }}</th>
                            <td class="py-1.5"><div class="h-3 rounded bg-brand-500 progress-{{ (int) (round($row['total'] * 20 / $max) * 5) }}"></div></td>
                            <td class="w-12 py-1.5 text-right text-xs font-bold">{{ $row['total'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
        <section class="card p-6">
            <h2 class="font-bold text-slate-800">Tingkat Kelulusan</h2>
            <p class="mt-3 text-3xl font-extrabold text-slate-800">{{ $passRate === null ? '—' : $passRate.'%' }}</p>
            <p class="mt-1 text-xs text-slate-500">Lulus dibanding lulus + tidak lulus. Program terbit: {{ $programs }}.</p>
            <p class="mt-4 text-xs text-slate-500">Laporan keuangan tersedia setelah modul pembayaran (Fase 2).</p>
        </section>
    </div>
</x-layouts.app>
