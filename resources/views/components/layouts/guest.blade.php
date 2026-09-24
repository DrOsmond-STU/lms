@props(['title' => 'Masuk'])
@php($theme = \App\Support\Ui\Theme::current())
<!DOCTYPE html>
<html lang="id" @if ($theme) data-theme="{{ $theme }}" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#062b63">
    <title>{{ $title }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans text-slate-700">
<x-environment-banner />
<div class="grid min-h-screen lg:grid-cols-2">
    <div class="login-brandside relative flex flex-col gap-8 overflow-hidden px-6 py-8 sm:px-10 lg:px-12 lg:py-12">
        <a href="{{ route('home') }}" class="relative z-10 flex items-center gap-3">
                        <x-brand />
        </a>
        <div class="relative z-10 hidden max-w-[34ch] lg:mt-auto lg:block">
            @if (setting('login.panel_eyebrow'))<p class="hero-eyebrow">{{ setting('login.panel_eyebrow') }}</p>@endif
            <h2 class="mt-3 mb-1 font-display text-[26px] leading-8 font-bold text-balance">{{ setting('login.panel_title') }}</h2>
            @if (setting('login.panel_text'))<p class="text-sm text-brand-300">{{ setting('login.panel_text') }}</p>@endif
            @if (setting('login.panel_quote'))<blockquote class="mt-6 border-l-[3px] border-brand-400 pl-4 text-[15px] leading-6 text-brand-300">{{ setting('login.panel_quote') }}</blockquote>@endif
        </div>
        <svg class="pointer-events-none absolute top-12 -right-10 hidden h-[140px] w-[320px] text-white lg:block" viewBox="0 0 320 140" aria-hidden="true" preserveAspectRatio="none">
            <g fill="currentColor">
                <rect x="0" y="0" width="52" height="24" rx="6" opacity=".14"/><rect x="58" y="0" width="24" height="24" rx="6" opacity=".22"/><rect x="88" y="0" width="24" height="24" rx="6" opacity=".30"/>
                <rect x="0" y="30" width="24" height="24" rx="6" opacity=".10"/><rect x="30" y="30" width="24" height="24" rx="6" opacity=".18"/><rect x="60" y="30" width="52" height="24" rx="6" opacity=".26"/>
                <rect x="0" y="60" width="24" height="24" rx="6" opacity=".08"/><rect x="30" y="60" width="24" height="24" rx="6" opacity=".14"/><rect x="60" y="60" width="24" height="24" rx="6" opacity=".20"/><rect x="90" y="60" width="24" height="24" rx="6" opacity=".30"/>
            </g>
        </svg>
        <p class="relative z-10 hidden text-xs text-brand-300 lg:block">&copy; {{ now()->year }} {{ \App\Modules\Cms\Models\SiteProfile::current()->company_name }}</p>
    </div>
    <main class="flex flex-col items-center bg-slate-50 px-4 pt-6 pb-12 sm:px-6">
        <div class="mb-6 flex w-full max-w-[440px] justify-end"><x-theme-switch /></div>
        <div class="login-card my-auto w-full max-w-[440px] p-6 sm:p-8">
            @if (session('status'))
                <div class="mb-5 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-slate-800" role="status">{{ session('status') }}</div>
            @endif
            {{ $slot }}
        </div>
    </main>
</div>
</body>
</html>
