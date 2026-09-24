<x-layouts.guest :title="$title">
    <h2 class="text-2xl font-extrabold text-slate-800">{{ $title }}</h2>
    <p class="mt-1 text-xs text-slate-500">Versi {{ $version }}</p>
    <div class="mt-5 space-y-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <p class="font-bold">Draf untuk lingkungan uji.</p>
        <p>Teks final {{ mb_strtolower($title) }} disusun oleh tim legal Semesta Teknologi Utama sebelum go-live dan ditampilkan di halaman ini. Setiap perubahan versi akan meminta persetujuan ulang dari pengguna.</p>
    </div>
    <div class="mt-5 space-y-3 text-sm text-slate-700">
        @foreach ($points as $point)
            <p>{{ $point }}</p>
        @endforeach
    </div>
    <p class="mt-6 text-sm"><a href="{{ route('register') }}" class="font-bold text-brand-700 hover:underline">Kembali ke pendaftaran</a></p>
</x-layouts.guest>
