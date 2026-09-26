<div wire:poll.10s class="card flex flex-col p-0">
    <div class="max-h-[60vh] min-h-64 space-y-3 overflow-y-auto p-5" aria-live="polite" aria-label="Pesan obrolan">
        @forelse ($messages as $message)
            <div @class(['flex flex-col', 'items-end' => $message->user_id === $me])>
                <span class="text-[11px] text-slate-500">{{ $message->user_id === $me ? 'Anda' : $message->user->name }} · {{ $message->created_at->timezone(display_tz())->format('d/m H:i') }}
                    @if ($moderator && $message->user_id !== $me)<button type="button" wire:click="hide('{{ $message->id }}')" class="ml-1 text-rose-700 hover:underline">sembunyikan</button>@endif
                </span>
                <span @class(['mt-0.5 max-w-[85%] rounded-2xl px-3 py-2 text-sm whitespace-pre-line', 'bg-brand-600 text-white' => $message->user_id === $me, 'bg-slate-100 text-slate-800' => $message->user_id !== $me])>{{ $message->body }}</span>
            </div>
        @empty
            <p class="py-8 text-center text-sm text-slate-500">Belum ada pesan. Mulai percakapan!</p>
        @endforelse
    </div>
    <form wire:submit="send" class="flex items-end gap-2 border-t border-slate-200 p-3">
        <label for="chat-body" class="sr-only">Pesan</label>
        <textarea id="chat-body" wire:model="body" rows="2" maxlength="1000" class="form-input flex-1 py-2" placeholder="Tulis pesan… (Enter untuk baris baru)"></textarea>
        <button type="submit" class="btn-primary w-auto" wire:loading.attr="disabled">Kirim</button>
    </form>
    @error('body')<p class="px-4 pb-3 text-xs text-rose-700">{{ $message }}</p>@enderror
    <p class="px-4 pb-3 text-[11px] text-slate-500">Pesan diperbarui otomatis tiap 10 detik. Jaga etika berkomunikasi; moderator dapat menyembunyikan pesan.</p>
</div>
