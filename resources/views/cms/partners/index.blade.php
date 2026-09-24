<x-layouts.app title="Pengaturan — Mitra" workspace="admin">
    <x-slot:heading>Pengaturan Sistem</x-slot:heading>
    <x-slot:subtitle>Perusahaan & universitas pengguna {{ setting('branding.short_name') }}, tampil sebagai logo berjalan. Pastikan ada izin penggunaan nama & logo mereka.</x-slot:subtitle>
    @can('cms.update')
        <x-slot:actions><a href="{{ route('admin.landing.partners.create') }}" class="btn-primary w-auto"><x-icon name="plus" class="h-4 w-4" />Tambah Mitra</a></x-slot:actions>
    @endcan
    @include('settings._tabs', ['tab' => 'beranda', 'sub' => 'mitra'])
    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th scope="col">Logo</th><th scope="col">Nama</th><th scope="col">Jenis</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
            <tbody>
                @forelse ($partners as $partner)
                    <tr>
                        <td>@include('cms._partner-mark', ['partner' => $partner])</td>
                        <td class="font-semibold text-slate-800">{{ $partner->name }}@if ($partner->website_url)<span class="block text-xs font-normal text-slate-500">{{ $partner->website_url }}</span>@endif</td>
                        <td>{{ \App\Modules\Cms\Models\LandingPartner::TYPES[$partner->type] ?? $partner->type }}</td>
                        <td><span class="chip {{ $partner->is_active ? 'chip-low' : 'chip-neutral' }}">{{ $partner->is_active ? 'Tampil' : 'Disembunyikan' }}</span></td>
                        <td class="text-right whitespace-nowrap">
                            @can('cms.update')
                                <a href="{{ route('admin.landing.partners.edit', $partner) }}" class="btn-mini">Ubah</a>
                                <form method="POST" action="{{ route('admin.landing.partners.destroy', $partner) }}" class="inline" data-confirm="Hapus mitra ini?">@csrf @method('DELETE')<button type="submit" class="btn-mini-danger">Hapus</button></form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-10 text-center text-slate-500">Belum ada mitra. Pita logo di beranda disembunyikan sampai ada mitra yang tampil.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
