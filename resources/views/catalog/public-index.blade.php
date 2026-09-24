<x-layouts.public title="Program Pelatihan" heading="Program Pelatihan & Sertifikasi" subtitle="Masuk atau daftar sebagai peserta untuk mengikuti kelas.">
    @include('catalog._filters')
    @include('catalog._grid', ['detailRoute' => 'catalog.public.show'])
</x-layouts.public>
