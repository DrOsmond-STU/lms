@php
    [$tabLabel, , , $needsReauth] = \App\Modules\Settings\Services\SystemSettings::TABS[$tab];
    $action = $tab === 'beranda' ? route('admin.settings.landing.update') : route('admin.settings.update', $tab);
@endphp
<x-layouts.app :title="'Pengaturan — '.$tabLabel" workspace="admin">
    <x-slot:heading>Pengaturan Sistem</x-slot:heading>
    <x-slot:subtitle>Semua identitas, teks, dan aturan operasional diatur di sini — tanpa mengubah kode. Nilai keamanan hanya dapat diperketat dalam batas aman; setiap perubahan tercatat di jejak audit.</x-slot:subtitle>
    @include('settings._tabs', ['tab' => $tab, 'sub' => 'teks'])

    <form method="POST" action="{{ $action }}" class="max-w-3xl space-y-6" novalidate>
        @csrf
        @method('PUT')
        @foreach (collect($definitions)->groupBy(fn ($definition) => $definition['section'] ?? $tabLabel, true) as $section => $items)
            <fieldset class="card space-y-4 p-6" @disabled(! $canUpdate)>
                <legend class="sr-only">{{ $section }}</legend>
                <h2 class="card-title">{{ $section }}</h2>
                @foreach ($items as $key => $definition)
                    @php($field = \App\Modules\Settings\Services\SystemSettings::field($key))
                    @php($value = old($field, $values[$key]))
                    @if ($definition['type'] === 'bool')
                        <label class="flex items-center gap-2 text-sm font-semibold text-slate-800"><input type="checkbox" name="{{ $field }}" value="1" @checked($value)> {{ $definition['label'] }}</label>
                    @else
                        <div>
                            <label for="{{ $field }}" class="form-label">{{ $definition['label'] }}@isset($definition['min']) <span class="font-normal text-slate-500">({{ $definition['min'] }}–{{ $definition['max'] }})</span>@endisset</label>
                            @if ($definition['type'] === 'select')
                                <select id="{{ $field }}" name="{{ $field }}" class="form-select">@foreach ($definition['options'] as $option => $optionLabel)<option value="{{ $option }}" @selected($value === $option)>{{ $optionLabel }}</option>@endforeach</select>
                            @elseif ($definition['type'] === 'secret')
                                <input id="{{ $field }}" name="{{ $field }}" type="password" value="" autocomplete="new-password" maxlength="{{ $definition['max'] ?? 400 }}" placeholder="{{ $value !== '' ? '•••••••• (tersimpan)' : 'belum diisi' }}" class="form-input">
                            @elseif ($definition['type'] === 'text')
                                <textarea id="{{ $field }}" name="{{ $field }}" rows="{{ $definition['rows'] ?? 3 }}" maxlength="{{ $definition['max'] ?? 300 }}" class="form-input">{{ $value }}</textarea>
                            @else
                                <input id="{{ $field }}" name="{{ $field }}" type="{{ $definition['type'] === 'int' ? 'number' : ($definition['type'] === 'email' ? 'email' : 'text') }}" value="{{ $value }}" @isset($definition['min']) min="{{ $definition['min'] }}" max="{{ $definition['max'] }}" @endisset @if ($definition['type'] !== 'int' && isset($definition['max'])) maxlength="{{ $definition['max'] }}" @endif @if ($definition['required'] ?? false) required @endif class="form-input">
                            @endif
                            @isset($definition['help'])<p class="mt-1 text-xs text-slate-500">{{ $definition['help'] }}</p>@endisset
                        </div>
                    @endif
                    <x-form-error :field="$field" />
                @endforeach
            </fieldset>
        @endforeach
        @if ($tab === 'integrasi')
            @include('settings._integrasi')
        @endif
        @if ($canUpdate)
            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="btn-primary w-auto">Simpan {{ $tabLabel }}</button>
                @if ($needsReauth)<span class="text-xs text-slate-500">Menyimpan tab ini meminta konfirmasi kata sandi/MFA.</span>@endif
            </div>
        @else
            <p class="card p-4 text-sm text-slate-600">Anda hanya dapat melihat pengaturan ini.</p>
        @endif
    </form>
</x-layouts.app>
