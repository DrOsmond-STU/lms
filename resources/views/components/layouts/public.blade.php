@props(['title' => 'STU LMS'])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-800">
<x-environment-banner />
<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-800 text-sm font-extrabold text-white">STU</span>
            <span class="font-extrabold text-slate-800">STU LMS</span>
        </a>
        <nav class="flex flex-wrap items-center gap-4 text-sm font-bold" aria-label="Navigasi publik">
            <a href="{{ route('catalog.public') }}" class="text-slate-700 hover:text-brand-700">Program</a>
            <a href="{{ route('verification.form') }}" class="text-slate-700 hover:text-brand-700">Verifikasi Sertifikat</a>
            @auth
                <a href="{{ route('dashboard') }}" class="rounded-lg bg-brand-800 px-4 py-2 text-white hover:bg-brand-700">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="rounded-lg bg-brand-800 px-4 py-2 text-white hover:bg-brand-700">Masuk</a>
            @endauth
        </nav>
    </div>
</header>
<main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800" role="status">{{ session('status') }}</div>
    @endif
    {{ $slot }}
</main>
<footer class="border-t border-slate-200 bg-white py-6 text-center text-xs text-slate-500">
    &copy; {{ now()->year }} Semesta Teknologi Utama · <a href="{{ route('legal.privacy') }}" class="hover:underline">Kebijakan Privasi</a> · <a href="{{ route('legal.terms') }}" class="hover:underline">Syarat &amp; Ketentuan</a>
</footer>
</body>
</html>
