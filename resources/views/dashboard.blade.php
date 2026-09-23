@php
    $titles = ['participant' => 'Dashboard Peserta', 'trainer' => 'Dashboard Trainer', 'organization' => 'Dashboard Organisasi', 'admin' => 'Dashboard Administrator'];
@endphp
<x-layouts.app :title="$titles[$workspace]" :workspace="$workspace">
    <h1 class="text-xl font-extrabold text-slate-800">{{ $titles[$workspace] }}</h1>
    <p class="mt-0.5 mb-6 text-sm text-slate-600">Selamat datang, {{ $user->name }}.</p>

    <div class="grid gap-5 md:grid-cols-3">
        <div class="card p-5">
            <p class="text-xs font-bold tracking-wide text-slate-500 uppercase">Status Akun</p>
            <p class="mt-2 text-lg font-extrabold text-emerald-700">Aktif</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-bold tracking-wide text-slate-500 uppercase">Autentikasi Dua Faktor</p>
            @if ($user->hasConfirmedMfa())
                <p class="mt-2 text-lg font-extrabold text-emerald-700">Aktif</p>
            @else
                <p class="mt-2 text-lg font-extrabold text-amber-700">Belum aktif</p>
                <a href="{{ route('mfa.setup') }}" class="mt-1 inline-block text-xs font-bold text-brand-700 hover:underline">Aktifkan sekarang</a>
            @endif
        </div>
        <div class="card p-5">
            <p class="text-xs font-bold tracking-wide text-slate-500 uppercase">Peran</p>
            <p class="mt-2 text-sm font-bold text-slate-800">{{ collect($user->roleCodes())->map->label()->implode(', ') }}</p>
        </div>
    </div>

    <div class="card mt-6 p-6">
        <h2 class="font-bold text-slate-800">Fondasi platform siap</h2>
        <p class="mt-1 text-sm text-slate-600">Fitur pembelajaran, ujian, sertifikat, dan laporan dibangun bertahap sesuai roadmap (Fase 1). Menu bertanda <span class="font-bold">Segera</span> akan aktif setelah modulnya selesai.</p>
    </div>
</x-layouts.app>
