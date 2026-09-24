<x-layouts.app title="Sertifikat Saya" workspace="participant">
    <h1 class="text-xl font-extrabold text-slate-800">Sertifikat Saya</h1>
    <p class="mt-0.5 mb-6 text-sm text-slate-600">Unduhan memakai tautan aman yang berlaku 5 menit.</p>
    <div class="grid gap-5 md:grid-cols-2">
        @forelse ($certificates as $certificate)
            @php($status = $certificate->publicStatus())
            <article class="card p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-extrabold text-slate-800">{{ $certificate->program_name }}</h2>
                        <p class="font-mono text-xs text-slate-500">{{ $certificate->number }}</p>
                    </div>
                    <span @class(['badge', 'bg-emerald-50 text-emerald-700' => $status === 'valid', 'bg-amber-50 text-amber-700' => in_array($status, ['expired', 'generating'], true), 'bg-rose-50 text-rose-700' => $status === 'revoked'])>{{ \App\Modules\Certification\Models\Certificate::statusLabel($status) }}</span>
                </div>
                <dl class="mt-3 space-y-1 text-xs text-slate-600">
                    <div>Terbit: {{ $certificate->issued_at->translatedFormat('d M Y') }} · Berlaku hingga: {{ $certificate->valid_until?->translatedFormat('d M Y') ?? 'tanpa kedaluwarsa' }}</div>
                    <div>Kode verifikasi: <span class="font-mono font-bold">{{ $certificate->formattedCode() }}</span></div>
                </dl>
                <div class="mt-4 flex flex-wrap gap-2">
                    @if ($certificate->status === 'active')
                        <a href="{{ route('certificates.download', $certificate) }}" class="btn-primary w-auto">Unduh PDF</a>
                    @endif
                    <a href="{{ route('verification.show', $certificate->verification_code) }}" target="_blank" rel="noopener" class="btn-secondary">Halaman verifikasi</a>
                </div>
            </article>
        @empty
            <div class="card p-8 text-center text-sm text-slate-500 md:col-span-2">Belum ada sertifikat. Selesaikan pelatihan untuk mendapatkannya.</div>
        @endforelse
    </div>
</x-layouts.app>
