<x-layouts.app :title="$attempt->assessment->title" workspace="participant" :hero="false">
    <div data-exam data-seconds-left="{{ $attempt->secondsLeft() }}" data-answer-url="{{ route('exams.answer', $attempt) }}" data-integrity-url="{{ route('exams.integrity', $attempt) }}">
        <div class="sticky top-[58px] z-20 -mx-4 mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-surface/95 px-4 py-3 backdrop-blur sm:-mx-8 sm:px-8 lg:top-0">
            <div>
                <h1 class="font-extrabold text-slate-800">{{ $attempt->assessment->title }} · Attempt #{{ $attempt->attempt_no }}</h1>
                <p class="text-xs text-slate-500" data-exam-status aria-live="polite">Jawaban tersimpan otomatis.</p>
            </div>
            <div class="text-right">
                <span class="block text-xs text-slate-500">Sisa waktu</span>
                <span class="font-mono text-2xl font-extrabold text-slate-800" data-exam-timer>{{ sprintf('%02d:%02d', intdiv($attempt->secondsLeft(), 60), $attempt->secondsLeft() % 60) }}</span>
                <span class="block text-xs text-slate-500">batas {{ $attempt->deadline_at->timezone(display_tz())->format('H:i') }} {{ tz_label() }}</span>
            </div>
        </div>

        <nav class="mb-5 flex flex-wrap gap-1.5" aria-label="Nomor soal">
            @foreach ($items as $item)
                @php($answered = collect($item['options'])->contains('selected', true) || filled($item['text_answer']))
                <a href="#q-{{ $item['alias'] }}" data-answered="{{ $item['alias'] }}" @class(['flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-xs font-bold', 'bg-accent-500 text-white' => $answered])>{{ $item['number'] }}</a>
            @endforeach
        </nav>

        <form method="POST" action="{{ route('exams.submit', $attempt) }}" data-exam-form data-confirm="Kumpulkan jawaban sekarang? Jawaban tidak dapat diubah setelah dikumpulkan." class="space-y-5">
            @csrf
            @foreach ($items as $item)
                <fieldset id="q-{{ $item['alias'] }}" class="card scroll-mt-40 p-5">
                    <legend class="sr-only">Soal {{ $item['number'] }}</legend>
                    <p class="text-xs font-bold text-slate-500">Soal {{ $item['number'] }} · {{ fmt_score($item['points']) }} poin{{ $item['type'] === 'multiple_choice' ? ' · pilih semua yang benar' : '' }}</p>
                    <div class="prose-content mt-1 text-sm">@include('components.safe-html', ['html' => $item['stem_html']])</div>
                    <div class="mt-3 space-y-2">
                        @if (in_array($item['type'], ['single_choice', 'true_false', 'multiple_choice'], true))
                            @foreach ($item['options'] as $option)
                                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 text-sm hover:bg-slate-50">
                                    <input type="{{ $item['type'] === 'multiple_choice' ? 'checkbox' : 'radio' }}" name="answers[{{ $item['alias'] }}][]" value="{{ $option['alias'] }}" data-question="{{ $item['alias'] }}" @checked($option['selected']) class="mt-0.5">
                                    <span>@include('components.safe-html', ['html' => $option['body_html']])</span>
                                </label>
                            @endforeach
                        @elseif ($item['type'] === 'short_answer')
                            <label for="t-{{ $item['alias'] }}" class="sr-only">Jawaban</label>
                            <input id="t-{{ $item['alias'] }}" type="text" name="texts[{{ $item['alias'] }}]" value="{{ $item['text_answer'] }}" maxlength="500" data-question="{{ $item['alias'] }}" class="form-input" autocomplete="off">
                        @else
                            <label for="t-{{ $item['alias'] }}" class="sr-only">Jawaban esai</label>
                            <textarea id="t-{{ $item['alias'] }}" name="texts[{{ $item['alias'] }}]" rows="6" maxlength="10000" data-question="{{ $item['alias'] }}" class="form-input">{{ $item['text_answer'] }}</textarea>
                        @endif
                    </div>
                </fieldset>
            @endforeach
            <x-form-error field="options" />
            <x-form-error field="question" />
            <button type="submit" class="btn-primary w-auto">Kumpulkan Jawaban</button>
        </form>
    </div>
</x-layouts.app>
