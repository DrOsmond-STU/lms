<x-layouts.app title="Pilih Pelatihan" workspace="participant">
    <h1 class="text-xl font-extrabold text-slate-800">Pilih Pelatihan</h1>
    <p class="mt-0.5 mb-6 text-sm text-slate-600">Program yang terbit dan kelas yang sedang membuka pendaftaran.</p>
    @include('catalog._filters')
    @include('catalog._grid', ['detailRoute' => 'catalog.participant.show'])
</x-layouts.app>
