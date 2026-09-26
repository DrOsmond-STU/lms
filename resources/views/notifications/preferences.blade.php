<x-layouts.app title="Preferensi Notifikasi" :workspace="$workspace">
    <x-slot:back><a href="{{ route('notifications.index') }}" class="hero-back">&larr; Notifikasi</a></x-slot:back>
    <x-slot:heading>Preferensi Notifikasi</x-slot:heading>
    <x-slot:subtitle>Pilih kanal dan jenis informasi yang ingin Anda terima. Notifikasi di aplikasi dan keamanan akun selalu aktif.</x-slot:subtitle>

    <div class="grid max-w-5xl gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('notifications.preferences.update') }}" class="card space-y-5 p-6">@csrf @method('PUT')
            <section>
                <h2 class="card-title">Kanal</h2>
                <div class="mt-2 space-y-2 text-sm">
                    <label class="flex items-center gap-2"><input type="checkbox" checked disabled> Di aplikasi (selalu aktif)</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="email_enabled" value="1" @checked($preference->email_enabled)> Email</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="push_enabled" value="1" @checked($preference->push_enabled)> Push peramban/ponsel {{ $pushConfigured ? '' : '(belum diaktifkan admin)' }}</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="whatsapp_enabled" value="1" @checked($preference->whatsapp_enabled) @disabled(! $whatsappConfigured)> WhatsApp {{ $whatsappConfigured ? ($hasPhone ? '' : '— isi nomor HP di Akun Saya') : '(belum diaktifkan admin)' }}</label>
                </div>
            </section>
            <section>
                <h2 class="card-title">Bisukan kategori</h2>
                <p class="card-sub">Kategori yang dibisukan tidak dikirim ke email/push/WhatsApp (tetap tampil di aplikasi).</p>
                <div class="grid gap-2 text-sm sm:grid-cols-2">
                    @foreach ($categories as $key => $label)
                        <label class="flex items-center gap-2"><input type="checkbox" name="muted[]" value="{{ $key }}" @checked(in_array($key, $preference->muted_categories, true)) @disabled($key === 'security')> {{ $label }}{{ $key === 'security' ? ' (wajib)' : '' }}</label>
                    @endforeach
                </div>
            </section>
            <div class="flex flex-wrap gap-2"><button class="btn-primary w-auto">Simpan Preferensi</button><button formaction="{{ route('notifications.preferences.test') }}" formmethod="POST" class="btn-secondary w-auto">Kirim pesan uji</button></div>
        </form>

        <section class="card p-6" aria-labelledby="push-heading">
            <h2 id="push-heading" class="card-title">Perangkat Push</h2>
            <p class="card-sub">Terima pemberitahuan tenggat, nilai, dan sertifikat walau aplikasi tidak dibuka.</p>
            @if ($pushConfigured)
                <div data-push-panel data-vapid-key="{{ $vapidPublic }}" data-subscribe-url="{{ route('notifications.push.subscribe') }}" data-sw-url="{{ url('/sw.js') }}">
                    <button type="button" class="btn-primary w-auto" data-push-enable>Aktifkan di perangkat ini</button>
                    <p class="mt-2 text-xs text-slate-500" data-push-status>Memerlukan peramban modern dan izin notifikasi.</p>
                </div>
            @else
                <p class="text-sm text-slate-500">Admin belum mengaktifkan Web Push (Pengaturan Sistem → Integrasi).</p>
            @endif
            <ul class="mt-4 divide-y divide-slate-100 text-sm">
                @forelse ($subscriptions as $subscription)
                    <li class="flex items-center justify-between gap-3 py-2">
                        <span><span class="block font-semibold text-slate-800">{{ \Illuminate\Support\Str::limit($subscription->user_agent ?? 'Perangkat', 60) }}</span><span class="text-xs text-slate-500">Didaftarkan {{ $subscription->created_at->timezone(display_tz())->translatedFormat('d M Y') }}{{ $subscription->last_used_at ? ' · terakhir dikirim '.$subscription->last_used_at->timezone(display_tz())->translatedFormat('d M H:i') : '' }}</span></span>
                        <form method="POST" action="{{ route('notifications.push.unsubscribe', $subscription) }}">@csrf @method('DELETE')<button class="btn-mini-danger">Hapus</button></form>
                    </li>
                @empty
                    <li class="py-2 text-slate-500">Belum ada perangkat terdaftar.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-layouts.app>
