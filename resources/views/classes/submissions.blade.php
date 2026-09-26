<x-layouts.app :title="'Pengumpulan '.$assignment->title" :workspace="$workspace">
    <x-slot:back><a href="{{ route('classes.assignments', $class) }}" class="hero-back">&larr; Tugas {{ $class->batch_name }}</a></x-slot:back>
    <x-slot:heading>{{ $assignment->title }}</x-slot:heading>
    <x-slot:subtitle>Tenggat {{ $assignment->due_at?->timezone(display_tz())->translatedFormat('d M Y H:i') ?? 'tidak ada' }} · skor maks. {{ fmt_score($assignment->max_score) }}@if ($assignment->rubric) · rubrik {{ count($assignment->rubric) }} kriteria @endif</x-slot:subtitle>
    <x-slot:aside><div class="num">{{ $submissions->where('status', 'submitted')->count() }}</div><div class="lbl">Menunggu nilai</div></x-slot:aside>
    <x-form-error field="score" />

    <div class="space-y-4">
        @forelse ($enrollments as $enrollment)
            @php($submission = $submissions->get($enrollment->id))
            <article class="card p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-bold text-slate-800">{{ $enrollment->user->name }}</h2>
                        <p class="text-xs text-slate-500">
                            @if ($submission)
                                Dikumpulkan {{ $submission->submitted_at->timezone(display_tz())->translatedFormat('d M Y H:i') }} (versi {{ $submission->version }}){{ $submission->is_late ? ' · terlambat' : '' }} · <span class="font-bold">{{ $submission->statusLabel() }}</span>
                                @if ($submission->score !== null) · skor {{ fmt_score($submission->score) }}/{{ fmt_score($assignment->max_score) }}@endif
                            @else
                                Belum mengumpulkan
                            @endif
                        </p>
                    </div>
                    @if ($submission && $fileUrls->has($submission->id))<a href="{{ $fileUrls[$submission->id] }}" class="btn-secondary">Unduh berkas ({{ $submission->media->original_filename }})</a>@endif
                </div>
                @if ($submission?->text_answer)
                    <div class="mt-3 rounded-lg bg-slate-50 p-3 text-sm whitespace-pre-line">{{ $submission->text_answer }}</div>
                @endif
                @if ($submission && $canGrade)
                    <form method="POST" action="{{ route('assignments.grade', [$class, $submission]) }}" class="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-[1fr_2fr]" novalidate>@csrf
                        <div class="space-y-2">
                            @if ($assignment->rubric)
                                @foreach ($assignment->rubric as $criterion)
                                    <div>
                                        <label for="r-{{ $submission->id }}-{{ $loop->index }}" class="form-label">{{ $criterion['name'] }} (maks. {{ fmt_score($criterion['max']) }})@if (! empty($criterion['description']))<span class="block text-xs font-normal text-slate-500">{{ $criterion['description'] }}</span>@endif</label>
                                        <input id="r-{{ $submission->id }}-{{ $loop->index }}" name="rubric[{{ $criterion['name'] }}]" type="number" min="0" max="{{ $criterion['max'] }}" step="0.5" value="{{ $submission->rubric_scores[$criterion['name']] ?? '' }}" class="form-input py-1.5">
                                    </div>
                                @endforeach
                                <p class="text-xs text-slate-500">Skor akhir dihitung dari rubrik.</p>
                            @else
                                <label for="s-{{ $submission->id }}" class="form-label">Skor (0–{{ fmt_score($assignment->max_score) }})</label>
                                <input id="s-{{ $submission->id }}" name="score" type="number" min="0" max="{{ $assignment->max_score }}" step="0.5" value="{{ $submission->score }}" class="form-input py-1.5">
                            @endif
                        </div>
                        <div>
                            <label for="f-{{ $submission->id }}" class="form-label">Umpan balik untuk peserta</label>
                            <textarea id="f-{{ $submission->id }}" name="feedback" rows="3" maxlength="5000" class="form-input py-1.5">{{ $submission->feedback }}</textarea>
                            <div class="mt-2 flex gap-2">
                                <button name="action" value="grade" class="btn-primary w-auto">Simpan Nilai</button>
                                <button name="action" value="return" class="btn-secondary" data-confirm="Kembalikan tugas ini untuk direvisi peserta?">Kembalikan untuk Revisi</button>
                            </div>
                        </div>
                    </form>
                @endif
            </article>
        @empty
            <div class="card p-8 text-center text-sm text-slate-500">Belum ada peserta.</div>
        @endforelse
    </div>
</x-layouts.app>
