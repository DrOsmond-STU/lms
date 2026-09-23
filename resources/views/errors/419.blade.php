<x-layouts.guest title="Sesi Kedaluwarsa">
    <p class="text-sm font-bold text-brand-700">Kode 419</p>
    <h2 class="mt-1 text-2xl font-extrabold text-slate-800">Sesi Kedaluwarsa</h2>
    <p class="mt-2 text-sm text-slate-600">Halaman terlalu lama dibuka. Muat ulang halaman lalu coba lagi.</p>
    @if ($rid = request()->attributes->get('request_id'))
        <p class="mt-4 text-xs text-slate-500">ID permintaan untuk dukungan: <code class="font-mono">{{ $rid }}</code></p>
    @endif
    <a href="{{ route('home') }}" class="btn-primary mt-6">Kembali ke Beranda</a>
</x-layouts.guest>
