<x-layouts.app :title="'Nilai '.$class->batch_name" :workspace="$workspace">
    <x-slot:back><a href="{{ $workspace === 'admin' ? route('admin.programs.show', $class->program) : route('trainer.classes') }}" class="hero-back">&larr; {{ $workspace === 'admin' ? $class->program->name : 'Kelas Saya' }}</a></x-slot:back>
    <x-slot:heading>{{ $class->program->name }} — {{ $class->batch_name }}</x-slot:heading>
    <x-slot:meta><span class="badge bg-slate-100 text-slate-700">{{ \App\Modules\Learning\Models\CourseClass::STATUSES[$class->status] ?? $class->status }}</span></x-slot:meta>
    <x-slot:subtitle>Buku nilai: skor terbaik tiap asesmen, nilai tugas, progres materi, dan skor akhir. {{ $rows->count() }} peserta.</x-slot:subtitle>
    @include('classes._header')

    <div class="card overflow-x-auto">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-5 py-4">
            <h2 class="card-title">Buku Nilai</h2>
            @if ($canExport)<div class="flex gap-2">@foreach (\App\Support\Export\TableExport::FORMATS as $format => $label)<a href="{{ route('classes.gradebook.export', [$class, 'format' => $format]) }}" class="btn-secondary">{{ $label }}</a>@endforeach</div>@endif
        </div>
        <table class="data-table text-xs">
            <thead>
                <tr>
                    <th scope="col">Peserta</th><th scope="col">Kelompok</th><th scope="col">Progres</th>
                    @foreach ($assessments as $assessment)<th scope="col" class="whitespace-nowrap" title="{{ \App\Modules\Assessment\Models\Assessment::KINDS[$assessment->kind] }}">{{ \Illuminate\Support\Str::limit($assessment->title, 24) }}<span class="block font-normal text-slate-500">{{ \App\Modules\Assessment\Models\Assessment::KINDS[$assessment->kind] }} · KKM {{ fmt_score($assessment->passing_score) }}</span></th>@endforeach
                    @foreach ($assignments as $assignment)<th scope="col" class="whitespace-nowrap">{{ \Illuminate\Support\Str::limit($assignment->title, 24) }}<span class="block font-normal text-slate-500">Tugas · maks {{ fmt_score($assignment->max_score) }}</span></th>@endforeach
                    <th scope="col">Skor Akhir</th><th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php($enrollment = $row['enrollment'])
                    <tr>
                        <td class="font-bold whitespace-nowrap">{{ $enrollment->user->name }}</td>
                        <td>{{ $enrollment->group?->name ?? '—' }}</td>
                        <td class="font-mono">{{ $enrollment->progress_percent }}%</td>
                        @foreach ($assessments as $assessment)
                            @php($score = $row['scores'][$assessment->id])
                            <td class="font-mono"><span @class(['text-emerald-700 font-bold' => $score !== null && $row['passed'][$assessment->id], 'text-rose-700' => $score !== null && ! $row['passed'][$assessment->id]])>{{ $score === null ? '—' : fmt_score($score) }}</span></td>
                        @endforeach
                        @foreach ($assignments as $assignment)
                            @php($score = $row['tasks'][$assignment->id])
                            <td class="font-mono">{{ $score === null ? '—' : fmt_score($score) }}</td>
                        @endforeach
                        <td class="font-mono font-bold">{{ $enrollment->final_score !== null ? fmt_score($enrollment->final_score) : '—' }}</td>
                        <td><span class="chip chip-{{ \App\Modules\Enrollment\Models\Enrollment::STATUS_TONES[$enrollment->status] ?? 'neutral' }} px-1.5 py-0.5 text-[10px]">{{ $enrollment->statusLabel() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 5 + $assessments->count() + $assignments->count() }}" class="py-8 text-center text-slate-500">Belum ada peserta yang dinilai.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-xs text-slate-500">Hijau = mencapai KKM pada percobaan terbaik; merah = belum. Skor akhir dihitung mesin kelulusan saat semua syarat terpenuhi.</p>
</x-layouts.app>
