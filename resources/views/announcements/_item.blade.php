{{-- Satu pengumuman. $item: Announcement --}}
<article class="card p-5" aria-labelledby="ann-{{ $item->id }}">
    <div class="flex flex-wrap items-center gap-2 text-xs">
        @if ($item->is_pinned)<span class="badge bg-amber-50 text-amber-800">Disematkan</span>@endif
        <span class="badge bg-slate-100 text-slate-700">{{ $item->scope === 'class' ? 'Kelas: '.($item->courseClass?->batch_name ?? '') : ($item->scope === 'organization' ? 'Organisasi: '.($item->organization?->name ?? '') : 'Seluruh platform') }}</span>
        <span class="text-slate-500">{{ $item->publish_at->timezone(display_tz())->translatedFormat('d M Y H:i') }} {{ tz_label() }}@if ($item->author) · {{ $item->author->name }}@endif</span>
    </div>
    <h2 id="ann-{{ $item->id }}" class="mt-2 font-extrabold text-slate-800">{{ $item->title }}</h2>
    <div class="prose-content mt-2 text-sm">@include('components.safe-html', ['html' => $item->body_html])</div>
    @if ($item->expires_at)<p class="mt-2 text-xs text-slate-500">Berlaku sampai {{ $item->expires_at->timezone(display_tz())->translatedFormat('d M Y H:i') }}</p>@endif
</article>
