<x-layouts.guest title="Kode Pemulihan">
    <h2 class="text-2xl font-extrabold text-slate-800">Simpan Kode Pemulihan</h2>
    <p class="mt-1 mb-4 text-sm text-slate-600">Autentikasi dua faktor telah aktif. Setiap kode di bawah hanya dapat dipakai <strong>sekali</strong> bila Anda kehilangan akses ke aplikasi autentikator.</p>
    <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
        Kode ini <strong>hanya ditampilkan sekali</strong>. Simpan di tempat aman (mis. password manager), jangan dibagikan.
    </div>
    <ul class="card mb-6 grid grid-cols-2 gap-2 p-5 font-mono text-sm tracking-wider text-slate-800">
        @foreach ($codes as $code)
            <li>{{ $code }}</li>
        @endforeach
    </ul>
    <a href="{{ route('dashboard') }}" class="btn-primary">Saya sudah menyimpannya — Lanjutkan</a>
</x-layouts.guest>
