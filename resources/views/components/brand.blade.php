@props(['sub' => null])
{{-- Logo & nama merek dari Pengaturan Sistem → Umum. --}}
<span class="brand-mark"><x-icon name="graduation" class="h-6 w-6" /></span>
<span class="min-w-0"><span class="brand-name block">{{ setting('branding.short_name') }}</span><span class="brand-tag block">{{ $sub ?? setting('branding.tagline') }}</span></span>
