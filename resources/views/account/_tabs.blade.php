<nav class="tabs mb-6" aria-label="Pengaturan akun">
    @foreach (['account.profile' => 'Profil', 'account.security' => 'Keamanan', 'account.sessions' => 'Sesi & Perangkat', 'account.privacy' => 'Privasi'] as $routeName => $label)
        <a href="{{ route($routeName) }}" @if (request()->routeIs($routeName)) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
