<x-layouts.app title="Pemantauan Keamanan" workspace="admin">
    <x-slot:heading>Pemantauan Keamanan</x-slot:heading>
    <x-slot:subtitle>Login gagal, pembatasan laju, anomali sesi, dan ekspor massal dari event keamanan (append-only). Pemindaian otomatis tiap jam; peringatan tinggi dikirim ke Super Admin.</x-slot:subtitle>
    <x-slot:actions><form method="POST" action="{{ route('admin.security.scan') }}">@csrf<button class="btn-secondary">Pindai sekarang</button></form></x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @include('dashboards._tile', ['label' => 'Login gagal (24 jam)', 'value' => $failedLogins24h, 'icon' => 'lock', 'tone' => $failedLogins24h > 50 ? 'bad' : 'good', 'note' => $rateLimited24h.' pembatasan laju'])
        @include('dashboards._tile', ['label' => 'Peringatan (7 hari)', 'value' => $alerts7d, 'icon' => 'shield', 'tone' => $alerts7d > 0 ? 'bad' : 'good', 'note' => 'Brute force, akun ditarget, anomali sesi, ekspor massal'])
        @include('dashboards._tile', ['label' => 'Sesi aktif (2 jam)', 'value' => $activeSessions, 'icon' => 'monitor', 'note' => 'Perangkat dengan aktivitas terbaru'])
        @include('dashboards._tile', ['label' => 'Jenis event (7 hari)', 'value' => $byType7d->count(), 'icon' => 'history', 'note' => 'Lihat rincian di bawah'])
    </div>

    <section class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="card p-5 xl:col-span-2">
            <h2 class="card-title">Peringatan Terbaru</h2>
            <p class="card-sub">Hasil pemindaian; detail tersamar (tanpa rahasia)</p>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($alerts as $alert)
                    <li class="flex flex-wrap items-start justify-between gap-2 py-2">
                        <span><span @class(['chip px-1.5 py-0.5 text-[10px]', 'chip-critical' => $alert->severity === 'critical', 'chip-high' => $alert->severity === 'high', 'chip-medium' => $alert->severity === 'warning', 'chip-info' => $alert->severity === 'info'])>{{ $alert->severity }}</span> <span class="font-mono font-bold">{{ $alert->type }}</span><span class="block text-xs text-slate-500">{{ \Illuminate\Support\Str::limit((string) $alert->details, 160) }}{{ $alert->user_id ? ' · '.($users[$alert->user_id] ?? 'pengguna') : '' }}</span></span>
                        <span class="text-xs whitespace-nowrap text-slate-500">{{ \Illuminate\Support\Carbon::parse($alert->occurred_at)->timezone(display_tz())->format('d M H:i') }}</span>
                    </li>
                @empty
                    <li class="py-4 text-slate-500">Belum ada peringatan.</li>
                @endforelse
            </ul>
        </div>
        <div class="card p-5">
            <h2 class="card-title">IP Login Gagal Terbanyak</h2>
            <p class="card-sub">24 jam terakhir</p>
            <ul class="space-y-1 font-mono text-xs">
                @forelse ($topIps as $row)
                    <li class="flex justify-between"><span>{{ $row->ip }}</span><span>{{ $row->total }}×</span></li>
                @empty
                    <li class="font-sans text-slate-500">Tidak ada.</li>
                @endforelse
            </ul>
            <h3 class="mt-5 text-sm font-bold text-slate-800">Event 7 hari per jenis</h3>
            <ul class="mt-1 space-y-1 text-xs">
                @foreach ($byType7d as $row)<li class="flex justify-between"><span class="font-mono">{{ $row->type }} <span class="text-slate-500">({{ $row->severity }})</span></span><span>{{ $row->total }}</span></li>@endforeach
            </ul>
        </div>
    </section>

    <form method="GET" class="card mt-6 flex flex-wrap items-end gap-3 p-4" role="search">
        <div><label for="jenis" class="form-label">Jenis event</label><input id="jenis" name="jenis" value="{{ $type }}" maxlength="64" placeholder="authn_login_fail" class="form-input"></div>
        <div><label for="tingkat" class="form-label">Tingkat</label><select id="tingkat" name="tingkat" class="form-select"><option value="">Semua</option>@foreach (['info', 'warning', 'high', 'critical'] as $level)<option value="{{ $level }}" @selected($severity === $level)>{{ $level }}</option>@endforeach</select></div>
        <button class="btn-secondary">Terapkan</button>
    </form>
    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th scope="col">Waktu ({{ tz_label() }})</th><th scope="col">Jenis</th><th scope="col">Tingkat</th><th scope="col">Pengguna</th><th scope="col">IP</th><th scope="col">Detail</th></tr></thead>
            <tbody>
                @forelse ($events as $event)
                    <tr>
                        <td class="text-xs whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($event->occurred_at)->timezone(display_tz())->format('d M Y H:i:s') }}</td>
                        <td class="font-mono text-xs">{{ $event->type }}</td>
                        <td class="text-xs">{{ $event->severity }}</td>
                        <td class="text-xs">{{ $event->user_id ? ($users[$event->user_id] ?? 'pengguna') : '—' }}</td>
                        <td class="font-mono text-xs">{{ $event->ip }}</td>
                        <td class="max-w-md text-xs break-all text-slate-600">{{ \Illuminate\Support\Str::limit((string) $event->details, 200) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-500">Tidak ada event.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $events->links() }}</div>
</x-layouts.app>
