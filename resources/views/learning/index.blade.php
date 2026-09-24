<x-layouts.app title="Pembelajaran Saya" workspace="participant">
    <x-slot:heading>Pembelajaran Saya</x-slot:heading>
    <x-slot:subtitle>Riwayat dan progres pelatihan Anda.</x-slot:subtitle>

    <div class="grid gap-5 md:grid-cols-2">
        @forelse ($enrollments as $enrollment)
            <article class="card p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-extrabold text-slate-800">{{ $enrollment->program->name }}</h2>
                        <p class="text-xs text-slate-500">{{ $enrollment->courseClass->batch_name }} · {{ $enrollment->courseClass->starts_on->translatedFormat('d M Y') }} – {{ $enrollment->courseClass->ends_on->translatedFormat('d M Y') }}</p>
                    </div>
                    <span @class(['badge', 'bg-emerald-50 text-emerald-700' => $enrollment->status === 'passed', 'bg-amber-50 text-amber-700' => $enrollment->status === 'pending_approval', 'bg-rose-50 text-rose-700' => in_array($enrollment->status, ['failed', 'cancelled'], true), 'bg-brand-50 text-link' => $enrollment->isActive()])>{{ $enrollment->statusLabel() }}</span>
                </div>
                <div class="mt-4 flex items-center gap-3">
                    <div class="h-2 flex-1 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-accent-500 progress-{{ (int) (floor($enrollment->progress_percent / 5) * 5) }}"></div></div>
                    <span class="text-xs font-bold">{{ $enrollment->progress_percent }}%</span>
                </div>
                <div class="mt-3 flex items-center justify-between text-xs text-slate-600">
                    <span>Skor akhir: {{ $enrollment->final_score !== null ? rtrim(rtrim($enrollment->final_score, '0'), '.') : '—' }}</span>
                    @if ($enrollment->rejection_reason && $enrollment->isActive())<span class="text-rose-700">Approval ditolak: {{ $enrollment->rejection_reason }}</span>@endif
                </div>
                @if ($enrollment->status !== 'cancelled')
                    <a href="{{ route('learning.classroom', $enrollment) }}" class="btn-primary mt-4">{{ $enrollment->isActive() ? 'Lanjutkan Belajar' : 'Lihat Kelas' }}</a>
                @endif
            </article>
        @empty
            <div class="card p-8 text-center text-sm text-slate-500 md:col-span-2">Belum ada pelatihan. <a href="{{ route('catalog.participant') }}" class="font-bold text-link hover:underline">Pilih pelatihan</a>.</div>
        @endforelse
    </div>
</x-layouts.app>
