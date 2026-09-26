{{-- Aksi tambahan tab Integrasi: bangkitkan kunci VAPID & uji WhatsApp (form terpisah dari form utama). --}}
</form>
<div class="max-w-3xl space-y-4">
    <div class="card flex flex-wrap items-center gap-3 p-5">
        <div class="flex-1 text-sm"><span class="font-bold text-slate-800">Kunci VAPID</span><span class="block text-xs text-slate-500">{{ \App\Modules\Notification\Services\WebPush::publicKey() !== '' ? 'Kunci sudah ada. Membuat kunci baru memutus semua langganan push yang ada.' : 'Belum ada kunci. Bangkitkan sekali, lalu centang "Web Push aktif" dan simpan.' }}</span></div>
        @if ($canUpdate)<form method="POST" action="{{ route('admin.settings.vapid') }}" data-confirm="Bangkitkan kunci VAPID baru? Langganan push yang ada akan berhenti menerima notifikasi.">@csrf<button class="btn-secondary w-auto">Buat kunci VAPID</button></form>@endif
    </div>
    <div class="card flex flex-wrap items-center gap-3 p-5">
        <div class="flex-1 text-sm"><span class="font-bold text-slate-800">Uji WhatsApp gateway</span><span class="block text-xs text-slate-500">Mengirim pesan uji ke nomor HP akun Anda (Akun Saya) memakai konfigurasi yang tersimpan.</span></div>
        @if ($canUpdate)<form method="POST" action="{{ route('admin.settings.whatsapp-test') }}">@csrf<button class="btn-secondary w-auto">Kirim pesan uji</button></form>@endif
    </div>
    <p class="text-xs text-slate-500">Log pengiriman push/WhatsApp tersedia di Jejak Audit dan tabel pesan keluar (status sent/failed).</p>
</div>
<form class="hidden">
