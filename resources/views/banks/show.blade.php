<x-layouts.app :title="$bank->name" :workspace="$workspace">
    <x-slot:back><a href="{{ route('banks.index', $bank->program) }}" class="hero-back">&larr; Bank soal {{ $bank->program->name }}</a></x-slot:back>
    <x-slot:heading>{{ $bank->name }}</x-slot:heading>
    <x-slot:subtitle>Kunci jawaban bersifat rahasia (K4). Jangan menyalin soal ke luar platform.</x-slot:subtitle>
    <x-slot:actions>
        <div class="flex flex-wrap gap-2 text-sm">
        @foreach (\App\Modules\Assessment\Models\Question::TYPES as $type => $label)
        <a href="{{ route('questions.create', [$bank, 'tipe' => $type]) }}" class="btn-secondary">+ {{ $label }}</a>
        @endforeach
        </div>
    </x-slot:actions>

    <div class="space-y-3">
        @forelse ($questions as $question)
            <article @class(['card p-5', 'opacity-60' => ! $question->is_active])>
                <div class="mb-2 flex flex-wrap items-center gap-2 text-xs">
                    <span class="badge bg-brand-50 text-link">{{ \App\Modules\Assessment\Models\Question::TYPES[$question->type] }}</span>
                    <span class="badge bg-slate-100 text-slate-600">Kesulitan {{ $question->difficulty }}</span>
                    <span class="badge bg-slate-100 text-slate-600">{{ rtrim(rtrim($question->points, '0'), '.') }} poin</span>
                    <span class="badge bg-slate-100 text-slate-600">v{{ $question->version }}</span>
                    @unless ($question->is_active)<span class="badge bg-slate-200 text-slate-700">Nonaktif</span>@endunless
                    @foreach ($question->competency_tags as $tag)<span class="text-slate-500">#{{ $tag }}</span>@endforeach
                </div>
                <div class="prose-content text-sm">@include('components.safe-html', ['html' => $question->stem_html])</div>
                @if ($question->options->isNotEmpty())
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach ($question->options as $option)
                            <li @class(['font-bold text-emerald-700' => $option->is_correct])>{{ $option->is_correct ? '✓' : '○' }} @include('components.safe-html', ['html' => $option->body_html])</li>
                        @endforeach
                    </ul>
                @elseif ($question->type === 'short_answer')
                    <p class="mt-2 text-sm text-emerald-700">Jawaban diterima: {{ implode(' | ', $question->accepted_answers_encrypted ?? []) }}</p>
                @endif
                <div class="mt-3 flex gap-3 text-xs">
                    <a href="{{ route('questions.edit', $question) }}" class="font-bold text-link hover:underline">Ubah</a>
                    <form method="POST" action="{{ route('questions.toggle', $question) }}">@csrf<button class="btn-mini">{{ $question->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                </div>
            </article>
        @empty
            <div class="card p-8 text-center text-sm text-slate-500">Belum ada soal.</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $questions->links() }}</div>
</x-layouts.app>
