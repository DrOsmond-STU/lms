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

    <x-form-error field="bank" />
    <x-form-error field="question" />
    <details class="card mb-5 p-4">
        <summary class="cursor-pointer text-sm font-semibold text-slate-800">Kelola bank soal (ubah nama / hapus)</summary>
        <div class="mt-3 flex flex-wrap items-end gap-3">
            <form method="POST" action="{{ route('banks.update', $bank) }}" class="flex flex-wrap items-end gap-2">@csrf @method('PUT')
                <div><label for="bank-name" class="form-label">Nama bank soal</label><input id="bank-name" name="name" value="{{ old('name', $bank->name) }}" minlength="3" maxlength="200" required class="form-input"></div>
                <button type="submit" class="btn-secondary">Simpan nama</button>
            </form>
            <form method="POST" action="{{ route('banks.destroy', $bank) }}" data-confirm="Hapus bank soal ini beserta semua soalnya? Hanya bisa bila belum dipakai asesmen.">@csrf @method('DELETE')
                <button type="submit" class="btn-danger">Hapus bank soal</button>
            </form>
        </div>
        <x-form-error field="name" />
    </details>

    @if (\App\Modules\Ai\Services\ClaudeClient::configured() && auth()->user()->can('ai.author'))
        <details class="card mb-5 p-4">
            <summary class="cursor-pointer text-sm font-semibold text-slate-800">Buat soal dengan AI (draf nonaktif untuk ditinjau)</summary>
            <form method="POST" action="{{ route('ai.questions', $bank) }}" class="mt-3 grid gap-3 sm:grid-cols-4">@csrf
                <div class="sm:col-span-4"><label for="ai-topic" class="form-label">Materi / topik (tempel ringkasan materi bila ada)</label><textarea id="ai-topic" name="topic" rows="4" required minlength="10" maxlength="4000" class="form-input" placeholder="Contoh: Prinsip CIA triad dalam keamanan informasi: kerahasiaan, integritas, ketersediaan; contoh ancaman masing-masing…">{{ old('topic') }}</textarea><x-form-error field="topic" /></div>
                <div><label for="ai-count" class="form-label">Jumlah</label><input id="ai-count" name="count" type="number" min="1" max="10" value="{{ old('count', 5) }}" class="form-input"></div>
                <div><label for="ai-type" class="form-label">Tipe</label><select id="ai-type" name="type" class="form-select">@foreach (['single_choice' => 'Pilihan ganda (1 benar)', 'multiple_choice' => 'Pilihan ganda (multi)', 'true_false' => 'Benar / Salah', 'essay' => 'Esai + rubrik'] as $v => $l)<option value="{{ $v }}" @selected(old('type') === $v)>{{ $l }}</option>@endforeach</select></div>
                <div><label for="ai-diff" class="form-label">Kesulitan (1–5)</label><input id="ai-diff" name="difficulty" type="number" min="1" max="5" value="{{ old('difficulty', 3) }}" class="form-input"></div>
                <div class="flex items-end"><button class="btn-primary w-full">Buat soal</button></div>
            </form>
            <p class="mt-2 text-xs text-slate-500">Soal hasil AI diberi tag <span class="font-mono">draf-ai</span> dan nonaktif sampai Anda meninjau dan mengaktifkannya. Kunci jawaban tetap rahasia di platform.</p>
        </details>
    @endif

    <div class="space-y-3">
        @forelse ($questions as $question)
            <article @class(['card p-5', 'opacity-60' => ! $question->is_active])>
                <div class="mb-2 flex flex-wrap items-center gap-2 text-xs">
                    <span class="badge bg-brand-50 text-link">{{ \App\Modules\Assessment\Models\Question::TYPES[$question->type] }}</span>
                    <span class="badge bg-slate-100 text-slate-600">Kesulitan {{ $question->difficulty }}</span>
                    <span class="badge bg-slate-100 text-slate-600">{{ fmt_score($question->points) }} poin</span>
                    <span class="badge bg-slate-100 text-slate-600">v{{ $question->version }}</span>
                    @unless ($question->is_active)<span class="badge bg-slate-200 text-slate-700">Nonaktif</span>@endunless
                    @foreach ($question->competency_tags as $tag)<span class="text-slate-500">#{{ $tag }}</span>@endforeach
                </div>
                <div class="prose-content text-sm">@include('components.safe-html', ['html' => $question->stem_html])</div>
                @if ($question->options->isNotEmpty())
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach ($question->options as $option)
                            <li @class(['font-bold text-emerald-700' => $option->is_correct])>{{ $option->is_correct ? '✓' : '○' }} @include('components.safe-html', ['html' => $option->body_html])@if ($question->type === 'matching') <span class="font-normal text-slate-600">↔ {{ $option->match_text }}</span>@endif</li>
                        @endforeach
                    </ul>
                @elseif ($question->type === 'short_answer')
                    <p class="mt-2 text-sm text-emerald-700">Jawaban diterima: {{ implode(' | ', $question->accepted_answers_encrypted ?? []) }}</p>
                @endif
                <div class="mt-3 flex gap-3 text-xs">
                    <a href="{{ route('questions.edit', $question) }}" class="font-bold text-link hover:underline">Ubah</a>
                    <form method="POST" action="{{ route('questions.toggle', $question) }}">@csrf<button class="btn-mini">{{ $question->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                    <form method="POST" action="{{ route('questions.destroy', $question) }}" data-confirm="Hapus soal ini? Soal yang sudah muncul di ujian tidak dapat dihapus.">@csrf @method('DELETE')<button class="btn-mini-danger">Hapus</button></form>
                </div>
            </article>
        @empty
            <div class="card p-8 text-center text-sm text-slate-500">Belum ada soal.</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $questions->links() }}</div>
</x-layouts.app>
