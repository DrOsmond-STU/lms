<x-layouts.app title="Hasil Asesmen" workspace="participant">
    <x-slot:back><a href="{{ route('exams.show', [$attempt->enrollment_id, $attempt->assessment_id]) }}" class="hero-back">&larr; {{ $attempt->assessment->title }}</a></x-slot:back>
    <x-slot:heading>Hasil — Attempt #{{ $attempt->attempt_no }}</x-slot:heading>

    <section class="card p-6">
        @if ($attempt->status === 'graded')
            <p class="text-sm text-slate-500">Skor</p>
            <p @class(['text-4xl font-extrabold', 'text-emerald-700' => $attempt->passed, 'text-rose-700' => ! $attempt->passed])>{{ fmt_score($attempt->score) }}</p>
            <p class="mt-1 text-sm">{{ $attempt->passed ? 'Lulus — skor mencapai minimal '.fmt_score($attempt->assessment->passing_score).'.' : 'Belum mencapai skor minimal '.fmt_score($attempt->assessment->passing_score).'.' }}</p>
        @elseif ($attempt->status === 'voided')
            <p class="text-sm text-rose-700">Attempt ini dibatalkan. Alasan: {{ $attempt->voided_reason }}</p>
        @else
            <p class="text-sm text-slate-600">Jawaban Anda sedang menunggu penilaian trainer (ada soal esai). Anda akan menerima notifikasi setelah dinilai.</p>
        @endif
        <p class="mt-3 text-xs text-slate-500">Dikumpulkan {{ $attempt->submitted_at?->timezone(display_tz())->format('d M Y H:i') }} {{ tz_label() }}{{ $attempt->status === 'auto_submitted' ? ' (otomatis saat waktu habis)' : '' }}.</p>
    </section>

    @if ($review)
        <h2 class="mt-8 mb-3 font-bold text-slate-800">Pembahasan</h2>
        <div class="space-y-4">
            @foreach ($attempt->question_order as $index => $questionId)
                @php($question = $questions->get($questionId))
                @continue($question === null)
                @php($answer = $answers->get($questionId))
                <article class="card p-5">
                    <p class="text-xs font-bold {{ $answer?->is_correct ? 'text-emerald-700' : 'text-rose-700' }}">Soal {{ $index + 1 }} · {{ $answer?->is_correct ? 'Benar' : 'Salah' }}</p>
                    <div class="prose-content mt-1 text-sm">@include('components.safe-html', ['html' => $question->stem_html])</div>
                    @if ($question->type === 'matching')
                        <ul class="mt-2 space-y-1 text-sm">
                            @foreach ($question->options as $option)
                                @php($chosen = $answer?->match_pairs[$option->id] ?? null)
                                <li @class(['text-emerald-700' => $chosen === $option->id, 'text-rose-700' => $chosen !== $option->id])>{{ $chosen === $option->id ? '✓' : '✗' }} @include('components.safe-html', ['html' => $option->body_html]) ↔ <span class="font-bold">{{ $option->match_text }}</span></li>
                            @endforeach
                        </ul>
                        <p class="mt-1 text-xs text-slate-500">{{ fmt_score($answer?->points_awarded ?? 0) }} dari {{ fmt_score($question->points) }} poin (kredit parsial).</p>
                    @elseif ($question->options->isNotEmpty())
                        <ul class="mt-2 space-y-1 text-sm">
                            @foreach ($question->options as $option)
                                <li @class(['font-bold text-emerald-700' => $option->is_correct, 'text-rose-700' => ! $option->is_correct && in_array($option->id, $answer?->selected_option_ids ?? [], true)])>
                                    {{ $option->is_correct ? '✓' : (in_array($option->id, $answer?->selected_option_ids ?? [], true) ? '✗' : '○') }} @include('components.safe-html', ['html' => $option->body_html])
                                </li>
                            @endforeach
                        </ul>
                    @elseif ($question->type === 'short_answer')
                        <p class="mt-2 text-sm">Jawaban Anda: {{ $answer?->text_answer ?: '—' }}</p>
                    @elseif ($question->type === 'essay')
                        <div class="mt-2 rounded-lg bg-slate-50 p-3 text-sm whitespace-pre-line">{{ $answer?->text_answer ?: '(tidak dijawab)' }}</div>
                        <p class="mt-1 text-xs text-slate-500">{{ fmt_score($answer?->points_awarded ?? 0) }} dari {{ fmt_score($question->points) }} poin</p>
                        @if ($answer?->rubric_scores)<ul class="mt-1 text-xs text-slate-600">@foreach ($answer->rubric_scores as $name => $value)<li>{{ $name }}: {{ fmt_score($value) }}</li>@endforeach</ul>@endif
                    @endif
                    @if ($answer?->feedback)<div class="mt-2 rounded-lg border border-brand-100 bg-brand-50 p-3 text-sm"><span class="font-bold">Umpan balik trainer:</span> {{ $answer->feedback }}</div>@endif
                    @if ($question->explanation_html)
                        <div class="prose-content mt-3 rounded-lg bg-slate-50 p-3 text-sm">@include('components.safe-html', ['html' => $question->explanation_html])</div>
                    @endif
                </article>
            @endforeach
        </div>
    @elseif ($attempt->status === 'graded' && $attempt->assessment->review_policy !== 'never')
        <p class="mt-6 text-sm text-slate-500">Pembahasan tersedia setelah jendela asesmen ditutup untuk semua peserta.</p>
    @endif
</x-layouts.app>
