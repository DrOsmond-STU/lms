<x-layouts.app title="Jalur Belajar AI" workspace="participant">
    <x-slot:heading>Jalur Belajar Personal</x-slot:heading>
    <x-slot:subtitle>Asisten AI menyusun urutan belajar berdasarkan riwayat pelatihan Anda dan program yang tersedia di katalog.</x-slot:subtitle>
    <div class="max-w-3xl space-y-4">
        @if (! $configured)
            <p class="card p-6 text-sm text-slate-500">Asisten AI belum diaktifkan oleh admin.</p>
        @else
            <form method="POST" action="{{ route('learning.path.generate') }}" class="card flex flex-wrap items-center justify-between gap-3 p-5">@csrf
                <span class="text-sm text-slate-600">{{ $insight ? 'Terakhir disusun '.$insight->generated_at->timezone(display_tz())->translatedFormat('d M Y H:i').'.' : 'Belum ada jalur belajar.' }} Sisa kuota AI hari ini: {{ $remaining }}.</span>
                <button class="btn-primary w-auto">{{ $insight ? 'Susun ulang' : 'Susun jalur belajar' }}</button>
            </form>
            @if ($insight)
                <article class="card p-6"><div class="prose-content text-sm">@include('components.safe-html', ['html' => $insight->body_html])</div>
                    <p class="mt-3 text-xs text-slate-500">Saran AI bersifat rekomendasi; keputusan pendaftaran tetap di tangan Anda. <a href="{{ route('catalog.participant') }}" class="font-bold text-link hover:underline">Lihat katalog &rarr;</a></p>
                </article>
            @endif
        @endif
    </div>
</x-layouts.app>
