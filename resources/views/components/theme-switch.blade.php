@props(['onbrand' => false])
@php($current = \App\Support\Ui\Theme::current() ?? 'system')
<div @class(['switch', 'switch-onbrand' => $onbrand]) role="group" aria-label="Tema tampilan" data-theme-switch>
    @foreach (['light' => ['sun', 'Terang'], 'dark' => ['moon', 'Gelap'], 'system' => ['monitor', 'Ikuti sistem']] as $mode => [$icon, $label])
        <button type="button" data-theme-set="{{ $mode }}" aria-pressed="{{ $current === $mode ? 'true' : 'false' }}" title="{{ $label }}"><x-icon :name="$icon" class="h-4 w-4" /><span class="sr-only">{{ $label }}</span></button>
    @endforeach
</div>
