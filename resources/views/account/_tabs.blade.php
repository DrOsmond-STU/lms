<nav class="mb-6 flex flex-wrap gap-1 rounded-lg bg-slate-100 p-1 text-sm font-bold" aria-label="Pengaturan akun">
    @foreach (['account.profile' => 'Profil', 'account.security' => 'Keamanan', 'account.sessions' => 'Sesi & Perangkat', 'account.privacy' => 'Privasi'] as $routeName => $label)
        <a href="{{ route($routeName) }}" @class(['rounded-md px-4 py-2', 'bg-white text-brand-800 shadow' => request()->routeIs($routeName), 'text-slate-600 hover:text-slate-800' => ! request()->routeIs($routeName)]) @if (request()->routeIs($routeName)) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
