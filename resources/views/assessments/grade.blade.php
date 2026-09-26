@php($aiConfigured = \App\Modules\Ai\Services\ClaudeClient::configured() && auth()->user()->can('ai.author'))
<x-layouts.app title="Penilaian Esai" :workspace="$workspace">
    <x-slot:back><a href="{{ route('assessments.attempts', [$class, $attempt->assessment_id]) }}" class="hero-back">&larr; Attempt {{ $attempt->assessment->title }}</a></x-slot:back>
    <x-slot:heading>Penilaian — {{ $attempt->enrollment->user->name }}</x-slot:heading>
    <x-slot:subtitle>Attempt #{{ $attempt->attempt_no }}. Soal objektif sudah dinilai otomatis; beri poin untuk setiap esai.</x-slot:subtitle>

    <form method="POST" action="{{ route('assessments.grade.store', [$class, $attempt]) }}" class="space-y-4" novalidate>@csrf
        @foreach ($attempt->question_order as $index => $questionId)
            @php($question = $questions->get($questionId))
            @continue($question === null)
            @php($answer = $answers->get($questionId))
            <article class="card p-5">
                <p class="text-xs font-bold text-slate-500">Soal {{ $index + 1 }} · {{ \App\Modules\Assessment\Models\Question::TYPES[$question->type] }} · maks. {{ fmt_score($question->points) }} poin</p>
                <div class="prose-content mt-1 text-sm">@include('components.safe-html', ['html' => $question->stem_html])</div>
                @if ($question->type === 'essay')
                    <div class="mt-3 rounded-lg bg-slate-50 p-3 text-sm whitespace-pre-line">{{ $answer?->text_answer ?: '(tidak dijawab)' }}</div>
                    @if ($question->rubric)
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            @foreach ($question->rubric as $criterion)
                                <div>
                                    <label for="r-{{ $question->id }}-{{ $loop->index }}" class="form-label">{{ $criterion['name'] }} (maks. {{ fmt_score($criterion['max']) }})@if (! empty($criterion['description']))<span class="block text-xs font-normal text-slate-500">{{ $criterion['description'] }}</span>@endif</label>
                                    <input id="r-{{ $question->id }}-{{ $loop->index }}" name="rubric[{{ $question->id }}][{{ $criterion['name'] }}]" type="number" min="0" max="{{ $criterion['max'] }}" step="0.5" value="{{ $answer?->rubric_scores[$criterion['name']] ?? '' }}" class="form-input py-1.5">
                                </div>
                            @endforeach
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Poin soal dihitung dari rubrik (diskalakan ke {{ fmt_score($question->points) }} poin).</p>
                    @else
                        <div class="mt-3 max-w-xs">
                            <label for="p-{{ $question->id }}" class="form-label">Poin</label>
                            <input id="p-{{ $question->id }}" name="points[{{ $question->id }}]" type="number" min="0" max="{{ $question->points }}" step="0.5" value="{{ $answer?->points_awarded }}" required class="form-input">
                        </div>
                    @endif
                    <div class="mt-3">
                        <label for="fb-{{ $question->id }}" class="form-label">Umpan balik untuk peserta (opsional)</label>
                        <textarea id="fb-{{ $question->id }}" name="feedback[{{ $question->id }}]" rows="2" maxlength="3000" class="form-input">{{ $answer?->feedback ?: ($answer?->ai_suggestion['feedback'] ?? '') }}</textarea>
                    </div>
                    @if ($aiConfigured)
                        <div class="mt-3 rounded-lg border border-dashed border-brand-200 bg-brand-50/40 p-3 text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-bold text-slate-800">Saran penilaian AI</span>
                                <button type="submit" form="ai-form-{{ $question->id }}" class="btn-mini">{{ $answer?->ai_suggestion ? 'Minta ulang' : 'Minta saran AI' }}</button>
                            </div>
                            @if ($answer?->ai_suggestion)
                                <p class="mt-2"><span class="font-mono font-bold">{{ fmt_score($answer->ai_suggestion['score']) }}</span> / {{ fmt_score($question->points) }} poin — {{ $answer->ai_suggestion['feedback'] }}</p>
                                @if (! empty($answer->ai_suggestion['rubric']))<p class="mt-1 text-xs text-slate-500">Rubrik: {{ collect($answer->ai_suggestion['rubric'])->map(fn ($v, $k) => $k.' '.$v)->implode(', ') }}</p>@endif
                                <p class="mt-1 text-[11px] text-slate-500">Saran, bukan keputusan — umpan balik sudah diisikan ke kolom di atas bila masih kosong; sesuaikan poin sendiri.</p>
                            @else
                                <p class="mt-1 text-xs text-slate-500">AI membaca pertanyaan, rubrik, dan jawaban peserta lalu mengusulkan poin & umpan balik.</p>
                            @endif
                        </div>
                    @endif
                @else
                    <p class="mt-2 text-sm">{{ $answer?->is_correct ? '✓ Benar' : '✗ Salah' }} ({{ fmt_score(($answer?->points_awarded ?? '0')) ?: '0' }} poin)</p>
                @endif
            </article>
        @endforeach
        <x-form-error field="points" />
        <button class="btn-primary w-auto">Simpan Penilaian</button>
    </form>
    @if ($aiConfigured)
        @foreach ($attempt->question_order as $questionId)
            @if (($questions->get($questionId)?->type ?? null) === 'essay')<form id="ai-form-{{ $questionId }}" method="POST" action="{{ route('ai.essay-feedback', [$class, $attempt, $questionId]) }}">@csrf</form>@endif
        @endforeach
    @endif
</x-layouts.app>
