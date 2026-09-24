<x-layouts.guest :title="$title">
    <h2 class="text-2xl font-extrabold text-slate-800">{{ $title }}</h2>
    <p class="mt-1 text-xs text-slate-500">Versi {{ $version }} · {{ \App\Modules\Cms\Models\SiteProfile::current()->company_name }}</p>
    @if ($isDraft)
        <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-bold">Draf untuk lingkungan uji.</p>
            <p>Teks final disusun tim legal sebelum go-live. Setiap perubahan versi akan meminta persetujuan ulang dari pengguna.</p>
        </div>
    @endif
    <div class="prose-content mt-5 text-sm"><x-safe-html :html="$bodyHtml" /></div>
    <p class="mt-6 text-sm"><a href="{{ route('home') }}" class="font-bold text-link hover:underline">&larr; Beranda</a></p>
</x-layouts.guest>
