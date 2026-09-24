@props(['title' => null, 'heading' => null, 'subtitle' => null])
@php($theme = \App\Support\Ui\Theme::current())
<!DOCTYPE html>
<html lang="id" @if ($theme) data-theme="{{ $theme }}" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#062b63">
    <title>{{ $title ?? setting('branding.short_name') }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col font-sans text-slate-700">
<x-environment-banner />
<div class="hero rounded-b-[20px]">
    <header class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
        <a href="{{ route('home') }}" class="flex items-center gap-3">
                        <x-brand />
        </a>
        <nav class="flex flex-wrap items-center gap-2 text-sm font-semibold" aria-label="Navigasi publik">
            <a href="{{ route('catalog.public') }}" @class(['rounded-lg px-3 py-2 hover:bg-white/10', 'bg-white/15' => request()->routeIs('catalog.public*')])>Program</a>
            <a href="{{ route('verification.form') }}" @class(['rounded-lg px-3 py-2 hover:bg-white/10', 'bg-white/15' => request()->routeIs('verification.*')])>Verifikasi Sertifikat</a>
            <x-theme-switch onbrand />
            @auth
                <a href="{{ route('dashboard') }}" class="btn-primary w-auto">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="btn-primary w-auto">Masuk</a>
            @endauth
        </nav>
    </header>
    <div class="mx-auto max-w-6xl px-4 pt-4 pb-14 sm:px-6">
        <p class="hero-eyebrow">{{ setting('branding.short_name') }} · Publik</p>
        <h1 class="hero-title">{{ $heading ?? $title ?? setting('branding.short_name') }}</h1>
        @if ($subtitle)<p class="hero-sub">{{ $subtitle }}</p>@endif
    </div>
</div>
<main class="page-overlap mx-auto w-full max-w-6xl flex-1 px-4 pb-12 sm:px-6">
    @if (session('status'))
        <div class="mb-5 rounded-xl border border-brand-200 bg-surface px-4 py-3 text-sm text-slate-800 shadow-[var(--shadow-float-md)]" role="status">{{ session('status') }}</div>
    @endif
    {{ $slot }}
</main>
<footer class="py-6 text-center text-xs text-slate-500">
    &copy; {{ now()->year }} {{ \App\Modules\Cms\Models\SiteProfile::current()->company_name }} · <a href="{{ route('legal.privacy') }}" class="hover:underline">Kebijakan Privasi</a> · <a href="{{ route('legal.terms') }}" class="hover:underline">Syarat &amp; Ketentuan</a>
</footer>
</body>
</html>
