<x-layouts.guest title="Akses Ditolak">
    <p class="text-sm font-bold text-link">Kode 403</p>
    <h2 class="mt-1 text-2xl font-extrabold text-slate-800">Akses Ditolak</h2>
    <p class="mt-2 text-sm text-slate-600">Anda tidak memiliki izin untuk membuka halaman ini.</p>
    @if ($rid = request()->attributes->get('request_id'))
        <p class="mt-4 text-xs text-slate-500">ID permintaan untuk dukungan: <code class="font-mono">{{ $rid }}</code></p>
    @endif
    <a href="{{ route('home') }}" class="btn-primary mt-6">Kembali ke Beranda</a>
</x-layouts.guest>
