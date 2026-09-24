<div class="card p-5">
    <p class="text-xs font-bold tracking-wide text-slate-500 uppercase">{{ $label }}</p>
    <p class="mt-2 text-2xl font-extrabold text-slate-800">{{ $value }}</p>
    @isset($link)<a href="{{ $link }}" class="mt-1 inline-block text-xs font-bold text-brand-700 hover:underline">{{ $linkLabel ?? 'Lihat' }}</a>@endisset
</div>
