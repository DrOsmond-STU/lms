@php($editing = $assessment->exists)
@php($rules = $assessment->selection_rules ?? [])
<x-layouts.app :title="$editing ? 'Ubah Asesmen' : 'Tambah Asesmen'" :workspace="$workspace">
    <a href="{{ route('classes.assessments', $class) }}" class="text-sm font-bold text-brand-700 hover:underline">&larr; Asesmen {{ $class->batch_name }}</a>
    <h1 class="mt-2 mb-6 text-xl font-extrabold text-slate-800">{{ $editing ? 'Ubah' : 'Tambah' }} {{ \App\Modules\Assessment\Models\Assessment::KINDS[$assessment->kind] }}</h1>

    @if ($banks->isEmpty())
        <div class="card p-6 text-sm">Program ini belum memiliki bank soal. <a href="{{ route('banks.index', $class->program) }}" class="font-bold text-brand-700 hover:underline">Buat bank soal</a> terlebih dahulu.</div>
    @else
        <form method="POST" action="{{ $editing ? route('assessments.update', [$class, $assessment]) : route('assessments.store', $class) }}" class="card max-w-3xl space-y-4 p-6" novalidate>
            @csrf
            @if ($editing) @method('PUT') @endif
            <input type="hidden" name="kind" value="{{ $assessment->kind }}">
            <x-form-error field="kind" />
            <div>
                <label for="title" class="form-label">Judul</label>
                <input id="title" name="title" value="{{ old('title', $assessment->title) }}" required maxlength="200" class="form-input">
                <x-form-error field="title" />
            </div>
            <div>
                <label for="question_bank_id" class="form-label">Bank soal</label>
                <select id="question_bank_id" name="question_bank_id" class="form-select">
                    @foreach ($banks as $bank)
                        <option value="{{ $bank->id }}" @selected(old('question_bank_id', $assessment->question_bank_id) === $bank->id)>{{ $bank->name }} ({{ $bank->active_questions_count }} soal aktif)</option>
                    @endforeach
                </select>
                <x-form-error field="question_bank_id" />
            </div>
            <div class="grid gap-4 sm:grid-cols-4">
                <div><label for="question_count" class="form-label">Jumlah soal</label><input id="question_count" name="question_count" type="number" min="1" max="200" value="{{ old('question_count', $assessment->question_count) }}" class="form-input"><x-form-error field="question_count" /></div>
                <div><label for="duration_minutes" class="form-label">Durasi (menit)</label><input id="duration_minutes" name="duration_minutes" type="number" min="1" max="600" value="{{ old('duration_minutes', $assessment->duration_minutes) }}" class="form-input"></div>
                <div><label for="max_attempts" class="form-label">Kesempatan</label><input id="max_attempts" name="max_attempts" type="number" min="1" max="20" value="{{ old('max_attempts', $assessment->max_attempts) }}" class="form-input"></div>
                <div><label for="cooldown_minutes" class="form-label">Jeda (menit)</label><input id="cooldown_minutes" name="cooldown_minutes" type="number" min="0" max="10080" value="{{ old('cooldown_minutes', $assessment->cooldown_minutes) }}" class="form-input"></div>
            </div>
            <fieldset>
                <legend class="form-label">Ambil acak per tingkat kesulitan (opsional; sisanya acak dari seluruh bank)</legend>
                <div class="grid grid-cols-5 gap-2">
                    @for ($d = 1; $d <= 5; $d++)
                        <div><label for="dr{{ $d }}" class="text-xs text-slate-500">Kesulitan {{ $d }}</label><input id="dr{{ $d }}" name="difficulty_rules[{{ $d }}]" type="number" min="0" max="200" value="{{ old('difficulty_rules.'.$d, $rules[$d] ?? $rules[(string) $d] ?? '') }}" class="form-input py-1.5"></div>
                    @endfor
                </div>
                <x-form-error field="difficulty_rules" />
            </fieldset>
            <div class="grid gap-4 sm:grid-cols-3">
                <div><label for="opens_at" class="form-label">Dibuka (opsional)</label><input id="opens_at" name="opens_at" type="datetime-local" value="{{ old('opens_at', $assessment->opens_at?->format('Y-m-d\TH:i')) }}" class="form-input"></div>
                <div><label for="closes_at" class="form-label">Ditutup (opsional)</label><input id="closes_at" name="closes_at" type="datetime-local" value="{{ old('closes_at', $assessment->closes_at?->format('Y-m-d\TH:i')) }}" class="form-input"><x-form-error field="closes_at" /></div>
                <div><label for="passing_score" class="form-label">Skor minimal</label><input id="passing_score" name="passing_score" type="number" min="0" max="100" step="0.01" value="{{ old('passing_score', $assessment->passing_score) }}" class="form-input"></div>
            </div>
            <div>
                <label for="review_policy" class="form-label">Tampilkan pembahasan & kunci</label>
                <select id="review_policy" name="review_policy" class="form-select">
                    @foreach (\App\Modules\Assessment\Models\Assessment::REVIEW_POLICIES as $value => $label)
                        <option value="{{ $value }}" @selected(old('review_policy', $assessment->review_policy) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-2 text-sm sm:grid-cols-2">
                <label class="flex items-center gap-2"><input type="checkbox" name="shuffle_questions" value="1" @checked(old('shuffle_questions', $assessment->shuffle_questions))> Acak urutan soal</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="shuffle_options" value="1" @checked(old('shuffle_options', $assessment->shuffle_options))> Acak urutan opsi</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="is_required" value="1" @checked(old('is_required', $assessment->is_required))> Wajib lulus (syarat kelulusan)</label>
                @if ($assessment->kind === 'final_exam')
                    <label class="flex items-center gap-2"><input type="checkbox" name="requires_prerequisites" value="1" @checked(old('requires_prerequisites', $assessment->requires_prerequisites))> Terkunci sampai materi & kuis wajib selesai</label>
                @endif
            </div>
            <button type="submit" class="btn-primary w-auto">Simpan</button>
        </form>
    @endif
</x-layouts.app>
