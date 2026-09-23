@props(['field'])
@error($field)
    <p class="mt-1 text-xs font-semibold text-rose-700" role="alert">{{ $message }}</p>
@enderror
