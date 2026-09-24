<x-layouts.public title="Verifikasi Sertifikat" subtitle="Periksa keaslian sertifikat STU dengan kode verifikasi (tercetak di sertifikat / dari QR) atau nomor sertifikat.">
    <div class="mx-auto max-w-xl">
        <form method="POST" action="{{ route('verification.lookup') }}" class="card space-y-4 p-6" novalidate>
            @csrf
            <input type="hidden" name="mode" value="code">
            <div>
                <label for="value" class="form-label">Kode verifikasi</label>
                <input id="value" name="value" value="{{ old('value') }}" required maxlength="20" autocomplete="off" class="form-input font-mono uppercase" placeholder="XXXX-XXXX-XXXX">
                <x-form-error field="value" />
            </div>
            <button class="btn-primary">Verifikasi</button>
        </form>
        <details class="card mt-4 p-6">
            <summary class="cursor-pointer text-sm font-bold text-link">Cari dengan nomor sertifikat</summary>
            <form method="POST" action="{{ route('verification.lookup') }}" class="mt-4 space-y-4" novalidate>
                @csrf
                <input type="hidden" name="mode" value="number">
                <div>
                    <label for="number" class="form-label">Nomor sertifikat</label>
                    <input id="number" name="value" required maxlength="80" autocomplete="off" class="form-input font-mono uppercase">
                </div>
                <div>
                    <label for="full_name" class="form-label">Nama lengkap pemegang (opsional)</label>
                    <input id="full_name" name="full_name" maxlength="120" autocomplete="off" class="form-input">
                    <p class="mt-1 text-xs text-slate-500">Tanpa nama lengkap yang cocok, nama pemegang ditampilkan tersamar.</p>
                </div>
                <button class="btn-secondary">Cari</button>
            </form>
        </details>
        <p class="mt-4 text-xs text-slate-500">Data yang ditampilkan dibatasi seperlunya. Pencarian dibatasi per alamat IP untuk mencegah penyalahgunaan.</p>
    </div>
</x-layouts.public>
