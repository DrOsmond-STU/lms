<x-layouts.app title="Permintaan Privasi" workspace="admin">
    <x-slot:heading>Permintaan Privasi (UU PDP)</x-slot:heading>
    <x-slot:subtitle>Permintaan penghapusan akun dari pengguna. Memproses = data pribadi dianonimkan; sertifikat dan rekam kelulusan tetap disimpan sesuai kewajiban penyelenggara.</x-slot:subtitle>
    <nav class="mb-4 flex flex-wrap gap-2 text-sm" aria-label="Status">
        @foreach (\App\Modules\Security\Models\PrivacyRequest::STATUSES as $value => $label)
            <a href="{{ route('admin.privacy.index', ['status' => $value]) }}" @class(['badge', 'bg-brand-800 text-white' => $status === $value, 'bg-slate-100 text-slate-700' => $status !== $value])>{{ $label }}</a>
        @endforeach
    </nav>
    <x-form-error field="decision_note" />
    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th scope="col">Diajukan</th><th scope="col">Pengguna</th><th scope="col">Jenis</th><th scope="col">Alasan</th><th scope="col">Keputusan</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
            <tbody>
                @forelse ($requests as $item)
                    <tr>
                        <td class="text-xs whitespace-nowrap">{{ $item->created_at->timezone(display_tz())->format('d M Y H:i') }}</td>
                        <td class="font-bold">{{ $item->user->name }}<span class="block font-mono text-xs font-normal text-slate-500">{{ \App\Support\Privacy\Mask::email($item->user->email) }} · {{ $item->user->status }}</span></td>
                        <td class="text-xs">{{ \App\Modules\Security\Models\PrivacyRequest::KINDS[$item->kind] }}</td>
                        <td class="max-w-xs text-xs">{{ $item->note ?? '—' }}</td>
                        <td class="text-xs">{{ $item->decision_note ?? '—' }}@if ($item->processed_at)<span class="block text-slate-500">{{ $item->processed_at->timezone(display_tz())->format('d M Y') }}</span>@endif</td>
                        <td class="text-right">
                            @if ($item->status === 'pending')
                                <details class="text-left"><summary class="btn-mini cursor-pointer list-none">Putuskan</summary>
                                    <form method="POST" action="{{ route('admin.privacy.decide', $item) }}" class="mt-2 space-y-2" data-confirm="Anonimisasi tidak dapat dibatalkan. Lanjutkan?">@csrf
                                        <label for="note-{{ $item->id }}" class="sr-only">Catatan keputusan</label>
                                        <input id="note-{{ $item->id }}" name="decision_note" minlength="5" maxlength="500" required class="form-input min-h-0 py-1 text-xs" placeholder="Catatan (dikirim ke pengguna bila ditolak)">
                                        <div class="flex gap-1"><button name="decision" value="process" class="btn-danger min-h-0 px-3 py-1 text-xs">Anonimkan akun</button><button name="decision" value="reject" class="btn-secondary min-h-0 px-3 py-1 text-xs">Tolak</button></div>
                                    </form>
                                </details>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-500">Tidak ada permintaan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $requests->links() }}</div>
</x-layouts.app>
