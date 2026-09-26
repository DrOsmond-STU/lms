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
                                @php($locked = $locks[$lesson->id] ?? null)
                                <li>
                                    <a href="{{ route('learning.lesson', [$enrollment, $lesson]) }}" @class(['flex items-center justify-between gap-3 py-2 hover:text-link', 'opacity-60' => $locked !== null]) @if ($locked) title="{{ $locked }}" @endif>
                                        <span class="flex items-center gap-2">
                                            <span @class(['flex h-5 w-5 items-center justify-center rounded-full text-[0.65rem] font-bold', 'bg-emerald-500 text-white' => isset($done[$lesson->id]), 'border border-slate-300 text-slate-400' => ! isset($done[$lesson->id])]) aria-hidden="true">{{ isset($done[$lesson->id]) ? '✓' : ($locked ? '🔒' : '') }}</span>
                                            <span>{{ $lesson->title }}</span>
                                            @unless ($lesson->is_required)<span class="text-xs text-slate-400">(opsional)</span>@endunless
                                            @if ($locked)<span class="text-xs text-amber-700">{{ $locked }}</span>@endif
                                            <span class="sr-only">{{ isset($done[$lesson->id]) ? 'selesai' : ($locked ? 'terkunci' : 'belum selesai') }}</span>
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
                    @if ($check['assignments_total'] > 0)<li>{{ $check['assignments_done'] === $check['assignments_total'] ? '✓' : '○' }} Tugas wajib dinilai: {{ $check['assignments_done'] }}/{{ $check['assignments_total'] }}</li>@endif
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

            @if ($aiConfigured)
                <section class="card p-5" aria-labelledby="reco-heading">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 id="reco-heading" class="font-bold text-slate-800">Rekomendasi AI</h2>
                        @if ($enrollment->isActive())<form method="POST" action="{{ route('ai.recommend', $enrollment) }}">@csrf<button class="btn-secondary min-h-0 px-3 py-1.5 text-xs">{{ $recommendation ? 'Perbarui' : 'Buat rekomendasi' }}</button></form>@endif
                    </div>
                    @if ($recommendation)
                        <div class="prose-content mt-3 text-sm">@include('components.safe-html', ['html' => $recommendation->body_html])</div>
                        <p class="mt-2 text-[11px] text-slate-500">{{ $recommendation->generated_at->timezone(display_tz())->translatedFormat('d M H:i') }} · <a href="{{ route('learning.path') }}" class="font-bold text-link hover:underline">jalur belajar lengkap</a></p>
                    @else
                        <p class="mt-2 text-xs text-slate-500">Langkah belajar berikutnya berdasarkan progres dan hasil kuis Anda.</p>
                    @endif
                </section>
            @endif

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

            @if ($enrollment->courseClass->discussion_enabled || $enrollment->courseClass->chat_enabled)
                <section class="card p-5" aria-labelledby="interact-heading">
                    <h2 id="interact-heading" class="font-bold text-slate-800">Ruang Interaktif</h2>
                    <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                        @if ($enrollment->courseClass->discussion_enabled)
                            <a href="{{ route('discussion.index', [$enrollment->course_class_id, 'jenis' => 'discussion']) }}" class="btn-secondary">Forum Diskusi</a>
                            <a href="{{ route('discussion.index', [$enrollment->course_class_id, 'jenis' => 'question']) }}" class="btn-secondary">Tanya Jawab</a>
                        @endif
                        <a href="{{ route('discussion.polls', $enrollment->course_class_id) }}" class="btn-secondary">Polling</a>
                        @if ($enrollment->courseClass->chat_enabled)<a href="{{ route('discussion.chat', $enrollment->course_class_id) }}" class="btn-secondary">Obrolan Kelas</a>@endif
                    </div>
                </section>
            @endif

            <section class="card p-5" aria-labelledby="assign-heading">
                <h2 id="assign-heading" class="font-bold text-slate-800">Tugas</h2>
                <ul class="mt-3 space-y-3 text-sm">
                    @forelse ($assignments as $assignment)
                        @php($sub = $submissions->get($assignment->id))
                        <li>
                            <a href="{{ route('assignments.show', [$enrollment, $assignment]) }}" class="font-bold text-link hover:underline">{{ $assignment->title }}</a>
                            <span class="block text-xs text-slate-500">
                                {{ $assignment->is_required ? 'Wajib' : 'Opsional' }} · tenggat {{ $assignment->due_at?->timezone(display_tz())->translatedFormat('d M H:i') ?? '—' }}
                                · @if ($sub)<span @class(['font-bold', 'text-emerald-700' => $sub->status === 'graded', 'text-amber-700' => $sub->status !== 'graded'])>{{ $sub->statusLabel() }}{{ $sub->score !== null ? ' '.fmt_score($sub->score).'/'.fmt_score($assignment->max_score) : '' }}</span>@elseif ($assignment->isOverdue() && ! $assignment->allow_late)<span class="text-rose-700">tenggat lewat</span>@else belum dikumpulkan @endif
                            </span>
                        </li>
                    @empty
                        <li class="text-slate-500">Belum ada tugas.</li>
                    @endforelse
                </ul>
            </section>

            <section class="card p-5" aria-labelledby="sessions-heading">
                <h2 id="sessions-heading" class="font-bold text-slate-800">Sesi &amp; Live Class</h2>
                <x-form-error field="checkin" />
                <ul class="mt-3 space-y-3 text-sm">
                    @forelse ($sessions as $session)
                        @php($record = $attendance->get($session->id))
                        <li>
                            <span class="font-bold text-slate-800">{{ $session->title }}</span>
                            <span class="block text-xs text-slate-500">{{ $session->starts_at->timezone(display_tz())->translatedFormat('D, d M Y H:i') }}–{{ $session->ends_at->timezone(display_tz())->format('H:i') }} {{ tz_label() }} · {{ \App\Modules\Learning\Models\ClassSession::TYPES[$session->type] }}@if ($session->location) · {{ $session->location }}@endif</span>
                            <span class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                @if ($session->joinOpen())<a href="{{ $session->meeting_url }}" target="_blank" rel="noopener noreferrer" class="btn-primary min-h-0 w-auto px-3 py-1.5">Gabung Live Class</a>@elseif ($session->isLive() && ! $session->isPast())<span class="text-slate-500">Tautan meeting tampil 30 menit sebelum mulai.</span>@endif
                                @if ($record)<span class="badge bg-emerald-50 text-emerald-700">{{ $record->statusLabel() }}</span>
                                @elseif ($enrollment->isActive() && $session->checkinOpen())
                                    <form method="POST" action="{{ route('schedule.checkin', [$enrollment, $session]) }}" class="flex items-center gap-1">@csrf
                                        @if ($session->checkin_code)<input name="code" maxlength="8" placeholder="Kode" class="form-input min-h-0 w-24 py-1 font-mono text-xs uppercase" aria-label="Kode cek-in">@endif
                                        <button class="btn-secondary min-h-0 px-3 py-1.5">Cek-in Hadir</button>
                                    </form>
                                @elseif ($session->attendance_mode === 'self' && ! $session->isPast())<span class="text-slate-500">Cek-in dibuka {{ $session->checkin_opens_before }} menit sebelum mulai.</span>@endif
                            </span>
                        </li>
                    @empty
                        <li class="text-slate-500">Belum ada sesi terjadwal.</li>
                    @endforelse
                </ul>
                <a href="{{ route('schedule.participant') }}" class="mt-3 block text-xs font-bold text-link hover:underline">Lihat jadwal lengkap &rarr;</a>
            </section>

            <section class="card p-5 text-sm text-slate-600">
                <h2 class="font-bold text-slate-800">Kelas</h2>
                <p class="mt-2">{{ $enrollment->courseClass->batch_name }}</p>
                <p class="text-xs">Trainer: {{ $enrollment->courseClass->trainers->pluck('name')->implode(', ') ?: '—' }}</p>
            </section>
        </aside>
    </div>
</x-layouts.app>
