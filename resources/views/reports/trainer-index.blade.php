<x-layouts.app title="Laporan Kelas" workspace="trainer">
    <x-slot:heading>Laporan Kelas</x-slot:heading>
    <x-slot:subtitle>Ringkasan kelas yang Anda ampu. Buka laporan untuk analitik lengkap dan ekspor CSV/Excel/PDF.</x-slot:subtitle>
    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th scope="col">Kelas</th><th scope="col">Status</th><th scope="col">Peserta</th><th scope="col">Lulus</th><th scope="col">Penyelesaian</th><th scope="col">Rata-rata Progres</th><th scope="col">Rata-rata Skor</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
            <tbody>
                @forelse ($classes as $class)
                    @php($s = $summaries->get($class->id))
                    <tr>
                        <td><a href="{{ route('classes.report', $class) }}" class="font-bold text-link hover:underline">{{ $class->batch_name }}</a><span class="block text-xs text-slate-500">{{ $class->program->name }} · {{ $class->starts_on->translatedFormat('d M Y') }} – {{ $class->ends_on->translatedFormat('d M Y') }}</span></td>
                        <td><span class="badge bg-slate-100 text-slate-700">{{ \App\Modules\Learning\Models\CourseClass::STATUSES[$class->status] }}</span></td>
                        <td class="font-mono">{{ $s?->total ?? 0 }}</td>
                        <td class="font-mono">{{ $s?->passed ?? 0 }}</td>
                        <td class="font-mono">{{ $s && $s->total > 0 ? round($s->passed * 100 / $s->total).'%' : '—' }}</td>
                        <td class="font-mono">{{ $s && $s->avg_progress !== null ? round((float) $s->avg_progress).'%' : '—' }}</td>
                        <td class="font-mono">{{ $s && $s->avg_score !== null ? fmt_score($s->avg_score) : '—' }}</td>
                        <td class="text-right"><a href="{{ route('classes.report', $class) }}" class="btn-mini">Laporan</a> <a href="{{ route('classes.gradebook', $class) }}" class="btn-mini">Nilai</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-8 text-center text-slate-500">Belum ada kelas yang Anda ampu.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
