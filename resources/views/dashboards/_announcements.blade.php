{{-- Pengumuman terbaru untuk pengguna (dari AnnouncementFeed). $announcements --}}
@if ($announcements->isNotEmpty())
    <section class="mt-8">
        <div class="section-head"><h2>Pengumuman</h2><a href="{{ route('announcements.index') }}" class="sub font-bold text-link hover:underline">Semua pengumuman &rarr;</a></div>
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($announcements as $item)
                <a href="{{ route('announcements.index') }}#ann-{{ $item->id }}" class="card block p-4 hover:border-brand-300">
                    <span class="flex flex-wrap items-center gap-2 text-xs">@if ($item->is_pinned)<span class="badge bg-amber-50 text-amber-800">Disematkan</span>@endif<span class="text-slate-500">{{ $item->publish_at->timezone(display_tz())->translatedFormat('d M Y') }} · {{ $item->scope === 'class' ? ($item->courseClass?->batch_name ?? 'Kelas') : ($item->scope === 'organization' ? ($item->organization?->name ?? 'Organisasi') : 'Platform') }}</span></span>
                    <span class="mt-1 block font-bold text-slate-800">{{ $item->title }}</span>
                    <span class="mt-1 line-clamp-2 block text-sm text-slate-600">{{ \Illuminate\Support\Str::limit(strip_tags($item->body_html), 160) }}</span>
                </a>
            @endforeach
        </div>
    </section>
@endif
