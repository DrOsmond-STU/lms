<x-layouts.app title="Notifikasi" :workspace="$workspace">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-extrabold text-slate-800">Notifikasi</h1>
            <p class="mt-0.5 text-sm text-slate-600">Informasi enrollment, ujian, sertifikat, dan keamanan akun Anda.</p>
        </div>
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button type="submit" class="btn-secondary">Tandai semua dibaca</button>
        </form>
    </div>
    <nav class="mb-4 flex gap-2 text-sm" aria-label="Filter notifikasi">
        <a href="{{ route('notifications.index') }}" @class(['badge', 'bg-brand-800 text-white' => ! $unreadOnly, 'bg-slate-100 text-slate-700' => $unreadOnly]) @if (! $unreadOnly) aria-current="page" @endif>Semua</a>
        <a href="{{ route('notifications.index', ['filter' => 'belum-dibaca']) }}" @class(['badge', 'bg-brand-800 text-white' => $unreadOnly, 'bg-slate-100 text-slate-700' => ! $unreadOnly]) @if ($unreadOnly) aria-current="page" @endif>Belum dibaca</a>
    </nav>
    <div class="card divide-y divide-slate-100">
        @forelse ($notifications as $item)
            <form method="POST" action="{{ route('notifications.open', $item->id) }}" class="block">
                @csrf
                <button type="submit" @class(['flex w-full items-start gap-3 px-5 py-4 text-left hover:bg-slate-50', 'bg-brand-50/40' => $item->read_at === null])>
                    <span @class(['mt-1.5 h-2 w-2 shrink-0 rounded-full', 'bg-accent-500' => $item->read_at === null, 'bg-transparent' => $item->read_at !== null]) aria-hidden="true"></span>
                    <span class="flex-1">
                        <span class="block text-xs font-bold tracking-wide text-slate-500 uppercase">{{ $categories[$item->category] ?? $item->category }}</span>
                        <span class="block font-bold text-slate-800">{{ $item->title }}@if ($item->read_at === null)<span class="sr-only"> (belum dibaca)</span>@endif</span>
                        <span class="block text-sm text-slate-600">{{ $item->body }}</span>
                    </span>
                    <span class="text-xs whitespace-nowrap text-slate-500">{{ $item->created_at->timezone('Asia/Jakarta')->translatedFormat('d M H:i') }}</span>
                </button>
            </form>
        @empty
            <p class="px-5 py-10 text-center text-sm text-slate-500">Belum ada notifikasi.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $notifications->links() }}</div>
</x-layouts.app>
