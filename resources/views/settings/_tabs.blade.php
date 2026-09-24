{{-- Navigasi Pengaturan Sistem. $tab = tab aktif; $sub = sub-tab Beranda (teks|slide|testimoni|mitra). --}}
@php($user = auth()->user())
<nav class="tabs mb-3" aria-label="Pengaturan sistem">
    @foreach (\App\Modules\Settings\Services\SystemSettings::TABS as $slug => [$label, $viewPermission])
        @continue(! $user->can($viewPermission))
        <a href="{{ $slug === 'pemilik' ? route('admin.settings.owner') : route('admin.settings.tab', $slug) }}" @if ($tab === $slug) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
@if ($tab === 'beranda')
    <nav class="mb-6 flex flex-wrap items-center gap-2" aria-label="Konten beranda">
        @foreach (['teks' => [route('admin.settings.tab', 'beranda'), 'Teks & judul'], 'slide' => [route('admin.landing.slides.index'), 'Slide'], 'testimoni' => [route('admin.landing.testimonials.index'), 'Testimoni'], 'mitra' => [route('admin.landing.partners.index'), 'Mitra pengguna']] as $key => [$href, $label])
            <a href="{{ $href }}" class="filter-pill" aria-pressed="{{ ($sub ?? 'teks') === $key ? 'true' : 'false' }}">{{ $label }}</a>
        @endforeach
        <a href="{{ route('home') }}" target="_blank" rel="noopener" class="btn-mini ml-auto">Lihat beranda ↗</a>
    </nav>
@else
    <div class="mb-6"></div>
@endif
