<x-layouts.guest title="Halaman Tidak Ditemukan">
    <p class="text-sm font-bold text-brand-700">Kode 404</p>
    <h2 class="mt-1 text-2xl font-extrabold text-slate-800">Halaman Tidak Ditemukan</h2>
    <p class="mt-2 text-sm text-slate-600">Halaman yang Anda cari tidak ada atau tidak tersedia untuk akun Anda.</p>
    @if ($rid = request()->attributes->get('request_id'))
        <p class="mt-4 text-xs text-slate-500">ID permintaan untuk dukungan: <code class="font-mono">{{ $rid }}</code></p>
    @endif
    <a href="{{ route('home') }}" class="btn-primary mt-6">Kembali ke Beranda</a>
</x-layouts.guest>
