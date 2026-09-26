<div class="card flex flex-col p-0">
    <div class="max-h-[50vh] min-h-40 space-y-3 overflow-y-auto p-5" aria-live="polite" aria-label="Percakapan tutor AI">
        @forelse ($messages as $message)
            <div @class(['flex flex-col', 'items-end' => $message['role'] === 'user'])>
                <span class="text-[11px] text-slate-500">{{ $message['role'] === 'user' ? 'Anda' : 'Tutor AI' }}</span>
                <div @class(['mt-0.5 max-w-[90%] rounded-2xl px-3 py-2 text-sm', 'bg-brand-600 text-white whitespace-pre-line' => $message['role'] === 'user', 'bg-slate-100 text-slate-800 prose-content' => $message['role'] !== 'user'])>@if ($message['role'] === 'user'){{ $message['content'] }}@else @include('components.safe-html', ['html' => \App\Support\Content\RichText::toHtml($message['content'])]) @endif</div>
            </div>
        @empty
            <p class="py-6 text-center text-sm text-slate-500">Tanyakan apa pun tentang materi ini — tutor AI menjawab berdasarkan isi materi dan tidak memberikan jawaban ujian.</p>
        @endforelse
        <div wire:loading wire:target="ask" class="text-xs text-slate-500">Tutor AI sedang menulis…</div>
    </div>
    <form wire:submit="ask" class="flex items-end gap-2 border-t border-slate-200 p-3">
        <label for="tutor-question" class="sr-only">Pertanyaan</label>
        <textarea id="tutor-question" wire:model="question" rows="2" maxlength="1500" class="form-input flex-1 py-2" placeholder="Contoh: Jelaskan perbedaan … dengan contoh sederhana"></textarea>
        <button type="submit" class="btn-primary w-auto" wire:loading.attr="disabled">Tanya</button>
    </form>
    @if ($error)<p class="px-4 pb-2 text-xs text-rose-700">{{ $error }}</p>@endif
    @error('question')<p class="px-4 pb-2 text-xs text-rose-700">{{ $message }}</p>@enderror
    <p class="px-4 pb-3 text-[11px] text-slate-500">Jawaban AI dapat keliru — verifikasi dengan materi dan trainer. Sisa kuota hari ini: {{ $remaining }}.</p>
</div>
