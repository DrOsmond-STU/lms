@php($categories = \App\Modules\Catalog\Models\Program::CATEGORIES)
<x-layouts.app title="Program Pelatihan" workspace="admin">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-extrabold text-slate-800">Program Pelatihan</h1>
            <p class="mt-0.5 text-sm text-slate-600">Program melalui review sebelum terbit di katalog. Penerbit harus berbeda dari pengaju.</p>
        </div>
        @can('program.create')
            <a href="{{ route('admin.programs.create') }}" class="btn-primary w-auto">Tambah Program</a>
        @endcan
    </div>

    <form method="GET" class="card mb-5 flex flex-wrap items-end gap-3 p-4" role="search">
        <div class="min-w-48 flex-1">
            <label for="q" class="form-label">Cari nama / kode</label>
            <input id="q" name="q" type="search" value="{{ $search }}" maxlength="100" class="form-input">
        </div>
        <div>
            <label for="kategori" class="form-label">Kategori</label>
            <select id="kategori" name="kategori" class="form-select">
                <option value="">Semua</option>
                @foreach ($categories as $value => $label)
                    <option value="{{ $value }}" @selected($category === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status" class="form-label">Status</label>
            <select id="status" name="status" class="form-select">
                <option value="">Semua</option>
                @foreach (\App\Modules\Catalog\Models\Program::STATUSES as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-secondary">Terapkan</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <caption class="sr-only">Daftar program</caption>
            <thead><tr><th scope="col">Program</th><th scope="col">Kode</th><th scope="col">Kategori</th><th scope="col">Harga</th><th scope="col">Kelas</th><th scope="col">Status</th></tr></thead>
            <tbody>
                @forelse ($programs as $program)
                    <tr>
                        <td><a href="{{ route('admin.programs.show', $program) }}" class="font-bold text-brand-700 hover:underline">{{ $program->name }}</a><span class="block text-xs text-slate-500">{{ $program->provider_name }}</span></td>
                        <td class="font-mono text-xs">{{ $program->short_code }}</td>
                        <td>{{ $categories[$program->category] ?? $program->category }}</td>
                        <td>{{ $program->priceLabel() }}</td>
                        <td>{{ $program->classes_count }}</td>
                        <td>@include('admin.programs._status', ['status' => $program->status])</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-500">Belum ada program.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $programs->links() }}</div>
</x-layouts.app>
