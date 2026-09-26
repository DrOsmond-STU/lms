<x-layouts.app title="Pengumuman" :workspace="$workspace">
    <x-slot:heading>Pengumuman</x-slot:heading>
    <x-slot:subtitle>Informasi dari platform, organisasi Anda, dan kelas yang Anda ikuti atau ampu.</x-slot:subtitle>
    @if ($manageUrl)
        <x-slot:actions><a href="{{ $manageUrl }}" class="btn-secondary">Kelola Pengumuman</a></x-slot:actions>
    @endif

    <div class="max-w-3xl space-y-4">
        @forelse ($announcements as $item)
            @include('announcements._item', ['item' => $item])
        @empty
            <div class="card p-8 text-center text-sm text-slate-500">Belum ada pengumuman untuk Anda.</div>
        @endforelse
    </div>
</x-layouts.app>
