@props(['name'])
{{-- Ikon garis dari purwarupa (heroicons-outline). Markup statis — tanpa data pengguna & tanpa output mentah. --}}
<svg {{ $attributes->merge(['class' => 'h-5 w-5 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
@switch($name)
    @case('dashboard')
        <rect x="3.5" y="3.5" width="7" height="7" rx="1.4"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.4"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.4"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.4"/>
        @break
    @case('cert')
        <circle cx="12" cy="8.5" r="5"/><path d="M8.5 12.8 7 21l5-2.5 5 2.5-1.5-8.2" stroke-linecap="round" stroke-linejoin="round"/>
        @break
    @case('book')
        <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5v-15Z" stroke-linejoin="round"/><path d="M4 20.5A2.5 2.5 0 0 1 6.5 18H20" stroke-linejoin="round"/>
        @break
    @case('calendar')
        <rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4" stroke-linecap="round"/>
        @break
    @case('shield')
        <path d="M12 3.5 4.5 6v6c0 5 3.2 7.7 7.5 8.9 4.3-1.2 7.5-3.9 7.5-8.9V6L12 3.5Z" stroke-linejoin="round"/><path d="m8.7 12 2.3 2.3 4.3-4.3" stroke-linecap="round" stroke-linejoin="round"/>
        @break
    @case('trophy')
        <path d="M8 4h8v5a4 4 0 0 1-8 0V4ZM8 6H4.5a3 3 0 0 0 3.5 4M16 6h3.5a3 3 0 0 1-3.5 4M12 13v4M8.5 20.5h7M10 17h4v3.5h-4z" stroke-linejoin="round"/>
        @break
    @case('bell')
        <path d="M6 16V11a6 6 0 1 1 12 0v5l1.5 2h-15L6 16Z" stroke-linejoin="round"/><path d="M10 20.5a2 2 0 0 0 4 0" stroke-linecap="round"/>
        @break
    @case('user')
        <circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c1.4-3.6 4.3-5.5 7.5-5.5s6.1 1.9 7.5 5.5" stroke-linecap="round"/>
        @break
    @case('users')
        <circle cx="9" cy="8" r="3.2"/><path d="M2.8 19.5c1.2-3.2 3.6-4.8 6.2-4.8s5 1.6 6.2 4.8" stroke-linecap="round"/><circle cx="17" cy="8.5" r="2.6"/><path d="M15.7 14.9c2.2.4 3.8 1.9 4.8 4.6" stroke-linecap="round"/>
        @break
    @case('layers')
        <path d="m12 3 8.5 4.5L12 12 3.5 7.5 12 3Z" stroke-linejoin="round"/><path d="m3.5 12 8.5 4.5 8.5-4.5" stroke-linejoin="round"/><path d="m3.5 16.5 8.5 4.5 8.5-4.5" stroke-linejoin="round"/>
        @break
    @case('chat')
        <path d="M4 5.5h16v10H9l-5 4v-14Z" stroke-linejoin="round"/>
        @break
    @case('chart')
        <path d="M4 20V10M11 20V4M18 20v-6" stroke-linecap="round"/>
        @break
    @case('check')
        <path d="m4.5 12.5 5 5 10-11" stroke-linecap="round" stroke-linejoin="round"/>
        @break
    @case('building')
        <rect x="4" y="3" width="11" height="18" rx="1"/><path d="M15 8h5v13h-5M7.5 7h1M7.5 10.5h1M7.5 14h1M11.5 7h1M11.5 10.5h1M11.5 14h1"/>
        @break
    @case('doc')
        <path d="M6 3h8l5 5v13H6Z" stroke-linejoin="round"/><path d="M14 3v5h5"/>
        @break
    @case('clipboard')
        <rect x="5" y="4.5" width="14" height="16.5" rx="2"/><path d="M9 4.5V3h6v1.5M8.5 11h7M8.5 15h5" stroke-linecap="round"/>
        @break
    @case('card')
        <rect x="3" y="5.5" width="18" height="13" rx="2"/><path d="M3 10h18M7 15h3" stroke-linecap="round"/>
        @break
    @case('history')
        <path d="M4 12a8 8 0 1 0 2.3-5.6M4 4v4h4M12 8v4l3 2" stroke-linecap="round" stroke-linejoin="round"/>
        @break
    @case('settings')
        <circle cx="12" cy="12" r="3"/><path d="M12 3v2.5M12 18.5V21M3 12h2.5M18.5 12H21M5.6 5.6l1.8 1.8M16.6 16.6l1.8 1.8M5.6 18.4l1.8-1.8M16.6 7.4l1.8-1.8" stroke-linecap="round"/>
        @break
    @case('logout')
        <path d="M15 4h3.5A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5H15M10 16l-4-4 4-4M6 12h10" stroke-linecap="round" stroke-linejoin="round"/>
        @break
    @case('lock')
        <rect x="5" y="10.5" width="14" height="10" rx="2"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5" stroke-linecap="round"/>
        @break
    @case('menu')
        <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
        @break
    @case('close')
        <path d="M6 6l12 12M18 6 6 18" stroke-linecap="round"/>
        @break
    @case('sun')
        <circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" stroke-linecap="round"/>
        @break
    @case('moon')
        <path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a6.8 6.8 0 0 0 10.5 10.5Z" stroke-linecap="round" stroke-linejoin="round"/>
        @break
    @case('monitor')
        <rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8" stroke-linecap="round"/>
        @break
    @case('up')
        <path d="M12 19V5M5 12l7-7 7 7" stroke-linecap="round" stroke-linejoin="round"/>
        @break
    @case('down')
        <path d="M12 5v14M5 12l7 7 7-7" stroke-linecap="round" stroke-linejoin="round"/>
        @break
    @case('plus')
        <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
        @break
    @case('download')
        <path d="M12 4v11M7 11l5 5 5-5M4 20h16" stroke-linecap="round" stroke-linejoin="round"/>
        @break
    @case('play')
        <circle cx="12" cy="12" r="9"/><path d="m10 8.5 5.5 3.5-5.5 3.5v-7Z" stroke-linejoin="round"/>
        @break
    @case('graduation')
        <path d="M2.5 9 12 4.5 21.5 9 12 13.5 2.5 9Z" stroke-linejoin="round"/><path d="M6.5 11v4.5c0 1.5 2.5 3 5.5 3s5.5-1.5 5.5-3V11M21.5 9v5" stroke-linecap="round" stroke-linejoin="round"/>
        @break
    @case('image')
        <rect x="3.5" y="4.5" width="17" height="15" rx="2"/><circle cx="9" cy="10" r="1.8"/><path d="m20.5 16-4.5-4.5L6 19.5" stroke-linecap="round" stroke-linejoin="round"/>
        @break
    @case('star')
        <path d="m12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8-4.3-4.1 5.9-.9L12 3.5Z" stroke-linejoin="round"/>
        @break
    @case('mail')
        <rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6" stroke-linecap="round" stroke-linejoin="round"/>
        @break
    @case('phone')
        <path d="M5 4h3.5l1.5 4-2 1.5a11 11 0 0 0 6.5 6.5l1.5-2 4 1.5V19a1.5 1.5 0 0 1-1.6 1.5A16.5 16.5 0 0 1 3.5 5.6 1.5 1.5 0 0 1 5 4Z" stroke-linejoin="round"/>
        @break
    @case('pin')
        <path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11Z" stroke-linejoin="round"/><circle cx="12" cy="10" r="2.3"/>
        @break
    @case('clock')
        <circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2" stroke-linecap="round" stroke-linejoin="round"/>
        @break
    @case('globe')
        <circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5c2.3 2.4 3.5 5.2 3.5 8.5s-1.2 6.1-3.5 8.5c-2.3-2.4-3.5-5.2-3.5-8.5s1.2-6.1 3.5-8.5Z" stroke-linejoin="round"/>
        @break
    @case('chat-bubble')
        <path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v9a1.5 1.5 0 0 1-1.5 1.5H10l-4.5 4v-4h0A1.5 1.5 0 0 1 4 14.5v-9Z" stroke-linejoin="round"/>
        @break
    @case('arrow-right')
        <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
        @break
    @case('chevron-left')
        <path d="m15 5-7 7 7 7" stroke-linecap="round" stroke-linejoin="round"/>
        @break
    @case('chevron-right')
        <path d="m9 5 7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/>
        @break
@endswitch
</svg>
