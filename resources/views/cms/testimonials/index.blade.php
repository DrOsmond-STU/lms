<x-layouts.app title="Pengaturan — Testimoni" workspace="admin">
    <x-slot:heading>Pengaturan Sistem</x-slot:heading>
    <x-slot:subtitle>Testimoni pengguna. Terbitkan hanya testimoni yang pemiliknya sudah menyetujui publikasi nama & kutipannya.</x-slot:subtitle>
    @can('cms.update')
        <x-slot:actions><a href="{{ route('admin.landing.testimonials.create') }}" class="btn-primary w-auto"><x-icon name="plus" class="h-4 w-4" />Tambah Testimoni</a></x-slot:actions>
    @endcan
    @include('settings._tabs', ['tab' => 'beranda', 'sub' => 'testimoni'])
    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th scope="col">Nama</th><th scope="col">Kutipan</th><th scope="col">Nilai</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
            <tbody>
                @forelse ($testimonials as $item)
                    <tr>
                        <td><span class="font-semibold text-slate-800">{{ $item->name }}</span><span class="block text-xs text-slate-500">{{ collect([$item->role_title, $item->organization_name])->filter()->implode(' · ') }}</span></td>
                        <td class="max-w-md text-sm">{{ \Illuminate\Support\Str::limit($item->quote, 120) }}</td>
                        <td class="font-mono">{{ $item->rating }}/5</td>
                        <td>
                            <span class="chip {{ $item->is_published ? 'chip-low' : 'chip-neutral' }}">{{ $item->is_published ? 'Terbit' : 'Draf' }}</span>
                            @if ($item->is_sample)<span class="chip chip-medium mt-1">Contoh</span>@endif
                        </td>
                        <td class="text-right whitespace-nowrap">
                            @can('cms.update')
                                <a href="{{ route('admin.landing.testimonials.edit', $item) }}" class="btn-mini">Ubah</a>
                                <form method="POST" action="{{ route('admin.landing.testimonials.destroy', $item) }}" class="inline" data-confirm="Hapus testimoni ini?">@csrf @method('DELETE')<button type="submit" class="btn-mini-danger">Hapus</button></form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-10 text-center text-slate-500">Belum ada testimoni. Bagian testimoni di beranda disembunyikan sampai ada yang terbit.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
