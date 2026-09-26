@php($editing = $question->exists)
@php($type = $question->type)
@php($optionRows = old('options', $options ?: array_fill(0, 4, ['body' => '', 'correct' => false])))
<x-layouts.app :title="$editing ? 'Ubah Soal' : 'Tambah Soal'" :workspace="$workspace">
    <x-slot:back><a href="{{ route('banks.show', $bank) }}" class="hero-back">&larr; {{ $bank->name }}</a></x-slot:back>
    <x-slot:heading>{{ $editing ? 'Ubah' : 'Tambah' }} Soal — {{ \App\Modules\Assessment\Models\Question::TYPES[$type] }}</x-slot:heading>

    <form method="POST" action="{{ $editing ? route('questions.update', $question) : route('questions.store', $bank) }}" class="card max-w-3xl space-y-4 p-6" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif
        <input type="hidden" name="type" value="{{ $type }}">
        <div>
            <label for="stem_md" class="form-label">Pertanyaan (Markdown)</label>
            <textarea id="stem_md" name="stem_md" rows="5" maxlength="10000" class="form-input">{{ old('stem_md', $question->stem_md) }}</textarea>
            <x-form-error field="stem_md" />
        </div>

        @if (in_array($type, ['single_choice', 'multiple_choice'], true))
            <fieldset class="space-y-2">
                <legend class="form-label">Opsi jawaban — tandai {{ $type === 'single_choice' ? 'satu' : 'semua' }} yang benar</legend>
                @foreach ($optionRows as $index => $option)
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="options[{{ $index }}][correct]" value="1" @checked($option['correct'] ?? false) aria-label="Opsi {{ $index + 1 }} benar">
                        <label for="opt-{{ $index }}" class="sr-only">Opsi {{ $index + 1 }}</label>
                        <input id="opt-{{ $index }}" name="options[{{ $index }}][body]" value="{{ $option['body'] ?? '' }}" maxlength="1000" class="form-input py-1.5" placeholder="Opsi {{ chr(65 + $index) }}">
                    </div>
                @endforeach
                @for ($i = count($optionRows); $i < 6; $i++)
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="options[{{ $i }}][correct]" value="1" aria-label="Opsi {{ $i + 1 }} benar">
                        <label for="opt-{{ $i }}" class="sr-only">Opsi {{ $i + 1 }}</label>
                        <input id="opt-{{ $i }}" name="options[{{ $i }}][body]" maxlength="1000" class="form-input py-1.5" placeholder="Opsi {{ chr(65 + $i) }} (opsional)">
                    </div>
                @endfor
                <x-form-error field="options" />
            </fieldset>
        @elseif ($type === 'true_false')
            @php($currentTrue = collect($options)->first()['correct'] ?? true)
            <fieldset>
                <legend class="form-label">Jawaban benar</legend>
                <label class="mr-4 text-sm"><input type="radio" name="correct_answer" value="true" @checked(old('correct_answer', $currentTrue ? 'true' : 'false') === 'true')> Benar</label>
                <label class="text-sm"><input type="radio" name="correct_answer" value="false" @checked(old('correct_answer', $currentTrue ? 'true' : 'false') === 'false')> Salah</label>
                <x-form-error field="correct_answer" />
            </fieldset>
        @elseif ($type === 'short_answer')
            <div>
                <label for="accepted_answers" class="form-label">Jawaban yang diterima (satu per baris; tidak peka huruf besar & spasi)</label>
                <textarea id="accepted_answers" name="accepted_answers" rows="4" maxlength="2000" class="form-input">{{ old('accepted_answers', $answers) }}</textarea>
                <x-form-error field="accepted_answers" />
            </div>
        @elseif ($type === 'matching')
            @php($pairRows = old('pairs', collect($options)->map(fn ($o) => ['left' => $o['body'] ?? '', 'right' => $o['right'] ?? ''])->all()))
            <fieldset class="space-y-2">
                <legend class="form-label">Pasangan (kiri ↔ kanan). Sisi kanan diacak saat ujian; kredit parsial per pasangan benar.</legend>
                @for ($i = 0; $i < max(4, count($pairRows) + 1) && $i < 10; $i++)
                    <div class="grid grid-cols-2 gap-2">
                        <input name="pairs[{{ $i }}][left]" value="{{ $pairRows[$i]['left'] ?? '' }}" maxlength="500" class="form-input py-1.5" placeholder="Kiri {{ $i + 1 }}" aria-label="Kiri {{ $i + 1 }}">
                        <input name="pairs[{{ $i }}][right]" value="{{ $pairRows[$i]['right'] ?? '' }}" maxlength="500" class="form-input py-1.5" placeholder="Pasangan kanan {{ $i + 1 }}" aria-label="Kanan {{ $i + 1 }}">
                    </div>
                @endfor
                <x-form-error field="pairs" />
            </fieldset>
        @else
            <p class="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">Esai dinilai manual oleh trainer pengampu setelah dikumpulkan.</p>
            @php($rubricRows = old('rubric', $question->rubric ?? []))
            <fieldset class="rounded-lg border border-slate-200 p-4">
                <legend class="px-1 text-xs font-bold text-slate-600">Rubrik penilaian (opsional)</legend>
                <p class="mb-2 text-xs text-slate-500">Skor per kriteria dijumlahkan lalu diskalakan ke poin soal. Kosongkan nama untuk baris yang tidak dipakai.</p>
                @for ($i = 0; $i < max(3, count($rubricRows) + 1) && $i < 8; $i++)
                    <div class="mb-2 grid gap-2 sm:grid-cols-[2fr_1fr_3fr]">
                        <input name="rubric[{{ $i }}][name]" value="{{ $rubricRows[$i]['name'] ?? '' }}" maxlength="120" class="form-input py-1.5" placeholder="Kriteria {{ $i + 1 }}" aria-label="Kriteria {{ $i + 1 }}">
                        <input name="rubric[{{ $i }}][max]" type="number" min="0" max="1000" step="0.5" value="{{ $rubricRows[$i]['max'] ?? '' }}" class="form-input py-1.5" placeholder="Maks." aria-label="Poin maksimal kriteria {{ $i + 1 }}">
                        <input name="rubric[{{ $i }}][description]" value="{{ $rubricRows[$i]['description'] ?? '' }}" maxlength="300" class="form-input py-1.5" placeholder="Deskripsi" aria-label="Deskripsi kriteria {{ $i + 1 }}">
                    </div>
                @endfor
            </fieldset>
        @endif

        <div>
            <label for="explanation_md" class="form-label">Pembahasan (opsional, Markdown)</label>
            <textarea id="explanation_md" name="explanation_md" rows="3" maxlength="10000" class="form-input">{{ old('explanation_md') }}</textarea>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="difficulty" class="form-label">Kesulitan (1–5)</label>
                <input id="difficulty" name="difficulty" type="number" min="1" max="5" value="{{ old('difficulty', $question->difficulty) }}" class="form-input">
            </div>
            <div>
                <label for="points" class="form-label">Poin</label>
                <input id="points" name="points" type="number" min="0.5" max="100" step="0.5" value="{{ old('points', $question->points) }}" class="form-input">
            </div>
            <div>
                <label for="tags" class="form-label">Tag kompetensi</label>
                <input id="tags" name="tags" value="{{ old('tags', implode(', ', $question->competency_tags ?? [])) }}" maxlength="300" class="form-input">
            </div>
        </div>
        <button type="submit" class="btn-primary w-auto">Simpan Soal</button>
    </form>
</x-layouts.app>
