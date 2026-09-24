<x-layouts.app :title="$enrollment->program->name" workspace="participant">
    <x-slot:back><a href="{{ route('learning.index') }}" class="hero-back">&larr; Pembelajaran Saya</a></x-slot:back>
    <x-slot:heading>{{ $enrollment->program->name }}</x-slot:heading>
    <x-slot:meta>
        <span class="badge bg-brand-50 text-link">{{ $enrollment->statusLabel() }}</span>
    </x-slot:meta>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @forelse ($modules as $module)
                <section class="card p-5" aria-labelledby="m-{{ $module->id }}">
                    <h2 id="m-{{ $module->id }}" class="font-bold text-slate-800">Modul {{ $loop->iteration }}: {{ $module->title }}</h2>
                    @foreach ($module->chapters as $chapter)
                        <h3 class="mt-3 text-xs font-bold tracking-wide text-slate-500 uppercase">{{ $chapter->title }}</h3>
                        <ul class="mt-1 divide-y divide-slate-100 text-sm">
                            @foreach ($chapter->lessons as $lesson)
                                <li>
                                    <a href="{{ route('learning.lesson', [$enrollment, $lesson]) }}" class="flex items-center justify-between gap-3 py-2 hover:text-link">
                                        <span class="flex items-center gap-2">
                                            <span @class(['flex h-5 w-5 items-center justify-center rounded-full text-[0.65rem] font-bold', 'bg-emerald-500 text-white' => isset($done[$lesson->id]), 'border border-slate-300 text-slate-400' => ! isset($done[$lesson->id])]) aria-hidden="true">{{ isset($done[$lesson->id]) ? '✓' : '' }}</span>
                                            <span>{{ $lesson->title }}</span>
                                            @unless ($lesson->is_required)<span class="text-xs text-slate-400">(opsional)</span>@endunless
                                            <span class="sr-only">{{ isset($done[$lesson->id]) ? 'selesai' : 'belum selesai' }}</span>
                                        </span>
                                        <span class="badge bg-slate-100 text-slate-600">{{ \App\Modules\Learning\Models\Lesson::TYPES[$lesson->type] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endforeach
                </section>
            @empty
                <div class="card p-8 text-center text-sm text-slate-500">Materi belum tersedia. Trainer sedang menyiapkan konten.</div>
            @endforelse
        </div>

        <aside class="space-y-6">
            <section class="card p-5" aria-labelledby="progress-heading">
                <h2 id="progress-heading" class="font-bold text-slate-800">Progres Kelulusan</h2>
                <div class="mt-3 flex items-center gap-3">
                    <div class="h-2 flex-1 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-accent-500 progress-{{ (int) (floor($enrollment->progress_percent / 5) * 5) }}"></div></div>
                    <span class="text-xs font-bold">{{ $enrollment->progress_percent }}%</span>
                </div>
                <ul class="mt-4 space-y-2 text-sm">
                    <li>{{ $check['lessons_done'] === $check['lessons_total'] ? '✓' : '○' }} Materi wajib: {{ $check['lessons_done'] }}/{{ $check['lessons_total'] }}</li>
                    <li>{{ $check['quizzes_ok'] ? '✓' : '○' }} Kuis wajib lulus</li>
                    @if ($check['final_required'])
                        <li>{{ $check['final_score'] !== null && $check['final_score'] >= $check['min_score'] ? '✓' : '○' }} Ujian akhir ≥ {{ fmt_score($check['min_score']) }} (terbaik: {{ $check['final_score'] ?? '—' }})</li>
                    @endif
                </ul>
                @if ($enrollment->status === 'pending_approval')
                    <p class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-800">Semua syarat terpenuhi. Sertifikat menunggu persetujuan Admin Akademik.</p>
                @elseif ($enrollment->status === 'passed')
                    <a href="{{ route('certificates.mine') }}" class="btn-primary mt-3">Lihat Sertifikat</a>
                @endif
            </section>

            <section class="card p-5" aria-labelledby="assess-heading">
                <h2 id="assess-heading" class="font-bold text-slate-800">Kuis &amp; Ujian</h2>
                <ul class="mt-3 space-y-3 text-sm">
                    @forelse ($assessments as $assessment)
                        @php($stat = $attemptStats->get($assessment->id))
                        <li>
                            <a href="{{ route('exams.show', [$enrollment, $assessment]) }}" class="font-bold text-link hover:underline">{{ $assessment->title }}</a>
                            <span class="block text-xs text-slate-500">{{ \App\Modules\Assessment\Models\Assessment::KINDS[$assessment->kind] }} · sisa {{ max(0, $remaining[$assessment->id]) }} kesempatan
                                @if ($stat) · terbaik {{ $stat->best !== null ? fmt_score($stat->best) : '—' }} {{ $stat->passed ? '✓' : '' }}@endif
                                @if ($stat?->active) · <span class="font-bold text-amber-700">sedang dikerjakan</span>@endif
                            </span>
                        </li>
                    @empty
                        <li class="text-slate-500">Belum ada asesmen.</li>
                    @endforelse
                </ul>
            </section>

            <section class="card p-5 text-sm text-slate-600">
                <h2 class="font-bold text-slate-800">Kelas</h2>
                <p class="mt-2">{{ $enrollment->courseClass->batch_name }}</p>
                <p class="text-xs">Trainer: {{ $enrollment->courseClass->trainers->pluck('name')->implode(', ') ?: '—' }}</p>
            </section>
        </aside>
    </div>
</x-layouts.app>
