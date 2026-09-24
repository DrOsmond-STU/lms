<x-layouts.app title="Penilaian Esai" :workspace="$workspace">
    <a href="{{ route('assessments.attempts', [$class, $attempt->assessment_id]) }}" class="text-sm font-bold text-brand-700 hover:underline">&larr; Attempt {{ $attempt->assessment->title }}</a>
    <h1 class="mt-2 text-xl font-extrabold text-slate-800">Penilaian — {{ $attempt->enrollment->user->name }}</h1>
    <p class="mt-0.5 mb-6 text-sm text-slate-600">Attempt #{{ $attempt->attempt_no }}. Soal objektif sudah dinilai otomatis; beri poin untuk setiap esai.</p>
    <form method="POST" action="{{ route('assessments.grade.store', [$class, $attempt]) }}" class="space-y-4" novalidate>@csrf
        @foreach ($attempt->question_order as $index => $questionId)
            @php($question = $questions->get($questionId))
            @continue($question === null)
            @php($answer = $answers->get($questionId))
            <article class="card p-5">
                <p class="text-xs font-bold text-slate-500">Soal {{ $index + 1 }} · {{ \App\Modules\Assessment\Models\Question::TYPES[$question->type] }} · maks. {{ rtrim(rtrim($question->points, '0'), '.') }} poin</p>
                <div class="prose-content mt-1 text-sm">@include('components.safe-html', ['html' => $question->stem_html])</div>
                @if ($question->type === 'essay')
                    <div class="mt-3 rounded-lg bg-slate-50 p-3 text-sm whitespace-pre-line">{{ $answer?->text_answer ?: '(tidak dijawab)' }}</div>
                    <div class="mt-3 max-w-xs">
                        <label for="p-{{ $question->id }}" class="form-label">Poin</label>
                        <input id="p-{{ $question->id }}" name="points[{{ $question->id }}]" type="number" min="0" max="{{ $question->points }}" step="0.5" value="{{ $answer?->points_awarded }}" required class="form-input">
                    </div>
                @else
                    <p class="mt-2 text-sm">{{ $answer?->is_correct ? '✓ Benar' : '✗ Salah' }} ({{ rtrim(rtrim((string) ($answer?->points_awarded ?? '0'), '0'), '.') ?: '0' }} poin)</p>
                @endif
            </article>
        @endforeach
        <x-form-error field="points" />
        <button class="btn-primary w-auto">Simpan Penilaian</button>
    </form>
</x-layouts.app>
