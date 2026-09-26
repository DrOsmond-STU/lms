<x-layouts.app :title="$assignment->title" workspace="participant">
    <x-slot:back><a href="{{ route('learning.classroom', $enrollment) }}" class="hero-back">&larr; {{ $enrollment->program->name }}</a></x-slot:back>
    <x-slot:heading>{{ $assignment->title }}</x-slot:heading>
    <x-slot:meta>
        <span class="badge bg-slate-100 text-slate-600">Tugas {{ $assignment->is_required ? 'wajib' : 'opsional' }}</span>
        @if ($submission)<span class="badge bg-brand-50 text-link">{{ $submission->statusLabel() }}</span>@endif
    </x-slot:meta>
    <x-slot:subtitle>Tenggat: {{ $assignment->due_at?->timezone(display_tz())->translatedFormat('l, d M Y H:i').' '.tz_label() ?? 'tidak ada' }}{{ $assignment->isOverdue() ? ' (sudah lewat'.($assignment->allow_late ? ', pengumpulan terlambat diterima' : '').')' : '' }} · skor maksimal {{ fmt_score($assignment->max_score) }}</x-slot:subtitle>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card p-6">
                <h2 class="font-bold text-slate-800">Instruksi</h2>
                <div class="prose-content mt-2 text-sm">@include('components.safe-html', ['html' => $assignment->instructions_html ?? '<p>Tidak ada instruksi tambahan.</p>'])</div>
                @if ($assignment->rubric)
                    <h3 class="mt-4 text-xs font-bold tracking-wide text-slate-500 uppercase">Rubrik penilaian</h3>
                    <ul class="mt-1 text-sm">@foreach ($assignment->rubric as $criterion)<li>• {{ $criterion['name'] }} — maks. {{ fmt_score($criterion['max']) }} poin{{ ! empty($criterion['description']) ? ': '.$criterion['description'] : '' }}</li>@endforeach</ul>
                @endif
            </section>

            @if ($submission?->status === 'graded' || $submission?->feedback)
                <section class="card p-6" aria-labelledby="grade-heading">
                    <h2 id="grade-heading" class="font-bold text-slate-800">{{ $submission->status === 'graded' ? 'Nilai' : 'Umpan balik' }}</h2>
                    @if ($submission->status === 'graded')
                        <p class="mt-2 text-3xl font-extrabold {{ $assignment->passing_score === null || (float) $submission->score >= (float) $assignment->passing_score ? 'text-emerald-700' : 'text-rose-700' }}">{{ fmt_score($submission->score) }} <span class="text-base font-normal text-slate-500">/ {{ fmt_score($assignment->max_score) }}</span></p>
                        @if ($submission->rubric_scores)<ul class="mt-2 text-sm">@foreach ($submission->rubric_scores as $name => $value)<li>{{ $name }}: {{ fmt_score($value) }}</li>@endforeach</ul>@endif
                    @endif
                    @if ($submission->feedback)<div class="mt-3 rounded-lg bg-slate-50 p-3 text-sm whitespace-pre-line">{{ $submission->feedback }}</div>@endif
                </section>
            @endif

            <section class="card p-6" aria-labelledby="submit-heading">
                <h2 id="submit-heading" class="font-bold text-slate-800">{{ $submission ? 'Pengumpulan Anda' : 'Kumpulkan Tugas' }}</h2>
                @if ($submission)
                    <p class="mt-1 text-xs text-slate-500">Versi {{ $submission->version }} · {{ $submission->submitted_at->timezone(display_tz())->translatedFormat('d M Y H:i') }} {{ tz_label() }}{{ $submission->is_late ? ' · terlambat' : '' }}</p>
                    @if ($submission->text_answer)<div class="mt-3 rounded-lg bg-slate-50 p-3 text-sm whitespace-pre-line">{{ $submission->text_answer }}</div>@endif
                    @if ($fileUrl)<a href="{{ $fileUrl }}" class="btn-secondary mt-3">Unduh berkas saya ({{ $submission->media->original_filename }})</a>@endif
                @endif
                <x-form-error field="submission" />
                @if ($canSubmit)
                    <form method="POST" enctype="multipart/form-data" action="{{ route('assignments.submit', [$enrollment, $assignment]) }}" class="mt-4 space-y-3 border-t border-slate-100 pt-4" novalidate>@csrf
                        @if ($assignment->allow_text)
                            <div><label for="text_answer" class="form-label">Jawaban teks</label><textarea id="text_answer" name="text_answer" rows="8" maxlength="20000" class="form-input">{{ old('text_answer', $submission?->text_answer) }}</textarea><x-form-error field="text_answer" /></div>
                        @endif
                        @if ($assignment->allow_file)
                            <div><label for="file" class="form-label">Berkas (PDF, dokumen kantor, gambar, zip; maks. {{ config('media.types.submission.max_mb') }} MB){{ $submission?->media_asset_id ? ' — kosongkan bila tidak diganti' : '' }}</label><input id="file" name="file" type="file" class="form-input"><x-form-error field="file" /></div>
                        @endif
                        <button class="btn-primary w-auto">{{ $submission ? 'Kumpulkan Ulang' : 'Kumpulkan' }}</button>
                    </form>
                @elseif (! $submission)
                    <p class="mt-3 text-sm text-amber-700">Pengumpulan sudah ditutup.</p>
                @endif
            </section>
        </div>
        <aside class="card p-5 text-sm text-slate-600">
            <h2 class="font-bold text-slate-800">Ketentuan</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>Anda dapat mengumpulkan ulang sampai tugas dinilai.</li>
                <li>Tugas yang dikembalikan untuk revisi dapat dikumpulkan lagi setelah membaca umpan balik.</li>
                <li>Berkas dipindai dan hanya dapat diunduh oleh Anda dan trainer kelas.</li>
            </ul>
        </aside>
    </div>
</x-layouts.app>
