<x-layouts.app title="Pengaturan Sistem" workspace="admin">
    <x-slot:heading>Pengaturan Sistem</x-slot:heading>
    <x-slot:subtitle>Nilai keamanan hanya dapat diperketat dalam batas aman. Semua perubahan tercatat di jejak audit.</x-slot:subtitle>

    @can('system_setting.update')
        <form method="POST" action="{{ route('admin.settings.update') }}" class="card max-w-2xl space-y-6 p-6" novalidate>
            @csrf
            @method('PUT')
            @foreach (collect($definitions)->groupBy('group', true) as $group => $items)
                <fieldset class="space-y-4">
                    <legend class="text-sm font-extrabold text-slate-800">{{ $group }}</legend>
                    @foreach ($items as $key => $definition)
                        @php($field = str_replace('.', '__', $key))
                        @if ($definition['type'] === 'bool')
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="{{ $field }}" value="1" @checked($values[$key])> {{ $definition['label'] }}</label>
                        @else
                            <div>
                                <label for="{{ $field }}" class="form-label">{{ $definition['label'] }} @isset($definition['min'])<span class="font-normal text-slate-500">({{ $definition['min'] }}–{{ $definition['max'] }})</span>@endisset</label>
                                <input id="{{ $field }}" name="{{ $field }}" type="{{ $definition['type'] === 'int' ? 'number' : ($definition['type'] === 'email' ? 'email' : 'text') }}" value="{{ old($field, $values[$key]) }}" @isset($definition['min']) min="{{ $definition['min'] }}" max="{{ $definition['max'] }}" @endisset class="form-input">
                            </div>
                        @endif
                        <x-form-error :field="$field" />
                    @endforeach
                </fieldset>
            @endforeach
            <button class="btn-primary w-auto">Simpan</button>
        </form>
    @else
        <p class="card p-6 text-sm text-slate-600">Anda hanya dapat melihat pengaturan.</p>
    @endcan
</x-layouts.app>
