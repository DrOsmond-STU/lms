{{-- Ubin KPI: $label, $value, $icon, opsional $unit, $trend (teks), $tone (good|bad|flat), $note, $link --}}
@php($tag = isset($link) ? 'a' : 'article')
<{{ $tag }} @isset($link) href="{{ $link }}" @endisset @class(['card tile', 'card-link' => isset($link)])>
    <div class="tile-head">
        <span class="label-caps">{{ $label }}</span>
        <span class="tile-icon"><x-icon :name="$icon" class="h-[18px] w-[18px]" /></span>
    </div>
    <div class="tile-num">{{ $value }}@isset($unit)<span class="tile-unit">{{ $unit }}</span>@endisset</div>
    @isset($trend)
        <span class="tile-trend {{ $tone ?? 'flat' }}">
            @if (($tone ?? 'flat') === 'good')<x-icon name="up" class="h-3 w-3" />@elseif (($tone ?? 'flat') === 'bad')<x-icon name="down" class="h-3 w-3" />@endif
            {{ $trend }}
        </span>
    @endisset
    @isset($note)<div class="tile-note">{{ $note }}</div>@endisset
</{{ $tag }}>
