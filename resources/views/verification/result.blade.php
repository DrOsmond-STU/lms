@php($label = \App\Modules\Certification\Models\Certificate::statusLabel($status === 'generating' ? 'not_found' : $status))
<x-layouts.public title="Hasil Verifikasi" :subtitle="'Status keaslian sertifikat menurut basis data '.setting('branding.short_name').'.'">
    <div class="mx-auto max-w-xl">
        <div class="card p-6">
            @if ($certificate && $status !== 'generating')
                <p @class(['text-2xl font-extrabold', 'text-emerald-700' => $status === 'valid', 'text-amber-700' => $status === 'expired', 'text-rose-700' => in_array($status, ['revoked', 'superseded'], true)])>{{ $label }}</p>
                <dl class="mt-4 space-y-2 text-sm">
                    <div><dt class="text-slate-500">Nama pemegang</dt><dd class="font-bold">{{ $name }}</dd></div>
                    <div><dt class="text-slate-500">Program</dt><dd class="font-bold">{{ $certificate->program_name }}</dd></div>
                    <div><dt class="text-slate-500">Penyelenggara</dt><dd class="font-bold">{{ $certificate->provider_name }}</dd></div>
                    <div><dt class="text-slate-500">Nomor</dt><dd class="font-mono">{{ $certificate->number }}</dd></div>
                    <div><dt class="text-slate-500">Terbit</dt><dd>{{ $certificate->issued_at->locale('id')->translatedFormat('d F Y') }}</dd></div>
                    <div><dt class="text-slate-500">Berlaku hingga</dt><dd>{{ $certificate->valid_until?->locale('id')->translatedFormat('d F Y') ?? 'Tanpa kedaluwarsa' }}</dd></div>
                </dl>
                <p class="mt-4 text-xs text-slate-500">{{ \App\Modules\Settings\Services\SystemSettings::text('certificate.verification_note', \App\Modules\Cms\Models\SiteProfile::current()->company_name) }}</p>
            @else
                <p class="text-2xl font-extrabold text-slate-700">Tidak ditemukan</p>
                <p class="mt-2 text-sm text-slate-600">Tidak ada sertifikat yang cocok dengan data tersebut. Periksa kembali penulisan kode/nomor.</p>
            @endif
        </div>
        <a href="{{ route('verification.form') }}" class="mt-4 inline-block text-sm font-bold text-link hover:underline">Verifikasi lain</a>
    </div>
</x-layouts.public>
