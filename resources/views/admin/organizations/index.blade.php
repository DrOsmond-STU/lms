@php($typeLabels = ['institution' => 'Institusi', 'corporate' => 'Korporat'])
<x-layouts.app title="Organisasi" workspace="admin">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-extrabold text-slate-800">Organisasi</h1>
            <p class="mt-0.5 text-sm text-slate-600">Institusi pendidikan &amp; korporat mitra. Organisasi diarsipkan, tidak dihapus.</p>
        </div>
        @can('organization.create')
            <a href="{{ route('admin.organizations.create') }}" class="btn-primary w-auto">Tambah Organisasi</a>
        @endcan
    </div>

    <form method="GET" action="{{ route('admin.organizations.index') }}" class="card mb-5 flex flex-wrap items-end gap-3 p-4" role="search">
        <div class="min-w-48 flex-1">
            <label for="q" class="form-label">Cari nama / kode</label>
            <input id="q" name="q" type="search" value="{{ $search }}" maxlength="100" class="form-input">
        </div>
        <div>
            <label for="tipe" class="form-label">Tipe</label>
            <select id="tipe" name="tipe" class="form-select">
                <option value="">Semua</option>
                @foreach ($typeLabels as $value => $label)
                    <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status" class="form-label">Status</label>
            <select id="status" name="status" class="form-select">
                <option value="">Semua</option>
                <option value="active" @selected($status === 'active')>Aktif</option>
                <option value="inactive" @selected($status === 'inactive')>Diarsipkan</option>
            </select>
        </div>
        <button type="submit" class="btn-secondary">Terapkan</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <caption class="sr-only">Daftar organisasi</caption>
            <thead>
                <tr><th scope="col">Nama</th><th scope="col">Kode</th><th scope="col">Tipe</th><th scope="col">Kota</th><th scope="col">Anggota Aktif</th><th scope="col">Status</th></tr>
            </thead>
            <tbody>
                @forelse ($organizations as $organization)
                    <tr>
                        <td><a href="{{ route('admin.organizations.show', $organization) }}" class="font-bold text-brand-700 hover:underline">{{ $organization->name }}</a></td>
                        <td class="font-mono">{{ $organization->code }}</td>
                        <td>{{ $typeLabels[$organization->type] ?? $organization->type }}</td>
                        <td>{{ $organization->city ?? '—' }}</td>
                        <td>{{ number_format($organization->active_members_count, 0, ',', '.') }}</td>
                        <td>
                            @if ($organization->status === 'active')
                                <span class="badge bg-emerald-50 text-emerald-700">Aktif</span>
                            @else
                                <span class="badge bg-slate-100 text-slate-600">Diarsipkan</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-500">Belum ada organisasi yang cocok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $organizations->links() }}</div>
</x-layouts.app>
