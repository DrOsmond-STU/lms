@php($theme = \App\Support\Ui\Theme::current())
<!DOCTYPE html>
<html lang="id" @if ($theme) data-theme="{{ $theme }}" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#062b63">
    <title>{{ config('app.name') }} — Platform Pelatihan &amp; Sertifikasi</title>
    <meta name="description" content="STU LMS: pelatihan sertifikasi Internasional & BNSP dengan sertifikat yang dapat diverifikasi publik.">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col font-sans text-slate-700">
<x-environment-banner />
<div class="hero relative overflow-hidden rounded-b-[20px]">
    <header class="relative z-10 mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
        <a href="{{ route('home') }}" class="flex items-center gap-3">
            <span class="brand-mark"><x-icon name="graduation" class="h-6 w-6" /></span>
            <span><span class="brand-name block">STU LMS</span><span class="brand-tag block">Pelatihan &amp; Sertifikasi</span></span>
        </a>
        <nav class="flex flex-wrap items-center gap-2 text-sm font-semibold" aria-label="Navigasi publik">
            <a href="{{ route('catalog.public') }}" class="rounded-lg px-3 py-2 hover:bg-white/10">Program</a>
            <a href="{{ route('verification.form') }}" class="rounded-lg px-3 py-2 hover:bg-white/10">Verifikasi Sertifikat</a>
            <x-theme-switch onbrand />
        </nav>
    </header>
    <svg class="pointer-events-none absolute top-24 -right-10 hidden h-[140px] w-[320px] text-white md:block" viewBox="0 0 320 140" aria-hidden="true" preserveAspectRatio="none">
        <g fill="currentColor">
            <rect x="0" y="0" width="52" height="24" rx="6" opacity=".14"/><rect x="58" y="0" width="24" height="24" rx="6" opacity=".22"/><rect x="88" y="0" width="24" height="24" rx="6" opacity=".30"/>
            <rect x="0" y="30" width="24" height="24" rx="6" opacity=".10"/><rect x="30" y="30" width="24" height="24" rx="6" opacity=".18"/><rect x="60" y="30" width="52" height="24" rx="6" opacity=".26"/>
            <rect x="0" y="60" width="24" height="24" rx="6" opacity=".08"/><rect x="30" y="60" width="24" height="24" rx="6" opacity=".14"/><rect x="60" y="60" width="24" height="24" rx="6" opacity=".20"/><rect x="90" y="60" width="24" height="24" rx="6" opacity=".30"/>
        </g>
    </svg>
    <div class="relative z-10 mx-auto max-w-6xl px-4 pt-10 pb-24 sm:px-6 md:pt-16 md:pb-32">
        <p class="hero-eyebrow">Platform resmi pelatihan &amp; sertifikasi</p>
        <h1 class="mt-3 max-w-3xl font-display text-3xl leading-tight font-bold text-balance md:text-5xl">Satu platform untuk pelatihan &amp; sertifikasi <span class="text-brand-300">Internasional</span> &amp; <span class="text-brand-300">BNSP</span></h1>
        <p class="hero-sub mt-5 text-base">Pilih program pelatihan, belajar terarah lewat modul &amp; ujian, hingga menerima sertifikat bertanda tangan digital yang dapat divalidasi publik.</p>
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ route('login') }}" class="btn-primary w-auto px-6">Masuk ke LMS</a>
            @if (config('security.registration.enabled'))
                <a href="{{ route('register') }}" class="btn-onhero px-6">Daftar Peserta</a>
            @endif
        </div>
    </div>
</div>
<main class="page-overlap mx-auto w-full max-w-6xl flex-1 px-4 pb-12 sm:px-6">
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
</main>
<footer class="py-8 text-center text-xs text-slate-500">&copy; {{ now()->year }} Semesta Teknologi Utama — STU LMS · <a href="{{ route('legal.privacy') }}" class="hover:underline">Kebijakan Privasi</a> · <a href="{{ route('legal.terms') }}" class="hover:underline">Syarat &amp; Ketentuan</a></footer>
</body>
</html>
