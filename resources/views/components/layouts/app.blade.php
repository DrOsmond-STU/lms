@props(['title' => 'Dashboard', 'workspace'])
@php
    /** @var \App\Modules\Identity\Models\User $user */
    $user = auth()->user();
    $groups = config("navigation.{$workspace}", []);
    $workspaceLabels = ['participant' => 'Peserta', 'trainer' => 'Trainer', 'organization' => 'Admin Organisasi', 'admin' => 'Administrator'];
    $initials = collect(preg_split('/\s+/', trim(preg_replace('/[,.].*$/', '', $user->name))))
        ->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles(['nonce' => Vite::cspNonce()])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-800">
<aside class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col bg-gradient-to-b from-brand-900 to-brand-800 text-white lg:flex" aria-label="Navigasi utama">
    <div class="flex h-16 items-center gap-2.5 border-b border-white/10 px-5">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-accent-500 text-sm font-extrabold text-brand-900">STU</span>
        <span class="leading-tight"><span class="block text-sm font-extrabold tracking-wide">STU LMS</span><span class="block text-[11px] text-white/60">{{ $workspaceLabels[$workspace] ?? '' }}</span></span>
    </div>
    <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 py-4">
        @foreach ($groups as $group)
            @if ($group['group'])
                <div class="nav-group-label">{{ $group['group'] }}</div>
            @endif
            @foreach ($group['items'] as $item)
                @continue($item['permission'] !== null && ! $user->can($item['permission']))
                @if ($item['route'])
                    <a href="{{ route($item['route']) }}" @class(['nav-link', 'nav-link-active' => request()->routeIs($item['route'])]) @if (request()->routeIs($item['route'])) aria-current="page" @endif>
                        <x-icon :name="$item['icon']" /><span>{{ $item['label'] }}</span>
                    </a>
                @else
                    <span class="nav-link cursor-not-allowed opacity-50" aria-disabled="true" title="Dibangun pada fase berikutnya">
                        <x-icon :name="$item['icon']" /><span class="flex-1">{{ $item['label'] }}</span><span class="rounded bg-white/10 px-1.5 text-[10px] font-bold">Segera</span>
                    </span>
                @endif
            @endforeach
        @endforeach
    </nav>
    <div class="border-t border-white/10 px-3 py-4">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="nav-link w-full text-left"><x-icon name="logout" /><span>Keluar</span></button>
        </form>
    </div>
</aside>

<header class="fixed top-0 right-0 left-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200 bg-white px-4 lg:left-64 lg:px-6">
    <span class="font-extrabold text-slate-800 lg:hidden">STU LMS</span>
    <div class="flex-1"></div>
    <span class="badge bg-brand-50 text-brand-700">{{ $workspaceLabels[$workspace] ?? '' }}</span>
    <div class="flex items-center gap-2 border-l border-slate-200 pl-3">
        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-xs font-extrabold text-brand-800" aria-hidden="true">{{ $initials }}</span>
        <span class="hidden text-sm leading-tight md:block"><span class="block font-bold text-slate-800">{{ $user->name }}</span><span class="block text-xs text-slate-600">{{ $user->email }}</span></span>
    </div>
    <form method="POST" action="{{ route('logout') }}" class="lg:hidden">
        @csrf
        <button type="submit" class="text-sm font-bold text-slate-600">Keluar</button>
    </form>
</header>

<main class="px-4 pt-24 pb-10 lg:ml-64 lg:px-8">
    <x-environment-banner />
    @if (session('status'))
        <div class="mb-5 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800" role="status">{{ session('status') }}</div>
    @endif
    {{ $slot }}
</main>
@livewireScripts(['nonce' => Vite::cspNonce()])
</body>
</html>
