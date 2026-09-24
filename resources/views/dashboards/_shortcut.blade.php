{{-- Kartu pintasan melayang: $href, $icon, $title, $hint --}}
<a href="{{ $href }}" class="card card-link block p-5">
    <span class="tile-icon mb-3"><x-icon :name="$icon" class="h-[18px] w-[18px]" /></span>
    <span class="block text-[15px] leading-5 font-semibold text-slate-800">{{ $title }}</span>
    <span class="mt-1 block text-xs text-slate-500">{{ $hint }}</span>
</a>
