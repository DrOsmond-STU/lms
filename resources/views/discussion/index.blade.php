<x-layouts.app :title="($kind === 'question' ? 'Tanya Jawab ' : 'Forum ').$class->batch_name" :workspace="$member['workspace']">
    <x-slot:back><a href="{{ app(\App\Modules\Discussion\Services\ClassMembership::class)->backUrl($member['workspace'], $class, $member['enrollment']) }}" class="hero-back">&larr; {{ $class->program->name }}</a></x-slot:back>
    <x-slot:heading>{{ $kind === 'question' ? 'Tanya Jawab' : 'Forum Diskusi' }} — {{ $class->batch_name }}</x-slot:heading>
    <x-slot:subtitle>{{ $kind === 'question' ? 'Ajukan pertanyaan; trainer dan sesama peserta menjawab. Jawaban terbaik ditandai.' : 'Diskusikan materi dengan peserta lain dan trainer.' }}{{ $openReports > 0 ? ' · '.$openReports.' laporan konten terbuka' : '' }}</x-slot:subtitle>
    @include('discussion._nav', ['tab' => $kind])
    <x-form-error field="body" />

    @unless ($enabled)
        <p class="mb-5 rounded-lg bg-amber-50 p-4 text-sm text-amber-800">Forum diskusi kelas ini sedang dinonaktifkan oleh admin.</p>
    @endunless

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-3 xl:col-span-2">
            @forelse ($threads as $thread)
                <a href="{{ route('discussion.show', [$class, $thread]) }}" @class(['card block p-4 hover:border-brand-300', 'opacity-60' => $thread->is_hidden])>
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        @if ($thread->is_pinned)<span class="badge bg-amber-50 text-amber-800">Disematkan</span>@endif
                        @if ($thread->is_locked)<span class="badge bg-slate-100 text-slate-600">Terkunci</span>@endif
                        @if ($thread->kind === 'question')<span @class(['badge', 'bg-emerald-50 text-emerald-700' => $thread->is_resolved, 'bg-brand-50 text-link' => ! $thread->is_resolved])>{{ $thread->is_resolved ? 'Terjawab' : 'Belum terjawab' }}</span>@endif
                        @if ($thread->is_hidden)<span class="badge bg-rose-50 text-rose-700">Disembunyikan</span>@endif
                    </div>
                    <h2 class="mt-1 font-bold text-slate-800">{{ $thread->title }}</h2>
                    <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ \Illuminate\Support\Str::limit($thread->body, 180) }}</p>
                    <p class="mt-2 text-xs text-slate-500">{{ $thread->author->name }} · {{ $thread->created_at->timezone(display_tz())->translatedFormat('d M Y H:i') }} · {{ $thread->replies_count }} balasan</p>
                </a>
            @empty
                <div class="card p-8 text-center text-sm text-slate-500">Belum ada {{ $kind === 'question' ? 'pertanyaan' : 'utas diskusi' }}.</div>
            @endforelse
            <div>{{ $threads->links() }}</div>
        </div>
        <aside>
            @if ($enabled)
                <section class="card p-5" aria-labelledby="new-thread">
                    <h2 id="new-thread" class="font-bold text-slate-800">{{ $kind === 'question' ? 'Ajukan Pertanyaan' : 'Utas Baru' }}</h2>
                    <form method="POST" action="{{ route('discussion.store', $class) }}" class="mt-3 space-y-3" novalidate>@csrf
                        <input type="hidden" name="kind" value="{{ $kind }}">
                        <div><label for="title" class="form-label">Judul</label><input id="title" name="title" value="{{ old('title') }}" required minlength="3" maxlength="200" class="form-input"><x-form-error field="title" /></div>
                        <div><label for="body" class="form-label">Isi (Markdown)</label><textarea id="body" name="body" rows="6" maxlength="20000" required class="form-input">{{ old('body') }}</textarea></div>
                        <button class="btn-primary w-full">Kirim</button>
                    </form>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.app>
