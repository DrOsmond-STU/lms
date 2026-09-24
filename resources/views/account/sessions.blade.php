<x-layouts.app title="Sesi & Perangkat" :workspace="$workspace">
    <x-slot:heading>Akun Saya</x-slot:heading>
    <x-slot:subtitle>Perangkat yang sedang masuk ke akun Anda. Tidak mengenali salah satunya? Keluarkan lalu ubah kata sandi.</x-slot:subtitle>

    @include('account._tabs')
    <div class="card divide-y divide-slate-100">
        @forelse ($sessions as $session)
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 text-sm">
                <div>
                    <p class="font-bold text-slate-800">{{ $session['device'] }} @if ($session['current'])<span class="badge ml-1 bg-emerald-50 text-emerald-700">Sesi ini</span>@endif</p>
                    <p class="text-xs text-slate-500">IP {{ $session['ip'] }} · masuk {{ $session['created']->translatedFormat('d M Y H:i') }} · aktif terakhir {{ $session['last']->translatedFormat('d M H:i') }} {{ tz_label() }}</p>
                </div>
                @unless ($session['current'])
                    <form method="POST" action="{{ route('account.sessions.revoke', $session['id']) }}">@csrf<button class="btn-secondary">Keluarkan</button></form>
                @endunless
            </div>
        @empty
            <p class="px-5 py-8 text-center text-sm text-slate-500">Tidak ada sesi aktif lain.</p>
        @endforelse
    </div>
    <form method="POST" action="{{ route('account.sessions.revoke-others') }}" class="mt-4" data-confirm="Keluarkan semua perangkat lain?">@csrf
        <button class="btn-danger">Keluar dari semua perangkat lain</button>
        <p class="mt-1 text-xs text-slate-500">Memerlukan konfirmasi identitas.</p>
    </form>
</x-layouts.app>
