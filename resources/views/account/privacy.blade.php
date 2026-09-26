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
        </section>
        @if ($canRequestDeletion)
            <section class="card p-6">
                <h2 class="font-bold text-slate-800">Salinan Data Saya</h2>
                <p class="mt-1 text-sm text-slate-600">Unduh data pribadi, riwayat pelatihan, nilai, sertifikat, dan sesi Anda dalam format JSON (hak akses, UU PDP No. 27/2022).</p>
                <a href="{{ route('account.privacy.export') }}" class="btn-secondary mt-3 w-auto">Unduh data saya (JSON)</a>
            </section>
            <section class="card p-6">
                <h2 class="font-bold text-slate-800">Penghapusan Akun</h2>
                <x-form-error field="note" /><x-form-error field="confirm" />
                @if ($deleteRequest && $deleteRequest->status === 'pending')
                    <p class="mt-1 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">Permintaan penghapusan diajukan {{ $deleteRequest->created_at->timezone(display_tz())->translatedFormat('d M Y') }} dan sedang ditinjau admin (maks. 14 hari kerja).</p>
                    <form method="POST" action="{{ route('account.privacy.delete.cancel', $deleteRequest) }}" class="mt-3">@csrf<button class="btn-secondary w-auto">Batalkan permintaan</button></form>
                @else
                    <p class="mt-1 text-sm text-slate-600">Setelah disetujui, data pribadi Anda dianonimkan dan akun tidak dapat dipakai lagi. Sertifikat yang telah terbit tetap tersimpan dan dapat diverifikasi sesuai kewajiban penyelenggara.</p>
                    @if ($deleteRequest)<p class="mt-2 text-xs text-slate-500">Permintaan sebelumnya ({{ $deleteRequest->created_at->timezone(display_tz())->format('d M Y') }}): {{ \App\Modules\Security\Models\PrivacyRequest::STATUSES[$deleteRequest->status] }}{{ $deleteRequest->decision_note ? ' — '.$deleteRequest->decision_note : '' }}</p>@endif
                    <form method="POST" action="{{ route('account.privacy.delete') }}" class="mt-3 space-y-3" data-confirm="Ajukan penghapusan akun? Admin akan meninjau dan data Anda dianonimkan setelah disetujui.">@csrf
                        <div><label for="delete-note" class="form-label">Alasan (opsional)</label><input id="delete-note" name="note" maxlength="500" class="form-input"></div>
                        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="confirm" value="1" class="mt-0.5" required> Saya memahami bahwa penghapusan tidak dapat dibatalkan setelah diproses.</label>
                        <button class="btn-danger w-auto">Ajukan penghapusan akun</button>
                    </form>
                @endif
            </section>
        @endif
    </div>
</x-layouts.app>
