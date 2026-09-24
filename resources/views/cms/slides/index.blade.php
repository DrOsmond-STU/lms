<x-layouts.app title="Konten Beranda — Slide" workspace="admin">
    <x-slot:heading>Konten Beranda</x-slot:heading>
    <x-slot:subtitle>Slide pembuka beranda. Gambar tampil samar di bawah lapisan biru gradasi. Tanpa slide aktif, beranda memakai slide bawaan.</x-slot:subtitle>
    @can('cms.update')
        <x-slot:actions><a href="{{ route('admin.landing.slides.create') }}" class="btn-primary w-auto"><x-icon name="plus" class="h-4 w-4" />Tambah Slide</a></x-slot:actions>
    @endcan
    @include('cms._tabs')
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($slides as $slide)
            <article class="card overflow-hidden">
                <div class="landing-slide-thumb relative h-36">
                    @if ($slide->image_path)<img src="{{ route('landing.image.slide', ['slide' => $slide, 'v' => $slide->updated_at?->timestamp]) }}" alt="" class="absolute inset-0 h-full w-full object-cover">@endif
                    <div class="landing-overlay absolute inset-0"></div>
                    <div class="relative p-4 text-white">
                        <p class="hero-eyebrow">{{ $slide->eyebrow }}</p>
                        <p class="mt-1 font-display text-lg leading-tight font-bold">{{ $slide->title }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 p-4">
                    <span class="chip {{ $slide->is_active ? 'chip-low' : 'chip-neutral' }}">{{ $slide->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                    <span class="font-mono text-xs text-slate-500">#{{ $slide->position }}</span>
                    @can('cms.update')
                        <a href="{{ route('admin.landing.slides.edit', $slide) }}" class="btn-mini ml-auto">Ubah</a>
                        <form method="POST" action="{{ route('admin.landing.slides.destroy', $slide) }}" data-confirm="Hapus slide ini?">@csrf @method('DELETE')<button type="submit" class="btn-mini-danger">Hapus</button></form>
                    @endcan
                </div>
            </article>
        @empty
            <div class="card p-8 text-center text-sm text-slate-500 md:col-span-2 xl:col-span-3">Belum ada slide — beranda menampilkan tiga slide bawaan.</div>
        @endforelse
    </div>
</x-layouts.app>
