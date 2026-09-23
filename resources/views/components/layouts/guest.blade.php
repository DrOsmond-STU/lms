@props(['title' => 'Masuk'])
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
<div class="grid min-h-screen lg:grid-cols-2">
    <div class="relative hidden flex-col justify-between overflow-hidden bg-gradient-to-br from-brand-900 to-brand-700 p-12 text-white lg:flex">
        <div class="absolute -top-24 -right-24 h-80 w-80 rounded-full bg-accent-500/20"></div>
        <div class="absolute bottom-10 -left-16 h-56 w-56 rounded-full bg-white/5"></div>
        <a href="{{ route('home') }}" class="relative flex items-center gap-2.5">
            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-accent-500 text-sm font-extrabold text-brand-900">STU</span>
            <span class="leading-tight"><span class="block font-extrabold tracking-wide">STU LMS</span><span class="block text-xs text-white/60">Pelatihan &amp; Sertifikasi</span></span>
        </a>
        <div class="relative">
            <h1 class="mb-4 text-3xl leading-tight font-extrabold">Belajar, dinilai, dan buktikan kompetensi Anda.</h1>
            <p class="max-w-md text-white/75">Kelola pembelajaran sertifikasi Internasional &amp; BNSP dalam satu platform — mulai dari pendaftaran, materi, ujian, hingga sertifikat yang dapat diverifikasi publik.</p>
        </div>
        <p class="relative text-xs text-white/50">&copy; {{ now()->year }} Semesta Teknologi Utama</p>
    </div>
    <main class="flex items-center justify-center p-6 sm:p-10">
        <div class="w-full max-w-md">
            <a href="{{ route('home') }}" class="mb-8 flex items-center gap-2.5 lg:hidden">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-800 text-sm font-extrabold text-white">STU</span>
                <span class="font-extrabold text-slate-800">STU LMS</span>
            </a>
            @if (session('status'))
                <div class="mb-5 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800" role="status">{{ session('status') }}</div>
            @endif
            {{ $slot }}
        </div>
    </main>
</div>
</body>
</html>
