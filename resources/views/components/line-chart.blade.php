@props(['series', 'label' => 'Grafik'])
{{-- Grafik garis SVG sisi server: $series = [['label' => 'Jan', 'value' => 3], ...]. Tanpa atribut style (CSP). --}}
@php
    $w = 680; $h = 190; $pad = 40; $base = 160;
    $values = array_map(fn ($p) => (float) $p['value'], $series);
    $count = max(count($values), 2);
    $max = max(max($values ?: [0]) * 1.15, 4);
    $x = fn (int $i) => $pad + $i * (($w - $pad - 16) / ($count - 1));
    $y = fn (float $v) => $base - ($v / $max) * 144;
    $points = collect($values)->map(fn ($v, $i) => round($x($i), 1).','.round($y($v), 1))->implode(' ');
    $last = count($values) - 1;
    $peak = $values ? array_search(max($values), $values, true) : 0;
    $ticks = [0, .25, .5, .75, 1];
@endphp
<svg viewBox="0 0 {{ $w }} {{ $h }}" class="h-auto w-full" role="img" aria-label="{{ $label }}">
    <g class="stroke-slate-200" stroke-width="1">
        @foreach ($ticks as $t)<line x1="{{ $pad }}" y1="{{ round($y($max * $t), 1) }}" x2="{{ $w - 16 }}" y2="{{ round($y($max * $t), 1) }}"/>@endforeach
    </g>
    <g text-anchor="end" class="fill-slate-500 font-mono" font-size="10">
        @foreach ($ticks as $t)<text x="{{ $pad - 8 }}" y="{{ round($y($max * $t) + 4, 1) }}">{{ (int) round($max * $t) }}</text>@endforeach
    </g>
    @if ($values)
        <polygon points="{{ $points }} {{ round($x($last), 1) }},{{ $base }} {{ $pad }},{{ $base }}" class="fill-brand-500" opacity=".18"/>
        <polyline points="{{ $points }}" fill="none" class="stroke-brand-500" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
        <circle cx="{{ round($x($peak), 1) }}" cy="{{ round($y($values[$peak]), 1) }}" r="4" class="fill-surface stroke-brand-700" stroke-width="2.5"/>
        <circle cx="{{ round($x($last), 1) }}" cy="{{ round($y($values[$last]), 1) }}" r="5" class="fill-brand-700"/>
        <text x="{{ round($x($last) - 8, 1) }}" y="{{ round($y($values[$last]) - 9, 1) }}" text-anchor="end" class="fill-brand-700 font-mono" font-size="11" font-weight="600">{{ (int) $values[$last] }}</text>
    @endif
    <g text-anchor="middle" class="fill-slate-500 font-mono" font-size="10">
        @foreach ($series as $i => $p)<text x="{{ round($x($i), 1) }}" y="182">{{ $p['label'] }}</text>@endforeach
    </g>
</svg>
