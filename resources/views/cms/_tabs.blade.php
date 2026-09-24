<nav class="tabs mb-6" aria-label="Konten beranda">
    @foreach (['admin.landing.slides.index' => ['admin.landing.slides.*', 'Slide'], 'admin.landing.testimonials.index' => ['admin.landing.testimonials.*', 'Testimoni'], 'admin.landing.partners.index' => ['admin.landing.partners.*', 'Mitra Pengguna'], 'admin.landing.profile.edit' => ['admin.landing.profile.*', 'Profil Situs']] as $routeName => [$pattern, $label])
        <a href="{{ route($routeName) }}" @if (request()->routeIs($pattern)) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
    <a href="{{ route('home') }}" target="_blank" rel="noopener" class="ml-auto">Lihat beranda ↗</a>
</nav>
