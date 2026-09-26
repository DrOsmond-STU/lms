<x-layouts.app :title="$lesson->title" workspace="participant">
    <x-slot:back><a href="{{ route('learning.classroom', $enrollment) }}" class="hero-back">&larr; {{ $enrollment->program->name }}</a></x-slot:back>
    <x-slot:heading>{{ $lesson->title }}</x-slot:heading>
    <x-slot:meta>
        <span class="badge bg-slate-100 text-slate-600">{{ \App\Modules\Learning\Models\Lesson::TYPES[$lesson->type] }}</span>
        @if ($progress?->status === 'completed')<span class="badge bg-emerald-50 text-emerald-700">Selesai</span>@endif
    </x-slot:meta>

    <div class="card p-6" @if ($enrollment->isActive() && ! $lesson->isTimed()) data-lesson-ping-url="{{ route('learning.ping', [$enrollment, $lesson]) }}" @endif>
        @switch($lesson->type)
            @case('video')
                @if ($mediaUrl)
                    <video src="{{ $mediaUrl }}" controls preload="metadata" playsinline class="w-full rounded-lg bg-black" @unless ($lesson->allow_download) controlsList="nodownload" @endunless
                        @if ($enrollment->isActive()) data-heartbeat-url="{{ route('learning.heartbeat', [$enrollment, $lesson]) }}" @endif></video>
                    <p class="mt-3 text-xs text-slate-500" data-video-status>Tonton minimal {{ (int) round(\App\Modules\Enrollment\Services\ProgressService::videoCompletionRatio() * 100) }}% durasi untuk menyelesaikan lesson ini. Progres tersimpan otomatis.</p>
                @else
                    <p class="text-sm text-slate-500">Video sedang diproses atau belum tersedia.</p>
                @endif
                @break
            @case('audio')
                @if ($mediaUrl)
                    <audio src="{{ $mediaUrl }}" controls preload="metadata" class="w-full" @unless ($lesson->allow_download) controlsList="nodownload" @endunless
                        @if ($enrollment->isActive()) data-heartbeat-url="{{ route('learning.heartbeat', [$enrollment, $lesson]) }}" @endif></audio>
                    <p class="mt-3 text-xs text-slate-500" data-video-status>Dengarkan minimal {{ (int) round(\App\Modules\Enrollment\Services\ProgressService::videoCompletionRatio() * 100) }}% durasi ({{ intdiv((int) $lesson->duration_seconds, 60) }} menit) untuk menyelesaikan lesson ini.</p>
                @else
                    <p class="text-sm text-slate-500">Audio belum tersedia.</p>
                @endif
                @break
            @case('document')
                @if ($mediaUrl)
                    <p class="text-sm text-slate-600">Dokumen <span class="font-bold">{{ $lesson->media->original_filename }}</span> ({{ $lesson->media->humanSize() }}) diunduh melalui tautan aman yang berlaku {{ config('media.signed_url_minutes') }} menit. Buka dengan PowerPoint/Word/Excel atau aplikasi sejenis.</p>
                    <a href="{{ $mediaUrl }}" class="btn-primary mt-3 w-auto">Unduh Dokumen</a>
                @else
                    <p class="text-sm text-slate-500">Dokumen belum tersedia.</p>
                @endif
                @break
            @case('pdf')
                @if ($mediaUrl)
                    <p class="text-sm text-slate-600">Dokumen PDF dibuka di tab baru melalui tautan aman yang berlaku {{ config('media.signed_url_minutes') }} menit.</p>
                    <a href="{{ $mediaUrl }}" target="_blank" rel="noopener" class="btn-primary mt-3 w-auto">Buka PDF</a>
                @else
                    <p class="text-sm text-slate-500">Dokumen belum tersedia.</p>
                @endif
                @break
            @case('text')
                <div class="prose-content text-sm">@include('components.safe-html', ['html' => $lesson->body_html])</div>
                @break
            @case('link')
                <p class="text-sm text-slate-600">Materi ini berada di situs eksternal.</p>
                <a href="{{ $lesson->external_url }}" target="_blank" rel="noopener noreferrer nofollow" class="btn-primary mt-3 w-auto">Buka tautan</a>
                <p class="mt-2 font-mono text-xs break-all text-slate-500">{{ $lesson->external_url }}</p>
                @break
            @case('quiz')
                @if ($lesson->assessment)
                    <p class="text-sm text-slate-600">Kerjakan kuis <span class="font-bold">{{ $lesson->assessment->title }}</span>. Lesson selesai saat Anda lulus kuis.</p>
                    <a href="{{ route('exams.show', [$enrollment, $lesson->assessment]) }}" class="btn-primary mt-3 w-auto">Ke Kuis</a>
                @endif
                @break
        @endswitch

        @if (in_array($lesson->type, \App\Modules\Learning\Models\Lesson::MANUAL_COMPLETE_TYPES, true) && $enrollment->isActive() && $progress?->status !== 'completed')
            <form method="POST" action="{{ route('learning.complete', [$enrollment, $lesson]) }}" class="mt-6 border-t border-slate-100 pt-4">@csrf
                <button class="btn-secondary">Tandai Selesai</button>
            </form>
        @endif
    </div>

    @if ($enrollment->courseClass->discussion_enabled)
        <section class="card mt-6 p-6" aria-labelledby="comments-heading">
            <h2 id="comments-heading" class="font-bold text-slate-800">Komentar materi <span class="text-sm font-normal text-slate-500">({{ $comments->count() }})</span></h2>
            <x-form-error field="body" />
            <div class="mt-3 divide-y divide-slate-100">
                @forelse ($comments as $comment)
                    <div class="py-3">
                        <p class="text-xs text-slate-500"><span class="font-bold text-slate-800">{{ $comment->author->name }}</span> · {{ $comment->created_at->timezone(display_tz())->translatedFormat('d M Y H:i') }}</p>
                        <div class="prose-content mt-1 text-sm">@include('components.safe-html', ['html' => $comment->body_html])</div>
                        <a href="{{ route('discussion.show', [$enrollment->course_class_id, $comment]) }}" class="mt-1 inline-block text-xs font-bold text-link hover:underline">{{ $comment->posts_count }} balasan · buka</a>
                    </div>
                @empty
                    <p class="py-3 text-sm text-slate-500">Belum ada komentar. Ajukan pertanyaan atau bagikan catatan Anda tentang materi ini.</p>
                @endforelse
            </div>
            @can('discussion.post')
                <form method="POST" action="{{ route('discussion.comment', [$enrollment->course_class_id, $lesson]) }}" class="mt-4 space-y-2 border-t border-slate-100 pt-4" novalidate>@csrf
                    <label for="comment-body" class="form-label">Tulis komentar (Markdown)</label>
                    <textarea id="comment-body" name="body" rows="3" maxlength="5000" required class="form-input"></textarea>
                    <button class="btn-secondary">Kirim Komentar</button>
                </form>
            @endcan
        </section>
    @endif

    <nav class="mt-5 flex justify-between text-sm font-bold" aria-label="Navigasi lesson">
        @if ($previousId)<a href="{{ route('learning.lesson', [$enrollment, $previousId]) }}" class="text-link hover:underline">&larr; Sebelumnya</a>@else<span></span>@endif
        @if ($nextId)<a href="{{ route('learning.lesson', [$enrollment, $nextId]) }}" class="text-link hover:underline">Berikutnya &rarr;</a>@endif
    </nav>
</x-layouts.app>
