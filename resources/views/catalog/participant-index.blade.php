<x-layouts.app title="Pilih Pelatihan" workspace="participant">
    <x-slot:heading>Pilih Pelatihan</x-slot:heading>
    <x-slot:subtitle>Program yang terbit dan kelas yang sedang membuka pendaftaran.</x-slot:subtitle>

    @include('catalog._filters')
    @include('catalog._grid', ['detailRoute' => 'catalog.participant.show'])
</x-layouts.app>
