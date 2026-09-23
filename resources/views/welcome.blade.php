<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Platform Pelatihan &amp; Sertifikasi</title>
    <meta name="description" content="STU LMS: pelatihan sertifikasi Internasional & BNSP dengan sertifikat yang dapat diverifikasi publik.">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white font-sans text-slate-800">
<x-environment-banner />
<header class="border-b border-slate-100">
    <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-800 text-sm font-extrabold text-white">STU</span>
            <span class="font-extrabold text-slate-800">STU LMS</span>
        </a>
        <a href="{{ route('login') }}" class="rounded-lg bg-brand-800 px-4 py-2 text-sm font-bold text-white hover:bg-brand-700">Masuk</a>
    </div>
</header>
<main class="bg-gradient-to-br from-brand-900 to-brand-700 text-white">
    <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 md:py-28">
        <span class="badge mb-5 bg-white/10 text-white">Platform Resmi Pelatihan &amp; Sertifikasi</span>
        <h1 class="max-w-3xl text-3xl leading-tight font-extrabold md:text-5xl">Satu platform untuk pelatihan &amp; sertifikasi <span class="text-accent-400">Internasional</span> &amp; <span class="text-accent-400">BNSP</span></h1>
        <p class="mt-5 max-w-2xl text-white/80">Pilih program pelatihan, belajar terarah lewat modul &amp; ujian, hingga menerima sertifikat bertanda tangan digital yang dapat divalidasi publik.</p>
        <a href="{{ route('login') }}" class="mt-8 inline-flex rounded-lg bg-accent-500 px-6 py-3 font-bold text-brand-900 hover:bg-accent-400">Masuk ke LMS</a>
    </div>
</main>
<footer class="py-8 text-center text-xs text-slate-500">&copy; {{ now()->year }} Semesta Teknologi Utama — STU LMS</footer>
</body>
</html>
