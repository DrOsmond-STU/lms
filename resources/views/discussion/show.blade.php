<x-layouts.app :title="$thread->title ?? 'Komentar materi'" :workspace="$member['workspace']">
    <x-slot:back><a href="{{ $thread->kind === 'comment' && $thread->lesson ? ($member['enrollment'] ? route('learning.lesson', [$member['enrollment'], $thread->lesson_id]) : route('classes.manage', $class)) : route('discussion.index', [$class, 'jenis' => $thread->kind === 'question' ? 'question' : 'discussion']) }}" class="hero-back">&larr; {{ $thread->kind === 'comment' ? ($thread->lesson->title ?? 'Materi') : ($thread->kind === 'question' ? 'Tanya Jawab' : 'Forum Diskusi') }}</a></x-slot:back>
    <x-slot:heading>{{ $thread->title ?? 'Komentar pada '.($thread->lesson->title ?? 'materi') }}</x-slot:heading>
    <x-slot:meta>
        <span class="badge bg-slate-100 text-slate-700">{{ $thread->kindLabel() }}</span>
        @if ($thread->is_pinned)<span class="badge bg-amber-50 text-amber-800">Disematkan</span>@endif
        @if ($thread->is_locked)<span class="badge bg-slate-100 text-slate-600">Terkunci</span>@endif
        @if ($thread->kind === 'question')<span @class(['badge', 'bg-emerald-50 text-emerald-700' => $thread->is_resolved, 'bg-brand-50 text-link' => ! $thread->is_resolved])>{{ $thread->is_resolved ? 'Terjawab' : 'Belum terjawab' }}</span>@endif
        @if ($thread->is_hidden)<span class="badge bg-rose-50 text-rose-700">Disembunyikan</span>@endif
    </x-slot:meta>
    <x-slot:subtitle>{{ $thread->author->name }} · {{ $thread->created_at->timezone(display_tz())->translatedFormat('d M Y H:i') }} {{ tz_label() }}</x-slot:subtitle>
    <x-form-error field="body" />
    <x-form-error field="reason" />

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-4 xl:col-span-2">
            <article class="card p-5">
                <div class="prose-content text-sm">@include('components.safe-html', ['html' => $thread->body_html])</div>
                @if ($member['role'] === 'participant' && $thread->author_id !== auth()->id())
                    <details class="mt-3 text-xs"><summary class="cursor-pointer text-slate-500 hover:underline">Laporkan utas</summary>
                        <form method="POST" action="{{ route('discussion.report', [$class, $thread]) }}" class="mt-2 flex gap-2">@csrf<input name="reason" minlength="5" maxlength="500" required placeholder="Alasan" class="form-input min-h-0 py-1 text-xs" aria-label="Alasan laporan"><button class="btn-secondary min-h-0 px-3 py-1 text-xs">Kirim</button></form>
                    </details>
                @endif
            </article>

            @foreach ($posts as $post)
                <article @class(['card p-5', 'border-emerald-300 bg-emerald-50/40' => $post->is_answer, 'opacity-60' => $post->is_hidden])>
                    <p class="text-xs text-slate-500"><span class="font-bold text-slate-800">{{ $post->author->name }}</span> · {{ $post->created_at->timezone(display_tz())->translatedFormat('d M Y H:i') }}
                        @if ($post->is_answer)<span class="badge ml-1 bg-emerald-100 text-emerald-800">Jawaban</span>@endif
                        @if ($post->is_hidden)<span class="badge ml-1 bg-rose-50 text-rose-700">Disembunyikan{{ $post->hidden_reason ? ': '.$post->hidden_reason : '' }}</span>@endif
                    </p>
                    <div class="prose-content mt-2 text-sm">@include('components.safe-html', ['html' => $post->body_html])</div>
                    <div class="mt-3 flex flex-wrap gap-3 text-xs">
                        @if ($thread->kind === 'question' && ! $post->is_answer && ($member['role'] === 'moderator' || $thread->author_id === auth()->id()))
                            <form method="POST" action="{{ route('discussion.answer', [$class, $thread, $post]) }}">@csrf<button class="font-bold text-emerald-700 hover:underline">Tandai sebagai jawaban</button></form>
                        @endif
                        @if ($member['role'] === 'moderator')
                            <form method="POST" action="{{ route('discussion.moderate', [$class, $thread]) }}" class="flex items-center gap-1">@csrf<input type="hidden" name="post_id" value="{{ $post->id }}"><input type="hidden" name="action" value="{{ $post->is_hidden ? 'unhide_post' : 'hide_post' }}">@unless ($post->is_hidden)<input name="reason" maxlength="300" placeholder="Alasan" class="form-input min-h-0 w-32 py-1 text-xs" aria-label="Alasan">@endunless<button class="font-bold text-rose-700 hover:underline">{{ $post->is_hidden ? 'Tampilkan' : 'Sembunyikan' }}</button></form>
                        @elseif ($member['role'] === 'participant' && $post->author_id !== auth()->id())
                            <details><summary class="cursor-pointer text-slate-500 hover:underline">Laporkan</summary>
                                <form method="POST" action="{{ route('discussion.report', [$class, $thread]) }}" class="mt-1 flex gap-1">@csrf<input type="hidden" name="post_id" value="{{ $post->id }}"><input name="reason" minlength="5" maxlength="500" required placeholder="Alasan" class="form-input min-h-0 py-1 text-xs" aria-label="Alasan laporan"><button class="btn-secondary min-h-0 px-2 py-1 text-xs">Kirim</button></form>
                            </details>
                        @endif
                    </div>
                </article>
            @endforeach

            @if (! $thread->is_locked || $member['role'] === 'moderator')
                <form method="POST" action="{{ route('discussion.reply', [$class, $thread]) }}" class="card space-y-3 p-5" novalidate>@csrf
                    <label for="reply-body" class="form-label">Balas (Markdown)</label>
                    <textarea id="reply-body" name="body" rows="5" maxlength="20000" required class="form-input">{{ old('body') }}</textarea>
                    <button class="btn-primary w-auto">Kirim Balasan</button>
                </form>
            @else
                <p class="card p-4 text-sm text-slate-500">Utas ini dikunci; balasan baru tidak diterima.</p>
            @endif
        </div>
        <aside class="space-y-4">
            @if ($member['role'] === 'moderator')
                <section class="card p-5">
                    <h2 class="font-bold text-slate-800">Moderasi</h2>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ([['pin', 'unpin', $thread->is_pinned, 'Sematkan', 'Lepas sematan'], ['lock', 'unlock', $thread->is_locked, 'Kunci', 'Buka kunci'], ['hide', 'unhide', $thread->is_hidden, 'Sembunyikan', 'Tampilkan']] as [$on, $off, $state, $labelOn, $labelOff])
                            <form method="POST" action="{{ route('discussion.moderate', [$class, $thread]) }}">@csrf<input type="hidden" name="action" value="{{ $state ? $off : $on }}"><button class="btn-secondary">{{ $state ? $labelOff : $labelOn }}</button></form>
                        @endforeach
                    </div>
                </section>
            @endif
            <section class="card p-5 text-sm text-slate-600">
                <h2 class="font-bold text-slate-800">Etika</h2>
                <p class="mt-2">Hormati sesama peserta, fokus pada materi, dan jangan membagikan data pribadi atau kunci jawaban ujian.</p>
            </section>
        </aside>
    </div>
</x-layouts.app>
