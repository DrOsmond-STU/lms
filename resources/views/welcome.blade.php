@php
    use App\Modules\Catalog\Models\Program;
    use App\Modules\Cms\Support\ProgramFilter;

    $theme = \App\Support\Ui\Theme::current();
    $registrationOpen = (bool) config('security.registration.enabled');
    $slideItems = $slides->isNotEmpty()
        ? $slides->map(fn ($s) => [
            'eyebrow' => $s->eyebrow, 'title' => $s->title, 'subtitle' => $s->subtitle,
            'cta_label' => $s->cta_label, 'cta_url' => $s->cta_url,
            'image' => $s->image_path ? route('landing.image.slide', ['slide' => $s, 'v' => $s->updated_at?->timestamp]) : null,
        ])->all()
        : [
            ['eyebrow' => 'Pelatihan & Sertifikasi', 'title' => 'Tingkatkan kompetensi Anda bersama STU LMS', 'subtitle' => 'Program pelatihan bersertifikat Internasional & BNSP — belajar fleksibel, ujian yang adil, dan sertifikat digital yang dapat diverifikasi publik.', 'cta_label' => 'Lihat Pelatihan', 'cta_url' => '#pelatihan', 'image' => asset('images/landing/slide-1.webp')],
            ['eyebrow' => 'Asesmen daring', 'title' => 'Ujian terukur, hasil langsung terlihat', 'subtitle' => 'Waktu ujian dijaga server, soal diacak untuk setiap peserta, dan penilaian otomatis yang transparan.', 'cta_label' => 'Mulai Belajar', 'cta_url' => $registrationOpen ? route('register') : route('login'), 'image' => asset('images/landing/slide-2.webp')],
            ['eyebrow' => 'Sertifikat terverifikasi', 'title' => 'Sertifikat digital yang dapat dibuktikan keasliannya', 'subtitle' => 'Setiap sertifikat bertanda tangan digital dengan QR & kode unik — perusahaan dan kampus dapat memverifikasinya kapan saja.', 'cta_label' => 'Verifikasi Sertifikat', 'cta_url' => route('verification.form'), 'image' => asset('images/landing/slide-3.webp')],
        ];
    $visibleCount = $cards->where('visible', true)->count();
    $jenisLink = fn (?string $value) => url('/').'?'.http_build_query(array_filter(array_merge($filter->active, ['jenis' => $value]))).'#pelatihan';
    $contacts = array_filter([
        ['pin', 'Alamat', $profile->address, null],
        ['mail', 'Email', $profile->email, $profile->email ? 'mailto:'.$profile->email : null],
        ['phone', 'Telepon', $profile->phone, $profile->phone ? 'tel:'.preg_replace('/[^0-9+]/', '', $profile->phone) : null],
        ['chat-bubble', 'WhatsApp', $profile->whatsapp, $profile->whatsappLink()],
        ['clock', 'Jam layanan', $profile->business_hours, null],
        ['globe', 'Situs web', $profile->website_url ? preg_replace('#^https://#', '', $profile->website_url) : null, $profile->website_url],
    ], fn ($row) => filled($row[2]));
@endphp
<!DOCTYPE html>
<html lang="id" @if ($theme) data-theme="{{ $theme }}" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#062b63">
    <title>{{ config('app.name') }} — Platform Pelatihan &amp; Sertifikasi</title>
    <meta name="description" content="STU LMS: pelatihan sertifikasi Internasional & BNSP dengan sertifikat yang dapat diverifikasi publik. Dikelola oleh {{ $profile->company_name }}.">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-slate-700">
<a href="#pelatihan" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-surface focus:px-4 focus:py-2">Langsung ke daftar pelatihan</a>
<x-environment-banner />

{{-- ===================== Slide ===================== --}}
<section class="landing-hero" data-slider aria-roledescription="carousel" aria-label="Sorotan STU LMS">
    <header class="landing-nav mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-4 sm:px-6">
        <a href="{{ route('home') }}" class="flex items-center gap-3">
            <span class="brand-mark"><x-icon name="graduation" class="h-6 w-6" /></span>
            <span><span class="brand-name block">STU LMS</span><span class="brand-tag block">Pelatihan &amp; Sertifikasi</span></span>
        </a>
        <nav class="hidden items-center gap-1 text-sm font-semibold lg:flex" aria-label="Navigasi beranda">
            <a href="#pelatihan" class="rounded-lg px-3 py-2 hover:bg-white/10">Pelatihan</a>
            @if ($testimonials->isNotEmpty())<a href="#testimoni" class="rounded-lg px-3 py-2 hover:bg-white/10">Testimoni</a>@endif
            @if ($partners->isNotEmpty())<a href="#mitra" class="rounded-lg px-3 py-2 hover:bg-white/10">Mitra</a>@endif
            <a href="#tentang" class="rounded-lg px-3 py-2 hover:bg-white/10">Tentang Kami</a>
            <a href="{{ route('verification.form') }}" class="rounded-lg px-3 py-2 hover:bg-white/10">Verifikasi Sertifikat</a>
        </nav>
        <div class="flex items-center gap-2">
            <span class="hidden sm:inline-flex"><x-theme-switch onbrand /></span>
            @auth
                <a href="{{ route('dashboard') }}" class="btn-primary w-auto">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="btn-onhero">Masuk</a>
                @if ($registrationOpen)<a href="{{ route('register') }}" class="btn-primary hidden w-auto sm:inline-flex">Daftar</a>@endif
            @endauth
            <details class="landing-menu relative lg:hidden">
                <summary class="sidebar-icon-btn cursor-pointer list-none" aria-label="Menu"><x-icon name="menu" class="h-[18px] w-[18px]" /></summary>
                <div class="card absolute right-0 z-30 mt-2 flex w-56 flex-col p-2 text-sm font-semibold text-slate-800">
                    <a href="#pelatihan" class="rounded-lg px-3 py-2 hover:bg-slate-100">Pelatihan</a>
                    @if ($testimonials->isNotEmpty())<a href="#testimoni" class="rounded-lg px-3 py-2 hover:bg-slate-100">Testimoni</a>@endif
                    @if ($partners->isNotEmpty())<a href="#mitra" class="rounded-lg px-3 py-2 hover:bg-slate-100">Mitra</a>@endif
                    <a href="#tentang" class="rounded-lg px-3 py-2 hover:bg-slate-100">Tentang Kami</a>
                    <a href="{{ route('verification.form') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Verifikasi Sertifikat</a>
                    @guest @if ($registrationOpen)<a href="{{ route('register') }}" class="rounded-lg px-3 py-2 text-link hover:bg-slate-100">Daftar Peserta</a>@endif @endguest
                    <div class="px-3 py-2"><x-theme-switch /></div>
                </div>
            </details>
        </div>
    </header>

    <div class="landing-slides">
        @foreach ($slideItems as $i => $slide)
            <div @class(['landing-slide', 'is-active' => $i === 0]) data-slide role="group" aria-roledescription="slide" aria-label="{{ $i + 1 }} dari {{ count($slideItems) }}" @if ($i > 0) aria-hidden="true" inert @endif>
                @if ($slide['image'])<img src="{{ $slide['image'] }}" alt="" class="landing-slide-img" @if ($i > 0) loading="lazy" @endif>@endif
                <div class="landing-overlay"></div>
                <div class="relative z-10 mx-auto max-w-6xl px-4 pt-28 pb-28 sm:px-6 md:pt-36 md:pb-36">
                    <div class="max-w-2xl">
                        @if ($slide['eyebrow'])<p class="hero-eyebrow">{{ $slide['eyebrow'] }}</p>@endif
                        @if ($i === 0)
                            <h1 class="landing-title">{{ $slide['title'] }}</h1>
                        @else
                            <h2 class="landing-title">{{ $slide['title'] }}</h2>
                        @endif
                        @if ($slide['subtitle'])<p class="hero-sub mt-4 text-base md:text-lg md:leading-8">{{ $slide['subtitle'] }}</p>@endif
                        <div class="mt-8 flex flex-wrap gap-3">
                            @if ($slide['cta_label'] && $slide['cta_url'])<a href="{{ $slide['cta_url'] }}" class="btn-primary w-auto px-6">{{ $slide['cta_label'] }}<x-icon name="arrow-right" class="h-4 w-4" /></a>@endif
                            @guest @if ($registrationOpen)<a href="{{ route('register') }}" class="btn-onhero px-6">Daftar Gratis</a>@endif @endguest
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if (count($slideItems) > 1)
        <div class="landing-slider-controls">
            <button type="button" class="sidebar-icon-btn" data-slide-prev aria-label="Slide sebelumnya"><x-icon name="chevron-left" class="h-4 w-4" /></button>
            <div class="flex items-center gap-2">
                @foreach ($slideItems as $i => $slide)
                    <button type="button" class="landing-dot" data-slide-to="{{ $i }}" aria-label="Tampilkan slide {{ $i + 1 }}" aria-current="{{ $i === 0 ? 'true' : 'false' }}"></button>
                @endforeach
            </div>
            <button type="button" class="sidebar-icon-btn" data-slide-next aria-label="Slide berikutnya"><x-icon name="chevron-right" class="h-4 w-4" /></button>
        </div>
    @endif
</section>

<main>
    {{-- Keunggulan (menumpang di bawah slide) --}}
    <div class="page-overlap relative z-20 mx-auto max-w-6xl px-4 sm:px-6">
        <div class="grid gap-4 md:grid-cols-3">
            @foreach ([
                ['book', 'Belajar terarah', 'Modul, video, materi PDF, dan kuis dengan progres yang tersimpan otomatis.'],
                ['clipboard', 'Ujian yang adil', 'Waktu dijaga server, soal diacak per peserta, dan penilaian otomatis.'],
                ['shield', 'Sertifikat terverifikasi', 'PDF bertanda tangan digital dengan QR & kode verifikasi publik.'],
            ] as [$icon, $title, $text])
                <div class="card tile">
                    <span class="tile-icon"><x-icon :name="$icon" class="h-[18px] w-[18px]" /></span>
                    <h2 class="mt-1 text-[17px] font-semibold text-slate-800">{{ $title }}</h2>
                    <p class="text-sm text-slate-500">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ===================== Pelatihan ===================== --}}
    <section id="pelatihan" class="mx-auto max-w-6xl scroll-mt-6 px-4 pt-16 sm:px-6" aria-labelledby="pelatihan-heading">
        <div class="section-head">
            <div>
                <p class="label-caps text-link">Pelatihan tersedia</p>
                <h2 id="pelatihan-heading" class="mt-1 font-display text-2xl font-bold text-slate-800 md:text-3xl">Pilih pelatihan, langsung bergabung</h2>
            </div>
            <span class="sub"><span data-program-count>{{ $visibleCount }}</span> dari {{ $cards->count() }} program</span>
        </div>

        <form method="GET" action="{{ url('/') }}#pelatihan" class="card mb-6 p-4" data-program-filter role="search" aria-label="Filter pelatihan">
            <input type="hidden" name="jenis" value="{{ $filter->active['jenis'] ?? '' }}">
            <div class="mb-4 flex flex-wrap items-center gap-2" role="group" aria-label="Jenis kompetensi">
                <span class="mr-1 text-sm font-semibold text-slate-800">Jenis kompetensi:</span>
                @foreach (['' => 'Semua', 'international' => 'Internasional', 'bnsp' => 'BNSP'] as $value => $label)
                    <a href="{{ $jenisLink($value ?: null) }}" class="filter-pill" data-jenis-pill="{{ $value }}" aria-pressed="{{ ($filter->active['jenis'] ?? '') === $value ? 'true' : 'false' }}">{{ $label }}</a>
                @endforeach
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[repeat(4,minmax(0,1fr))_auto]">
                <div><label for="f-topik" class="form-label">Kategori / topik</label>
                    <select id="f-topik" name="topik" class="form-select"><option value="">Semua topik</option>@foreach ($topics as $topic)<option value="{{ $topic }}" @selected(($filter->active['topik'] ?? '') === $topic)>{{ \Illuminate\Support\Str::title($topic) }}</option>@endforeach</select></div>
                <div><label for="f-harga" class="form-label">Harga</label>
                    <select id="f-harga" name="harga" class="form-select"><option value="">Semua harga</option>@foreach (ProgramFilter::PRICES as $value => $label)<option value="{{ $value }}" @selected(($filter->active['harga'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div><label for="f-jadwal" class="form-label">Waktu mulai</label>
                    <select id="f-jadwal" name="jadwal" class="form-select"><option value="">Kapan saja</option>@foreach (ProgramFilter::SCHEDULES as $value => $label)<option value="{{ $value }}" @selected(($filter->active['jadwal'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div><label for="f-durasi" class="form-label">Durasi</label>
                    <select id="f-durasi" name="durasi" class="form-select"><option value="">Semua durasi</option>@foreach (ProgramFilter::DURATIONS as $value => $label)<option value="{{ $value }}" @selected(($filter->active['durasi'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-secondary" data-filter-submit>Terapkan</button>
                    <a href="{{ url('/') }}#pelatihan" class="btn-mini min-h-10" data-filter-reset>Reset</a>
                </div>
            </div>
        </form>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($cards as $card)
                @php($program = $card['program'])
                <article class="card program-card flex flex-col overflow-hidden" data-program-card
                    data-jenis="{{ $card['buckets']['jenis'] }}" data-topik="{{ implode('|', $card['buckets']['topik']) }}"
                    data-harga="{{ $card['buckets']['harga'] }}" data-jadwal="{{ $card['buckets']['jadwal'] ?? '' }}" data-durasi="{{ $card['buckets']['durasi'] }}"
                    @unless ($card['visible']) hidden @endunless>
                    <div @class(['program-band', 'program-band-bnsp' => $program->category === 'bnsp'])>
                        <span class="chip bg-white/15 text-white">{{ Program::CATEGORIES[$program->category] ?? $program->category }}</span>
                        @if ($program->level)<span class="text-xs font-semibold text-white/80">{{ Program::LEVELS[$program->level] ?? $program->level }}</span>@endif
                    </div>
                    <div class="flex flex-1 flex-col p-5">
                        <h3 class="font-display text-[17px] leading-6 font-bold text-slate-800">{{ $program->name }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ $program->provider_name }}</p>
                        <ul class="mt-4 space-y-1.5 text-sm text-slate-600">
                            <li class="flex items-center gap-2"><x-icon name="clock" class="h-4 w-4 text-slate-400" />{{ $program->duration_hours }} jam · {{ Program::MODES[$program->default_mode] ?? $program->default_mode }}</li>
                            <li class="flex items-center gap-2"><x-icon name="calendar" class="h-4 w-4 text-slate-400" />
                                @if ($card['next'])Mulai {{ $card['next']->translatedFormat('d M Y') }} · {{ $program->open_classes_count }} kelas dibuka @else Jadwal segera diumumkan @endif
                            </li>
                            @if ($card['buckets']['topik'])<li class="flex flex-wrap gap-1.5 pt-1">@foreach (array_slice($card['buckets']['topik'], 0, 3) as $topic)<span class="badge bg-slate-100 text-slate-600">{{ $topic }}</span>@endforeach</li>@endif
                        </ul>
                        <div class="mt-auto flex items-center justify-between gap-3 pt-5">
                            <span class="font-mono text-base font-semibold text-slate-800">{{ $program->priceLabel() }}</span>
                            <div class="flex items-center gap-2">
                                <a href="{{ route('catalog.public.show', $program->slug) }}" class="btn-mini">Detail</a>
                                @if ($program->open_classes_count > 0)
                                    <a href="{{ $joinAsParticipant ? route('catalog.participant.show', $program->slug) : route('catalog.public.show', $program->slug) }}" class="btn-primary min-h-9 w-auto px-4 py-2 text-[13px]">Ikut Pelatihan</a>
                                @else
                                    <span class="chip chip-neutral">Segera dibuka</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
        <div @class(['card mt-2 p-10 text-center']) data-program-empty @if ($visibleCount > 0) hidden @endif>
            <p class="font-semibold text-slate-800">{{ $cards->isEmpty() ? 'Program pelatihan akan segera tersedia.' : 'Tidak ada pelatihan yang cocok dengan filter ini.' }}</p>
            @if ($cards->isNotEmpty())<p class="mt-1 text-sm text-slate-500">Coba ubah atau reset filter.</p>@endif
        </div>
        <div class="mt-6 text-center"><a href="{{ route('catalog.public') }}" class="btn-secondary">Lihat katalog lengkap<x-icon name="arrow-right" class="h-4 w-4" /></a></div>
    </section>

    {{-- ===================== Cara bergabung ===================== --}}
    <section class="mx-auto max-w-6xl px-4 pt-16 sm:px-6" aria-labelledby="alur-heading">
        <div class="section-head"><h2 id="alur-heading">Cara bergabung</h2><span class="sub">Empat langkah menuju sertifikat</span></div>
        <ol class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['Daftar akun', 'Buat akun peserta gratis dan verifikasi email Anda.'],
                ['Pilih kelas', 'Tentukan program & batch yang sesuai jadwal, lalu klik "Ikut Pelatihan".'],
                ['Belajar & ujian', 'Ikuti materi, kerjakan kuis, dan selesaikan ujian akhir.'],
                ['Terima sertifikat', 'Sertifikat digital terbit dan dapat diverifikasi publik.'],
            ] as $i => [$title, $text])
                <li class="card flex gap-3 p-4">
                    <span class="step-no">{{ $i + 1 }}</span>
                    <span><span class="block text-sm font-semibold text-slate-800">{{ $title }}</span><span class="mt-0.5 block text-[13px] leading-5 text-slate-500">{{ $text }}</span></span>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- ===================== Testimoni ===================== --}}
    @if ($testimonials->isNotEmpty())
        <section id="testimoni" class="mx-auto max-w-6xl scroll-mt-6 px-4 pt-16 sm:px-6" aria-labelledby="testimoni-heading">
            <div class="section-head">
                <div><p class="label-caps text-link">Testimoni</p><h2 id="testimoni-heading" class="mt-1 font-display text-2xl font-bold text-slate-800 md:text-3xl">Apa kata peserta & mitra kami</h2></div>
            </div>
            <div class="testimonial-track">
                @foreach ($testimonials as $item)
                    <figure class="card testimonial-card flex flex-col p-6">
                        <div class="flex items-center justify-between gap-2">
                            <span class="flex gap-0.5 text-amber-400" aria-label="Nilai {{ $item->rating }} dari 5">
                                @for ($s = 1; $s <= 5; $s++)<x-icon name="star" @class(['h-4 w-4', 'fill-current' => $s <= $item->rating, 'text-slate-300' => $s > $item->rating]) />@endfor
                            </span>
                            @if ($item->is_sample)<span class="chip chip-medium" title="Konten contoh untuk uji coba">Contoh</span>@endif
                        </div>
                        <blockquote class="mt-4 flex-1 text-[15px] leading-7 text-slate-700"><span class="quote-mark" aria-hidden="true">“</span>{{ $item->quote }}</blockquote>
                        <figcaption class="mt-5 flex items-center gap-3 border-t border-slate-200 pt-4">
                            <span class="sidebar-avatar bg-brand-100 text-brand-700" aria-hidden="true">{{ $item->initials() }}</span>
                            <span class="min-w-0">
                                <span class="block truncate font-semibold text-slate-800">{{ $item->name }}</span>
                                <span class="block truncate text-xs text-slate-500">{{ collect([$item->role_title, $item->organization_name])->filter()->implode(' · ') }}</span>
                                @if ($item->program_name)<span class="block truncate text-xs text-link">{{ $item->program_name }}</span>@endif
                            </span>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ===================== Mitra (logo berjalan) ===================== --}}
    @if ($partners->isNotEmpty())
        <section id="mitra" class="scroll-mt-6 pt-16" aria-labelledby="mitra-heading">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="section-head justify-center text-center"><div><p class="label-caps text-link">Mitra pengguna</p><h2 id="mitra-heading" class="mt-1 font-display text-2xl font-bold text-slate-800">Dipercaya perusahaan & perguruan tinggi</h2></div></div>
            </div>
            <div class="marquee" data-marquee>
                <ul class="marquee-track">
                    @foreach ([false, true] as $duplicate)
                        @foreach ($partners as $partner)
                            <li @if ($duplicate) aria-hidden="true" class="marquee-dup" @endif>
                                @if ($partner->website_url && ! $duplicate)
                                    <a href="{{ $partner->website_url }}" target="_blank" rel="noopener noreferrer nofollow" @class(['partner-chip', 'has-logo' => $partner->logo_path])>@include('cms._partner-mark', ['partner' => $partner])</a>
                                @else
                                    <span @class(['partner-chip', 'has-logo' => $partner->logo_path])>@include('cms._partner-mark', ['partner' => $partner])</span>
                                @endif
                            </li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- ===================== Ajakan ===================== --}}
    <section class="mx-auto max-w-6xl px-4 pt-16 sm:px-6">
        <div class="hero relative overflow-hidden rounded-[20px] px-6 py-10 sm:px-10">
            <div class="relative z-10 flex flex-wrap items-center justify-between gap-6">
                <div class="max-w-xl">
                    <h2 class="font-display text-2xl leading-tight font-bold text-white md:text-3xl">Siap meningkatkan kompetensi Anda?</h2>
                    <p class="hero-sub mt-2">Daftar sekarang dan ikuti pelatihan bersertifikat — atau verifikasi keaslian sertifikat STU yang Anda terima.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    @guest @if ($registrationOpen)<a href="{{ route('register') }}" class="btn-primary w-auto px-6">Daftar Peserta</a>@endif @endguest
                    <a href="{{ route('verification.form') }}" class="btn-onhero px-6">Verifikasi Sertifikat</a>
                </div>
            </div>
        </div>
    </section>

    {{-- ===================== Tentang / pemilik situs ===================== --}}
    <section id="tentang" class="mx-auto max-w-6xl scroll-mt-6 px-4 pt-16 pb-16 sm:px-6" aria-labelledby="tentang-heading">
        <div class="grid gap-6 lg:grid-cols-5">
            <div class="lg:col-span-3">
                <p class="label-caps text-link">Tentang kami</p>
                <h2 id="tentang-heading" class="mt-1 font-display text-2xl font-bold text-slate-800 md:text-3xl">{{ $profile->company_name }}</h2>
                @if ($profile->tagline)<p class="mt-1 font-semibold text-slate-600">{{ $profile->tagline }}</p>@endif
                @if ($profile->about)<p class="mt-4 leading-7 text-slate-600">{{ $profile->about }}</p>@endif
                <dl class="mt-6 grid gap-3 sm:grid-cols-3">
                    <div class="card p-4"><dt class="label-caps">Program terbit</dt><dd class="tile-num mt-1">{{ $stats['programs'] }}</dd></div>
                    <div class="card p-4"><dt class="label-caps">Kelas dibuka</dt><dd class="tile-num mt-1">{{ $stats['openClasses'] }}</dd></div>
                    <div class="card p-4"><dt class="label-caps">Sertifikat aktif</dt><dd class="tile-num mt-1">{{ number_format($stats['certificates'], 0, ',', '.') }}</dd></div>
                </dl>
            </div>
            <div class="card p-6 lg:col-span-2">
                <h3 class="card-title">Hubungi kami</h3>
                <p class="card-sub">Informasi pemilik & pengelola situs</p>
                <ul class="space-y-4 text-sm">
                    <li class="flex gap-3"><span class="tile-icon"><x-icon name="building" class="h-4 w-4" /></span><span><span class="block text-xs text-slate-500">Pemilik situs</span><span class="font-semibold text-slate-800">{{ $profile->company_name }}</span></span></li>
                    @foreach ($contacts as [$icon, $label, $value, $href])
                        <li class="flex gap-3"><span class="tile-icon"><x-icon :name="$icon" class="h-4 w-4" /></span><span class="min-w-0"><span class="block text-xs text-slate-500">{{ $label }}</span>
                            @if ($href)<a href="{{ $href }}" class="font-semibold break-words text-link hover:underline" @if (str_starts_with($href, 'https://')) target="_blank" rel="noopener noreferrer" @endif>{{ $value }}</a>@else<span class="font-semibold whitespace-pre-line text-slate-800">{{ $value }}</span>@endif
                        </span></li>
                    @endforeach
                </ul>
                @if ($profile->linkedin_url || $profile->instagram_url)
                    <div class="mt-5 flex gap-2 border-t border-slate-200 pt-4">
                        @if ($profile->linkedin_url)<a href="{{ $profile->linkedin_url }}" target="_blank" rel="noopener noreferrer" class="btn-mini">LinkedIn</a>@endif
                        @if ($profile->instagram_url)<a href="{{ $profile->instagram_url }}" target="_blank" rel="noopener noreferrer" class="btn-mini">Instagram</a>@endif
                    </div>
                @endif
            </div>
        </div>
    </section>
</main>

<footer class="landing-footer">
    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
        <div>
            <div class="flex items-center gap-3"><span class="brand-mark"><x-icon name="graduation" class="h-6 w-6" /></span><span><span class="brand-name block">STU LMS</span><span class="brand-tag block">Pelatihan &amp; Sertifikasi</span></span></div>
            <p class="mt-4 text-sm leading-6 text-brand-300">Platform pelatihan & sertifikasi kompetensi milik {{ $profile->company_name }}.</p>
        </div>
        <div>
            <p class="footer-head">Jelajahi</p>
            <ul class="footer-links">
                <li><a href="#pelatihan">Pelatihan tersedia</a></li>
                <li><a href="{{ route('catalog.public') }}">Katalog program</a></li>
                <li><a href="{{ route('verification.form') }}">Verifikasi sertifikat</a></li>
                <li><a href="{{ route('login') }}">Masuk</a></li>
                @if ($registrationOpen)<li><a href="{{ route('register') }}">Daftar peserta</a></li>@endif
            </ul>
        </div>
        <div>
            <p class="footer-head">Legal</p>
            <ul class="footer-links">
                <li><a href="{{ route('legal.privacy') }}">Kebijakan Privasi</a></li>
                <li><a href="{{ route('legal.terms') }}">Syarat &amp; Ketentuan</a></li>
            </ul>
        </div>
        <div>
            <p class="footer-head">Pemilik situs</p>
            <ul class="footer-links">
                <li class="font-semibold text-white">{{ $profile->company_name }}</li>
                @if ($profile->address)<li class="whitespace-pre-line">{{ $profile->address }}</li>@endif
                @if ($profile->email)<li><a href="mailto:{{ $profile->email }}">{{ $profile->email }}</a></li>@endif
                @if ($profile->phone)<li>{{ $profile->phone }}</li>@endif
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10 py-5 text-center text-xs text-brand-300">&copy; {{ now()->year }} {{ $profile->company_name }}. Seluruh hak cipta dilindungi.</div>
</footer>
</body>
</html>
