<x-layouts.app title="Pengguna" workspace="admin">
    <x-slot:heading>Pengguna</x-slot:heading>
    <x-slot:subtitle>Akun dinonaktifkan, tidak dihapus. Admin tidak pernah menetapkan kata sandi — pengguna baru menerima undangan.</x-slot:subtitle>
    <x-slot:actions>
        @can('user.create')
        <a href="{{ route('admin.users.create') }}" class="btn-primary w-auto">Undang Pengguna</a>
        @endcan
    </x-slot:actions>

    <form method="GET" action="{{ route('admin.users.index') }}" class="card mb-5 flex flex-wrap items-end gap-3 p-4" role="search">
        <div class="min-w-48 flex-1">
            <label for="q" class="form-label">Cari nama atau email lengkap</label>
            <input id="q" name="q" type="search" value="{{ $search }}" maxlength="100" class="form-input">
        </div>
        <div>
            <label for="peran" class="form-label">Peran</label>
            <select id="peran" name="peran" class="form-select">
                <option value="">Semua</option>
                @foreach ($roles as $item)
                    <option value="{{ $item->value }}" @selected($role === $item)>{{ $item->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status" class="form-label">Status</label>
            <select id="status" name="status" class="form-select">
                <option value="">Semua</option>
                <option value="active" @selected($status === 'active')>Aktif</option>
                <option value="pending_verification" @selected($status === 'pending_verification')>Menunggu aktivasi</option>
                <option value="deactivated" @selected($status === 'deactivated')>Nonaktif</option>
            </select>
        </div>
        <button type="submit" class="btn-secondary">Terapkan</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <caption class="sr-only">Daftar pengguna</caption>
            <thead>
                <tr><th scope="col">Nama</th><th scope="col">Email</th><th scope="col">Peran</th><th scope="col">Status</th><th scope="col">Terakhir Masuk</th></tr>
            </thead>
            <tbody>
                @forelse ($users as $item)
                    <tr>
                        <td><a href="{{ route('admin.users.show', $item) }}" class="font-bold text-link hover:underline">{{ $item->name }}</a></td>
                        <td class="font-mono text-xs">{{ \App\Support\Privacy\Mask::email($item->email) }}</td>
                        <td>{{ collect($item->roleCodes())->map->label()->implode(', ') ?: '—' }}</td>
                        <td>@include('admin.users._status', ['status' => $item->status])</td>
                        <td class="text-xs text-slate-600">{{ $item->last_login_at?->timezone('Asia/Jakarta')->translatedFormat('d M Y H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">Tidak ada pengguna yang cocok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
</x-layouts.app>
