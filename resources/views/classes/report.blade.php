<x-layouts.app :title="'Laporan '.$class->batch_name" :workspace="$workspace">
    <x-slot:back><a href="{{ $workspace === 'admin' ? route('admin.programs.show', $class->program) : route('trainer.reports') }}" class="hero-back">&larr; {{ $workspace === 'admin' ? $class->program->name : 'Laporan Kelas' }}</a></x-slot:back>
    <x-slot:heading>{{ $class->program->name }} — {{ $class->batch_name }}</x-slot:heading>
    <x-slot:meta><span class="badge bg-slate-100 text-slate-700">{{ \App\Modules\Learning\Models\CourseClass::STATUSES[$class->status] ?? $class->status }}</span></x-slot:meta>
    <x-slot:subtitle>Analitik kelas: penyelesaian, rata-rata nilai, aktivitas belajar, peserta tidak aktif, hasil kuis, durasi belajar, dan presensi.</x-slot:subtitle>
    @include('classes._header')

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @include('dashboards._tile', ['label' => 'Peserta', 'value' => $total, 'icon' => 'users', 'note' => $active.' aktif · '.$passed.' lulus · '.$failed.' tidak lulus'])
        @include('dashboards._tile', ['label' => 'Tingkat penyelesaian', 'value' => $completionRate === null ? '—' : str_replace('.', ',', (string) $completionRate).'%', 'icon' => 'graduation', 'note' => 'Lulus dari seluruh peserta', 'trend' => $pendingApproval > 0 ? $pendingApproval.' menunggu approval sertifikat' : null])
        @include('dashboards._tile', ['label' => 'Rata-rata skor akhir', 'value' => $avgScore === null ? '—' : fmt_score($avgScore), 'icon' => 'chart', 'note' => 'Rata-rata progres '.($avgProgress === null ? '—' : str_replace('.', ',', (string) $avgProgress).'%')])
        @include('dashboards._tile', ['label' => 'Aktif 7 hari terakhir', 'value' => $activeLast7, 'icon' => 'play', 'note' => $inactive->count().' peserta tidak aktif', 'tone' => $inactive->count() > 0 ? 'bad' : 'good', 'trend' => 'Rata-rata belajar '.\App\Modules\Reporting\Services\ClassReportService::duration($avgSeconds).'/peserta'])
    </div>

    <section class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="card p-5 xl:col-span-2">
            <h2 class="card-title">Aktivitas Mingguan</h2>
            <p class="card-sub">Peserta yang membuka materi per minggu (8 minggu terakhir)</p>
            <x-line-chart :series="$weekly" label="Grafik peserta aktif per minggu" />
            <table class="sr-only"><caption>Peserta aktif per minggu</caption><tbody>@foreach ($weekly as $row)<tr><th scope="row">{{ $row['label'] }}</th><td>{{ $row['value'] }}</td></tr>@endforeach</tbody></table>
        </div>
        <div class="card p-5">
            <h2 class="card-title">Peserta Tidak Aktif</h2>
            <p class="card-sub">Belum ada aktivitas &ge; {{ $inactiveDays }} hari</p>
            <ul class="space-y-2 text-sm">
                @forelse ($inactive->take(10) as $row)
                    <li class="flex items-center justify-between gap-2"><span class="font-semibold text-slate-800">{{ $row['enrollment']->user->name }}</span><span class="text-xs text-slate-500">{{ $row['last_at']?->timezone(display_tz())->translatedFormat('d M') ?? 'belum mulai' }} · {{ $row['enrollment']->progress_percent }}%</span></li>
                @empty
                    <li class="text-slate-500">Semua peserta aktif.</li>
                @endforelse
                @if ($inactive->count() > 10)<li class="text-xs text-slate-500">+{{ $inactive->count() - 10 }} lainnya (lihat tabel)</li>@endif
            </ul>
            @if ($sessions > 0)<p class="mt-4 text-xs text-slate-500">Presensi: {{ $attendanceRate === null ? '—' : str_replace('.', ',', (string) $attendanceRate).'%' }} dari {{ $sessions }} sesi</p>@endif
        </div>
    </section>

    <section class="card mt-6 overflow-x-auto">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="card-title">Hasil Kuis &amp; Ujian</h2><p class="card-sub">Per asesmen: peserta yang mengerjakan, rata-rata, rentang, dan yang mencapai KKM{{ $gain !== null ? ' · kenaikan pre→post-test '.($gain >= 0 ? '+' : '').str_replace('.', ',', (string) $gain).' poin' : '' }}</p></div>
        <table class="data-table">
            <thead><tr><th scope="col">Asesmen</th><th scope="col">Jenis</th><th scope="col">KKM</th><th scope="col">Peserta</th><th scope="col">Attempt</th><th scope="col">Rata-rata</th><th scope="col">Min–Maks</th><th scope="col">Lolos KKM</th></tr></thead>
            <tbody>
                @forelse ($quiz as $row)
                    @php($s = $row['stats'])
                    <tr>
                        <td class="font-bold">{{ $row['assessment']->title }}</td>
                        <td class="text-xs">{{ \App\Modules\Assessment\Models\Assessment::KINDS[$row['assessment']->kind] }}</td>
                        <td class="font-mono">{{ fmt_score($row['assessment']->passing_score) }}</td>
                        <td class="font-mono">{{ $s?->participants ?? 0 }}/{{ $total }}</td>
                        <td class="font-mono">{{ $s?->attempts ?? 0 }}</td>
                        <td class="font-mono font-bold">{{ $s ? fmt_score($s->avg_score) : '—' }}</td>
                        <td class="font-mono text-xs">{{ $s ? fmt_score($s->min_score).' – '.fmt_score($s->max_score) : '—' }}</td>
                        <td class="font-mono">{{ $s ? $s->passed.' ('.($s->participants > 0 ? round($s->passed * 100 / $s->participants) : 0).'%)' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-6 text-center text-slate-500">Belum ada asesmen.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="card mt-6 overflow-x-auto">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-5 py-4">
            <div><h2 class="card-title">Aktivitas per Peserta</h2><p class="card-sub">Durasi belajar dihitung dari waktu membuka materi (video/audio/teks) dan mengerjakan asesmen</p></div>
            @if ($canExport)<div class="flex gap-2">@foreach (\App\Support\Export\TableExport::FORMATS as $format => $label)<a href="{{ route('classes.report.export', [$class, 'format' => $format]) }}" class="btn-secondary">{{ $label }}</a>@endforeach</div>@endif
        </div>
        <table class="data-table">
            <thead><tr><th scope="col">Peserta</th><th scope="col">Status</th><th scope="col">Progres</th><th scope="col">Skor Akhir</th><th scope="col">Aktivitas Terakhir</th><th scope="col">Durasi Belajar</th></tr></thead>
            <tbody>
                @forelse ($rows as $row)
                    @php($e = $row['enrollment'])
                    <tr @class(['bg-rose-50/40' => $row['inactive']])>
                        <td class="font-bold">{{ $e->user->name }}@if ($e->group)<span class="block text-xs font-normal text-slate-500">{{ $e->group->name }}</span>@endif</td>
                        <td><span class="chip chip-{{ \App\Modules\Enrollment\Models\Enrollment::STATUS_TONES[$e->status] ?? 'neutral' }} px-1.5 py-0.5 text-[10px]">{{ $e->statusLabel() }}</span></td>
                        <td class="font-mono">{{ $e->progress_percent }}%</td>
                        <td class="font-mono">{{ $e->final_score !== null ? fmt_score($e->final_score) : '—' }}</td>
                        <td class="text-xs">{{ $row['last_at']?->timezone(display_tz())->translatedFormat('d M Y H:i') ?? '—' }}@if ($row['inactive'])<span class="block font-bold text-rose-700">tidak aktif</span>@endif</td>
                        <td class="font-mono text-xs">{{ \App\Modules\Reporting\Services\ClassReportService::duration($row['seconds']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-slate-500">Belum ada peserta.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</x-layouts.app>
