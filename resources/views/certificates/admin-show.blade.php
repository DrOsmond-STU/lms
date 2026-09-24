@php($status = $certificate->publicStatus())
<x-layouts.app :title="$certificate->number" workspace="admin">
    <x-slot:back><a href="{{ route('admin.certificates.index') }}" class="hero-back">&larr; Basis Data Sertifikat</a></x-slot:back>
    <x-slot:heading>{{ $certificate->number }}</x-slot:heading>
    <x-slot:meta>
        <span class="badge bg-slate-100 text-slate-700">{{ \App\Modules\Certification\Models\Certificate::statusLabel($status) }}</span>
    </x-slot:meta>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card p-6 lg:col-span-2">
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">Pemegang</dt><dd class="font-bold">{{ $certificate->holder_name }}</dd></div>
                <div><dt class="text-slate-500">Program</dt><dd class="font-bold">{{ $certificate->program_name }}</dd></div>
                <div><dt class="text-slate-500">Kode verifikasi</dt><dd class="font-mono font-bold">{{ $certificate->formattedCode() }}</dd></div>
                <div><dt class="text-slate-500">Kelas</dt><dd>{{ $certificate->enrollment->courseClass->batch_name }}</dd></div>
                <div><dt class="text-slate-500">Terbit</dt><dd>{{ $certificate->issued_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</dd></div>
                <div><dt class="text-slate-500">Berlaku hingga</dt><dd>{{ $certificate->valid_until?->format('d M Y') ?? 'tanpa kedaluwarsa' }}</dd></div>
                <div><dt class="text-slate-500">Disetujui oleh</dt><dd>{{ $people[$certificate->approved_by] ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">SHA-256 PDF</dt><dd class="font-mono text-xs break-all">{{ $certificate->pdf_sha256 ?? '—' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-slate-500">Fingerprint penandatangan</dt><dd class="font-mono text-xs break-all">{{ $certificate->signature_cert_fingerprint ?? 'tidak ditandatangani' }}</dd></div>
            </dl>
            <div class="mt-5 flex gap-2">
                @if ($certificate->status === 'active')
                    @can('certificate.download')<a href="{{ route('certificates.download', $certificate) }}" class="btn-secondary">Unduh PDF</a>@endcan
                @endif
                <a href="{{ route('verification.show', $certificate->verification_code) }}" target="_blank" rel="noopener" class="btn-secondary">Halaman verifikasi</a>
            </div>
        </section>
        <aside class="space-y-6">
            @if ($revocation)
                <section class="card p-5 text-sm">
                    <h2 class="font-bold text-rose-700">Dicabut</h2>
                    <p class="mt-2">{{ $revocation->reason_text }}</p>
                    <p class="mt-2 text-xs text-slate-500">Diajukan {{ $people[$revocation->requested_by] ?? '—' }} · disetujui {{ $people[$revocation->approved_by] ?? '—' }}</p>
                </section>
            @elseif ($pending)
                <section class="card p-5 text-sm">
                    <h2 class="font-bold text-amber-700">Pencabutan menunggu persetujuan kedua</h2>
                    <p class="mt-2">Diajukan oleh {{ $people[$pending->requested_by] ?? '—' }}: {{ $pending->reason }}</p>
                    <a href="{{ route('admin.second-approvals.index') }}" class="mt-2 inline-block font-bold text-link hover:underline">Buka Persetujuan Kedua</a>
                </section>
            @elseif ($status !== 'revoked')
                @can('certificate.revoke')
                    <form method="POST" action="{{ route('admin.certificates.revoke', $certificate) }}" class="card space-y-2 p-5" novalidate>@csrf
                        <h2 class="font-bold text-slate-800">Ajukan Pencabutan</h2>
                        <p class="text-xs text-slate-500">Memerlukan persetujuan admin kedua (maker–checker).</p>
                        <label for="reason_code" class="form-label">Alasan</label>
                        <select id="reason_code" name="reason_code" class="form-select">
                            <option value="integrity_violation">Pelanggaran integritas</option>
                            <option value="data_error">Kesalahan data</option>
                            <option value="holder_request">Permintaan pemegang</option>
                            <option value="other">Lainnya</option>
                        </select>
                        <label for="reason" class="form-label">Keterangan</label>
                        <textarea id="reason" name="reason" rows="3" minlength="10" maxlength="500" class="form-input"></textarea>
                        <x-form-error field="reason" />
                        <button class="btn-danger">Ajukan</button>
                    </form>
                @endcan
            @endif
        </aside>
    </div>
</x-layouts.app>
