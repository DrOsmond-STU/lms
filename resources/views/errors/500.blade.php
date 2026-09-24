<x-layouts.guest title="Terjadi Kesalahan">
    <p class="text-sm font-bold text-link">Kode 500</p>
    <h2 class="mt-1 text-2xl font-extrabold text-slate-800">Terjadi Kesalahan</h2>
    <p class="mt-2 text-sm text-slate-600">Terjadi kesalahan pada sistem. Tim kami telah diberi tahu.</p>
    @if ($rid = request()->attributes->get('request_id'))
        <p class="mt-4 text-xs text-slate-500">ID permintaan untuk dukungan: <code class="font-mono">{{ $rid }}</code></p>
    @endif
    <a href="{{ route('home') }}" class="btn-primary mt-6">Kembali ke Beranda</a>
</x-layouts.guest>
