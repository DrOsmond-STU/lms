<x-layouts.public title="Program Pelatihan">
    <h1 class="text-2xl font-extrabold text-slate-800">Program Pelatihan &amp; Sertifikasi</h1>
    <p class="mt-1 mb-6 text-sm text-slate-600">Masuk atau daftar sebagai peserta untuk mengikuti kelas.</p>
    @include('catalog._filters')
    @include('catalog._grid', ['detailRoute' => 'catalog.public.show'])
</x-layouts.public>
