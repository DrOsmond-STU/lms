<x-layouts.app title="Privasi" :workspace="$workspace">
    <x-slot:heading>Akun Saya</x-slot:heading>
    <x-slot:subtitle>Kelola persetujuan opsional. Persetujuan dapat ditarik kapan saja tanpa memengaruhi layanan inti.</x-slot:subtitle>

    @include('account._tabs')
    <div class="grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('account.privacy.update') }}" class="card space-y-3 p-6">@csrf
            <h2 class="font-bold text-slate-800">Persetujuan Opsional</h2>
            @foreach ($optional as $purpose => $label)
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="consents[{{ $purpose }}]" value="1" @checked(in_array($purpose, $granted, true)) class="mt-0.5"> {{ $label }}</label>
            @endforeach
            <button class="btn-primary w-auto">Simpan</button>
            <p class="text-xs text-slate-500">Notifikasi keamanan akun tidak dapat dimatikan.</p>
        </form>
        <section class="card p-6">
            <h2 class="font-bold text-slate-800">Riwayat Persetujuan</h2>
            <ul class="mt-3 space-y-2 text-xs">
                @forelse ($history as $item)
                    <li class="flex justify-between gap-2"><span>{{ ['terms' => 'Syarat & Ketentuan', 'privacy' => 'Kebijakan Privasi'][$item->document] ?? ($optional[$item->document] ?? $item->document) }} v{{ $item->version }}</span><span class="text-slate-500">{{ \Illuminate\Support\Carbon::parse($item->accepted_at)->timezone(display_tz())->format('d M Y H:i') }}{{ $item->withdrawn_at ? ' · ditarik' : '' }}</span></li>
                @empty
                    <li class="text-slate-500">Belum ada.</li>
                @endforelse
            </ul>
            <p class="mt-4 text-xs text-slate-500">Permintaan akses/koreksi/penghapusan data (UU PDP) dapat diajukan melalui kontak dukungan; formulir mandiri tersedia pada rilis berikutnya.</p>
        </section>
    </div>
</x-layouts.app>
