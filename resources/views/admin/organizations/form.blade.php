<x-layouts.app title="Tambah Organisasi" workspace="admin">
    <a href="{{ route('admin.organizations.index') }}" class="text-sm font-bold text-brand-700 hover:underline">&larr; Organisasi</a>
    <h1 class="mt-2 mb-6 text-xl font-extrabold text-slate-800">Tambah Organisasi</h1>

    <form method="POST" action="{{ route('admin.organizations.store') }}" class="card max-w-2xl space-y-4 p-6" novalidate>
        @csrf
        @include('admin.organizations._fields', ['editing' => false])
        <button type="submit" class="btn-primary w-auto">Simpan</button>
    </form>
</x-layouts.app>
