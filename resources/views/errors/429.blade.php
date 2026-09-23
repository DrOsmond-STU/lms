<x-layouts.guest title="Terlalu Banyak Permintaan">
    <p class="text-sm font-bold text-brand-700">Kode 429</p>
    <h2 class="mt-1 text-2xl font-extrabold text-slate-800">Terlalu Banyak Permintaan</h2>
    <p class="mt-2 text-sm text-slate-600">Terlalu banyak percobaan dalam waktu singkat. Coba lagi dalam beberapa menit.</p>
    @if ($rid = request()->attributes->get('request_id'))
        <p class="mt-4 text-xs text-slate-500">ID permintaan untuk dukungan: <code class="font-mono">{{ $rid }}</code></p>
    @endif
    <a href="{{ route('home') }}" class="btn-primary mt-6">Kembali ke Beranda</a>
</x-layouts.guest>
