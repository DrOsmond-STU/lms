@props(['title' => 'Dashboard', 'workspace', 'hero' => true, 'eyebrow' => null])
@php
    /** @var \App\Modules\Identity\Models\User $user */
    $user = auth()->user();
    $groups = config("navigation.{$workspace}", []);
    $workspaceLabels = ['participant' => 'Peserta', 'trainer' => 'Trainer', 'organization' => 'Admin Organisasi', 'admin' => 'Administrator'];
    $initials = collect(preg_split('/\s+/', trim(preg_replace('/[,.].*$/', '', $user->name))))
        ->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    $unread = \App\Modules\Notification\Services\Notifier::unreadCount($user);
    $theme = \App\Support\Ui\Theme::current();
@endphp
<!DOCTYPE html>
<html lang="id" @if ($theme) data-theme="{{ $theme }}" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#062b63">
    <title>{{ $title }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles(['nonce' => Vite::cspNonce()])
</head>
<body class="min-h-screen font-sans text-slate-700">
<div class="fixed inset-0 z-40 hidden bg-[var(--overlay-scrim)] lg:hidden" data-sidebar-backdrop></div>
<aside id="sidebar" class="sidebar fixed inset-y-0 left-0 z-50 flex w-[268px] -translate-x-full flex-col gap-0.5 overflow-y-auto px-3 pt-5 pb-4 transition-transform duration-200 lg:translate-x-0" aria-label="Navigasi utama" data-sidebar>
    <div class="flex items-center gap-3 px-3 pb-5">
        <span class="brand-mark"><x-icon name="graduation" class="h-6 w-6" /></span>
        <span class="min-w-0"><span class="brand-name block">STU LMS</span><span class="brand-tag block">{{ $workspaceLabels[$workspace] ?? '' }}</span></span>
        <button type="button" class="sidebar-icon-btn ml-auto lg:hidden" data-sidebar-close aria-label="Tutup menu"><x-icon name="close" class="h-4 w-4" /></button>
    </div>
    <nav class="space-y-0.5">
        @foreach ($groups as $group)
            @if ($group['group'])
                <div class="nav-group-label">{{ $group['group'] }}</div>
            @endif
            @foreach ($group['items'] as $item)
                @continue($item['permission'] !== null && ! $user->can($item['permission']))
                @if ($item['route'])
                    @php($active = request()->routeIs(str_ends_with($item['route'], '.index') ? substr($item['route'], 0, -6).'.*' : $item['route']))
                    <a href="{{ route($item['route']) }}" @class(['nav-link', 'nav-link-active' => $active]) @if ($active) aria-current="page" @endif>
                        <x-icon :name="$item['icon']" class="h-[18px] w-[18px] shrink-0" /><span>{{ $item['label'] }}</span>
                        @if ($item['route'] === 'notifications.index' && $unread > 0)<span class="nav-count">{{ $unread > 99 ? '99+' : $unread }}</span>@endif
                    </a>
                @else
                    <span class="nav-link cursor-not-allowed opacity-50" aria-disabled="true" title="Dibangun pada fase berikutnya">
                        <x-icon :name="$item['icon']" class="h-[18px] w-[18px] shrink-0" /><span class="flex-1">{{ $item['label'] }}</span><span class="rounded bg-white/10 px-1.5 text-[10px] font-bold">Segera</span>
                    </span>
                @endif
            @endforeach
        @endforeach
    </nav>
    <div class="mt-auto flex justify-center px-3 pt-4"><x-theme-switch onbrand /></div>
    <div class="mt-4 flex items-center gap-3 border-t border-white/15 px-3 pt-4">
        <span class="sidebar-avatar" aria-hidden="true">{{ $initials }}</span>
        <a href="{{ route('account.profile') }}" class="min-w-0 flex-1 leading-tight" title="Akun saya">
            <span class="block truncate text-[13px] font-semibold text-white">{{ $user->name }}</span>
            <span class="block truncate text-[11px] text-brand-300">{{ $workspaceLabels[$workspace] ?? '' }}</span>
        </a>
        <a href="{{ route('notifications.index') }}" class="sidebar-icon-btn relative" aria-label="Notifikasi{{ $unread > 0 ? ', '.$unread.' belum dibaca' : '' }}" title="Notifikasi">
            <x-icon name="bell" class="h-[17px] w-[17px]" />
            @if ($unread > 0)<span class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-600 px-1 text-[0.6rem] font-bold text-white">{{ $unread > 99 ? '99+' : $unread }}</span>@endif
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="sidebar-icon-btn" aria-label="Keluar" title="Keluar"><x-icon name="logout" class="h-[17px] w-[17px]" /></button>
        </form>
    </div>
</aside>

<div class="flex min-h-screen flex-col lg:pl-[268px]">
    <div class="topbar-mobile sticky top-0 z-30 flex items-center gap-3 px-4 py-3 lg:hidden">
        <button type="button" class="sidebar-icon-btn" data-sidebar-open aria-controls="sidebar" aria-expanded="false" aria-label="Buka menu"><x-icon name="menu" class="h-[18px] w-[18px]" /></button>
        <span class="min-w-0 flex-1 truncate font-display text-base font-bold">{{ $title }}</span>
        <a href="{{ route('notifications.index') }}" class="sidebar-icon-btn relative" aria-label="Notifikasi{{ $unread > 0 ? ', '.$unread.' belum dibaca' : '' }}">
            <x-icon name="bell" class="h-[17px] w-[17px]" />
            @if ($unread > 0)<span class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-600 px-1 text-[0.6rem] font-bold text-white">{{ $unread > 99 ? '99+' : $unread }}</span>@endif
        </a>
    </div>
    <x-environment-banner />

    @if ($hero)
        <header class="hero px-4 pt-7 pb-12 sm:px-8">
            <div class="hero-row">
                <div class="min-w-0">
                    @isset($back)<div>{{ $back }}</div>@endisset
                    <p class="hero-eyebrow">{{ $eyebrow ?? ($workspaceLabels[$workspace] ?? '').' · STU LMS' }}</p>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <h1 class="hero-title">{{ $heading ?? $title }}</h1>
                        @isset($meta)<div class="flex flex-wrap items-center gap-2">{{ $meta }}</div>@endisset
                    </div>
                    @isset($subtitle)<p class="hero-sub">{{ $subtitle }}</p>@endisset
                </div>
                @isset($aside)<div class="hero-metric">{{ $aside }}</div>@endisset
                @isset($actions)<div class="hero-actions">{{ $actions }}</div>@endisset
            </div>
        </header>
    @endif

    <main @class(['flex-1 px-4 pb-16 sm:px-8', 'page-overlap' => $hero, 'pt-6' => ! $hero])>
        @if (session('status'))
            <div class="mb-5 rounded-xl border border-brand-200 bg-surface px-4 py-3 text-sm font-medium text-slate-800 shadow-[var(--shadow-float-md)]" role="status">{{ session('status') }}</div>
        @endif
        {{ $slot }}
    </main>
</div>
@livewireScripts(['nonce' => Vite::cspNonce()])
</body>
</html>
