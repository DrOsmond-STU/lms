<x-layouts.app title="Tambah Organisasi" workspace="admin">
    <x-slot:back><a href="{{ route('admin.organizations.index') }}" class="hero-back">&larr; Organisasi</a></x-slot:back>
    <x-slot:heading>Tambah Organisasi</x-slot:heading>

    <form method="POST" action="{{ route('admin.organizations.store') }}" class="card max-w-2xl space-y-4 p-6" novalidate>
        @csrf
        @include('admin.organizations._fields', ['editing' => false])
        <button type="submit" class="btn-primary w-auto">Simpan</button>
    </form>
</x-layouts.app>
