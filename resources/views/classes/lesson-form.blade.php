@php($editing = $lesson->exists)
@php($type = $lesson->type)
<x-layouts.app :title="$editing ? 'Ubah Lesson' : 'Tambah Lesson'" :workspace="$workspace">
    <a href="{{ route('classes.manage', $class) }}" class="text-sm font-bold text-brand-700 hover:underline">&larr; Kelola {{ $class->batch_name }}</a>
    <h1 class="mt-2 text-xl font-extrabold text-slate-800">{{ $editing ? 'Ubah' : 'Tambah' }} Lesson {{ \App\Modules\Learning\Models\Lesson::TYPES[$type] }}</h1>
    <p class="mt-0.5 mb-6 text-sm text-slate-600">Bab: {{ $chapter->title }}</p>

    <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('content.lessons.update', [$class, $lesson]) : route('content.lessons.store', [$class, $chapter]) }}" class="card max-w-3xl space-y-4 p-6" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif
        <input type="hidden" name="type" value="{{ $type }}">
        <div>
            <label for="title" class="form-label">Judul</label>
            <input id="title" name="title" type="text" value="{{ old('title', $lesson->title) }}" required maxlength="200" class="form-input">
            <x-form-error field="title" />
        </div>

        @if (in_array($type, ['video', 'pdf'], true))
            <div>
                <label for="file" class="form-label">Berkas {{ $type === 'video' ? 'video (MP4/MOV/WebM)' : 'PDF (maks. 50 MB)' }} {{ $editing ? '— kosongkan bila tidak diganti' : '' }}</label>
                <input id="file" name="file" type="file" accept="{{ $type === 'video' ? 'video/mp4,video/quicktime,video/webm' : 'application/pdf' }}" @required(! $editing) class="form-input">
                @if ($editing && $lesson->media)
                    <p class="mt-1 text-xs text-slate-500">Saat ini: {{ $lesson->media->original_filename }} ({{ $lesson->media->humanSize() }})</p>
                @endif
                <x-form-error field="file" />
            </div>
            @if ($type === 'video')
                <div class="grid max-w-sm grid-cols-2 gap-3">
                    <div>
                        <label for="duration_minutes" class="form-label">Durasi (menit)</label>
                        <input id="duration_minutes" name="duration_minutes" type="number" min="0" max="600" value="{{ old('duration_minutes', intdiv((int) $lesson->duration_seconds, 60)) }}" class="form-input">
                    </div>
                    <div>
                        <label for="duration_seconds_part" class="form-label">Detik</label>
                        <input id="duration_seconds_part" name="duration_seconds_part" type="number" min="0" max="59" value="{{ old('duration_seconds_part', (int) $lesson->duration_seconds % 60) }}" class="form-input">
                    </div>
                </div>
                <x-form-error field="duration_minutes" />
            @endif
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allow_download" value="1" @checked(old('allow_download', $lesson->allow_download))> Izinkan peserta mengunduh berkas</label>
        @elseif ($type === 'text')
            <div>
                <label for="body_md" class="form-label">Isi (Markdown)</label>
                <textarea id="body_md" name="body_md" rows="16" maxlength="50000" class="form-input font-mono text-xs">{{ old('body_md', $lesson->body_md) }}</textarea>
                <p class="mt-1 text-xs text-slate-500">HTML tidak dirender; tautan hanya https.</p>
                <x-form-error field="body_md" />
            </div>
        @elseif ($type === 'link')
            <div>
                <label for="external_url" class="form-label">Tautan (https)</label>
                <input id="external_url" name="external_url" type="url" value="{{ old('external_url', $lesson->external_url) }}" maxlength="500" class="form-input">
                <p class="mt-1 text-xs text-slate-500">Domain yang diizinkan: {{ implode(', ', config('media.link_allowlist')) }}.</p>
                <x-form-error field="external_url" />
            </div>
        @elseif ($type === 'quiz')
            <div>
                <label for="assessment_id" class="form-label">Kuis</label>
                <select id="assessment_id" name="assessment_id" class="form-select">
                    @foreach ($quizzes as $quiz)
                        <option value="{{ $quiz->id }}" @selected(old('assessment_id', $lesson->assessment_id) === $quiz->id)>{{ $quiz->title }}</option>
                    @endforeach
                </select>
                @if ($quizzes->isEmpty())
                    <p class="mt-1 text-xs text-amber-700">Belum ada kuis. Buat kuis di tab Asesmen terlebih dahulu.</p>
                @endif
                <x-form-error field="assessment_id" />
            </div>
        @endif

        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_required" value="1" @checked(old('is_required', $lesson->is_required))> Wajib diselesaikan untuk lulus</label>
        <button type="submit" class="btn-primary w-auto">Simpan</button>
    </form>
</x-layouts.app>
