<x-layouts.app title="Nilai Saya" workspace="participant">
    <x-slot:heading>Nilai &amp; Riwayat Belajar</x-slot:heading>
    <x-slot:subtitle>Skor setiap kuis dan ujian (semua percobaan), nilai tugas, durasi belajar, dan riwayat status pelatihan Anda.</x-slot:subtitle>

    <div class="space-y-6">
        @forelse ($enrollments as $enrollment)
            @php($book = $books[$enrollment->id])
            @php($row = $book['rows']->first())
            @php($t = $time->get($enrollment->id))
            <article class="card p-5" aria-labelledby="grade-{{ $enrollment->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="grade-{{ $enrollment->id }}" class="font-extrabold text-slate-800">{{ $enrollment->program->name }}</h2>
                        <p class="text-xs text-slate-500">{{ $enrollment->courseClass->batch_name }} · {{ $enrollment->courseClass->starts_on->translatedFormat('d M Y') }} – {{ $enrollment->courseClass->ends_on->translatedFormat('d M Y') }}@if ($enrollment->group) · Kelompok {{ $enrollment->group->name }}@endif</p>
                    </div>
                    <span class="chip chip-{{ \App\Modules\Enrollment\Models\Enrollment::STATUS_TONES[$enrollment->status] ?? 'neutral' }}">{{ $enrollment->statusLabel() }}</span>
                </div>
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-4">
                    <div><dt class="text-xs text-slate-500">Progres materi</dt><dd class="font-mono font-bold">{{ $enrollment->progress_percent }}%</dd></div>
                    <div><dt class="text-xs text-slate-500">Skor akhir</dt><dd class="font-mono font-bold">{{ $enrollment->final_score !== null ? fmt_score($enrollment->final_score) : '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Durasi belajar</dt><dd class="font-mono font-bold">{{ \App\Modules\Reporting\Services\ClassReportService::duration((int) ($t->seconds ?? 0)) }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Materi selesai</dt><dd class="font-mono font-bold">{{ (int) ($t->done ?? 0) }}</dd></div>
                </dl>

                <div class="mt-5 grid gap-5 lg:grid-cols-2">
                    <section>
                        <h3 class="text-sm font-bold text-slate-800">Kuis &amp; Ujian</h3>
                        <table class="data-table mt-2 text-xs">
                            <thead><tr><th scope="col">Asesmen</th><th scope="col">Percobaan</th><th scope="col">Skor</th><th scope="col">Hasil</th></tr></thead>
                            <tbody>
                                @forelse ($attempts->get($enrollment->id, collect()) as $attempt)
                                    <tr>
                                        <td>{{ $attempt->assessment->title }}<span class="block text-slate-500">{{ \App\Modules\Assessment\Models\Assessment::KINDS[$attempt->assessment->kind] }} · {{ $attempt->started_at->timezone(display_tz())->translatedFormat('d M Y H:i') }}</span></td>
                                        <td class="font-mono">#{{ $attempt->attempt_no }}</td>
                                        <td class="font-mono font-bold">{{ $attempt->score !== null ? fmt_score($attempt->score) : '—' }}</td>
                                        <td>@if ($attempt->status === 'voided')<span class="text-slate-500">dibatalkan</span>@elseif ($attempt->passed === null)<span class="text-amber-700">{{ \App\Modules\Assessment\Models\ExamAttempt::STATUSES[$attempt->status] }}</span>@else<span @class(['font-bold', 'text-emerald-700' => $attempt->passed, 'text-rose-700' => ! $attempt->passed])>{{ $attempt->passed ? 'Lulus' : 'Belum lulus' }}</span> <a href="{{ route('exams.result', $attempt) }}" class="text-link hover:underline">detail</a>@endif</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="py-4 text-center text-slate-500">Belum ada percobaan.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </section>
                    <section>
                        <h3 class="text-sm font-bold text-slate-800">Tugas</h3>
                        <table class="data-table mt-2 text-xs">
                            <thead><tr><th scope="col">Tugas</th><th scope="col">Nilai</th></tr></thead>
                            <tbody>
                                @forelse ($book['assignments'] as $assignment)
                                    <tr><td>{{ $assignment->title }}<span class="block text-slate-500">{{ $assignment->is_required ? 'Wajib' : 'Opsional' }} · maks {{ fmt_score($assignment->max_score) }}</span></td><td class="font-mono font-bold">{{ $row !== null && $row['tasks'][$assignment->id] !== null ? fmt_score($row['tasks'][$assignment->id]) : '—' }}</td></tr>
                                @empty
                                    <tr><td colspan="2" class="py-4 text-center text-slate-500">Tidak ada tugas.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        <h3 class="mt-4 text-sm font-bold text-slate-800">Riwayat Status</h3>
                        <ol class="mt-2 space-y-1 text-xs text-slate-600">
                            @foreach ($history->get($enrollment->id, collect()) as $h)
                                <li><span class="font-mono text-slate-500">{{ \Illuminate\Support\Carbon::parse($h->created_at)->timezone(display_tz())->format('d/m/Y H:i') }}</span> · {{ \App\Modules\Enrollment\Models\Enrollment::STATUSES[$h->to_status] ?? $h->to_status }}@if ($h->reason) <span class="text-slate-500">— {{ $h->reason }}</span>@endif</li>
                            @endforeach
                        </ol>
                    </section>
                </div>
                <a href="{{ route('learning.classroom', $enrollment) }}" class="btn-secondary mt-4">Buka Kelas</a>
            </article>
        @empty
            <div class="card p-8 text-center text-sm text-slate-500">Belum ada pelatihan yang dinilai.</div>
        @endforelse
    </div>
</x-layouts.app>
