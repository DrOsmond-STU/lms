@php($editing = $lesson->exists)
@php($type = $lesson->type)
@php($isMedia = in_array($type, \App\Modules\Learning\Models\Lesson::MEDIA_TYPES, true))
@php($isTimed = in_array($type, \App\Modules\Learning\Models\Lesson::TIMED_TYPES, true))
<x-layouts.app :title="$editing ? 'Ubah Lesson' : 'Tambah Lesson'" :workspace="$workspace">
    <x-slot:back><a href="{{ route('classes.manage', $class) }}" class="hero-back">&larr; Kelola {{ $class->batch_name }}</a></x-slot:back>
    <x-slot:heading>{{ $editing ? 'Ubah' : 'Tambah' }} Lesson {{ \App\Modules\Learning\Models\Lesson::TYPES[$type] }}</x-slot:heading>
    <x-slot:subtitle>Bab: {{ $chapter->title }}</x-slot:subtitle>

    <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('content.lessons.update', [$class, $lesson]) : route('content.lessons.store', [$class, $chapter]) }}" class="card max-w-3xl space-y-4 p-6" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif
        <input type="hidden" name="type" value="{{ $type }}">
        <div>
            <label for="title" class="form-label">Judul</label>
            <input id="title" name="title" type="text" value="{{ old('title', $lesson->title) }}" required maxlength="200" class="form-input">
            <x-form-error field="title" />
        </div>

        @if ($isMedia)
            @php($fileHints = ['video' => ['Berkas video (MP4/MOV/WebM)', 'video/mp4,video/quicktime,video/webm'], 'audio' => ['Berkas audio (MP3/M4A/OGG/WAV/FLAC, maks. '.config('media.types.audio.max_mb').' MB)', 'audio/*'], 'pdf' => ['Berkas PDF (maks. 50 MB)', 'application/pdf'], 'document' => ['Dokumen PowerPoint/Word/Excel/OpenDocument (maks. 50 MB)', '.pptx,.ppt,.docx,.doc,.xlsx,.xls,.odp,.odt,.ods']])
            <div>
                <label for="file" class="form-label">{{ $fileHints[$type][0] }} {{ $editing ? '— kosongkan bila tidak diganti' : '' }}</label>
                <input id="file" name="file" type="file" accept="{{ $fileHints[$type][1] }}" @required(! $editing) class="form-input">
                @if ($editing && $lesson->media)
                    <p class="mt-1 text-xs text-slate-500">Saat ini: {{ $lesson->media->original_filename }} ({{ $lesson->media->humanSize() }})</p>
                @endif
                <x-form-error field="file" />
            </div>
            @if ($isTimed)
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
            @if ($type === 'document')
                <p class="text-xs text-slate-500">Dokumen kantor tidak dirender di peramban; peserta mengunduhnya lewat tautan aman lalu menandai selesai.</p>
            @else
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allow_download" value="1" @checked(old('allow_download', $lesson->allow_download))> Izinkan peserta mengunduh berkas</label>
            @endif
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

        <fieldset class="rounded-lg border border-slate-200 p-4">
            <legend class="px-1 text-xs font-bold text-slate-600">Ketersediaan (drip content &amp; prasyarat)</legend>
            <p class="mb-3 text-xs text-slate-500">Kosongkan bila lesson tersedia sejak awal. Urutan wajib seluruh kelas diatur di Pengaturan Kelas.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="unlock_at" class="form-label">Terbuka mulai tanggal</label>
                    <input id="unlock_at" name="unlock_at" type="datetime-local" value="{{ old('unlock_at', $lesson->unlock_at?->timezone(display_tz())->format('Y-m-d\TH:i')) }}" class="form-input">
                    <x-form-error field="unlock_at" />
                </div>
                <div>
                    <label for="unlock_after_days" class="form-label">Terbuka N hari setelah peserta mendaftar</label>
                    <input id="unlock_after_days" name="unlock_after_days" type="number" min="0" max="3650" value="{{ old('unlock_after_days', $lesson->unlock_after_days) }}" class="form-input" placeholder="mis. 7">
                    <x-form-error field="unlock_after_days" />
                </div>
            </div>
            <div class="mt-3">
                <label for="prerequisite_lesson_id" class="form-label">Prasyarat: harus menyelesaikan lesson</label>
                <select id="prerequisite_lesson_id" name="prerequisite_lesson_id" class="form-select">
                    <option value="">— Tidak ada —</option>
                    @foreach ($siblings as $sibling)
                        <option value="{{ $sibling->id }}" @selected(old('prerequisite_lesson_id', $lesson->prerequisite_lesson_id) === $sibling->id)>{{ $sibling->title }} ({{ \App\Modules\Learning\Models\Lesson::TYPES[$sibling->type] }})</option>
                    @endforeach
                </select>
                <x-form-error field="prerequisite_lesson_id" />
            </div>
        </fieldset>

        <button type="submit" class="btn-primary w-auto">Simpan</button>
    </form>
</x-layouts.app>
