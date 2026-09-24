<x-layouts.app title="Persetujuan Kedua" workspace="admin">
    <x-slot:heading>Persetujuan Kedua</x-slot:heading>
    <x-slot:subtitle>Aksi berdampak tinggi dieksekusi setelah disetujui admin lain (maker–checker). Pengaju tidak dapat memutus permintaannya sendiri.</x-slot:subtitle>

    <x-form-error field="decision" />
    <div class="space-y-4">
        @forelse ($open as $item)
            <article class="card p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-bold text-slate-800">{{ \App\Modules\Access\Models\ApprovalRequest::ACTIONS[$item->action] ?? $item->action }}</h2>
                        <p class="text-xs text-slate-500">Diajukan {{ $item->requester->name }} · {{ $item->requested_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} · kedaluwarsa {{ $item->expires_at->timezone('Asia/Jakarta')->format('d M Y') }}</p>
                        <p class="mt-2 text-sm">Alasan: {{ $item->reason }}</p>
                        <p class="mt-1 font-mono text-xs text-slate-500">@foreach ($item->payload as $key => $value){{ $key }}={{ is_scalar($value) ? $value : json_encode($value) }} @endforeach</p>
                    </div>
                    @if ($decidable[$item->id])
                        <form method="POST" action="{{ route('admin.second-approvals.decide', $item) }}" class="flex flex-wrap items-end gap-2">@csrf
                            <label for="r-{{ $item->id }}" class="sr-only">Catatan</label>
                            <input id="r-{{ $item->id }}" name="reason" maxlength="500" placeholder="Catatan (opsional)" class="form-input py-1.5 text-sm">
                            <button name="decision" value="approve" class="btn-primary w-auto">Setujui</button>
                            <button name="decision" value="reject" class="btn-secondary">Tolak</button>
                        </form>
                    @else
                        <span class="text-xs text-slate-500">{{ $item->requested_by === auth()->id() ? 'Menunggu admin lain.' : 'Anda tidak berwenang memutus.' }}</span>
                    @endif
                </div>
            </article>
        @empty
            <div class="card p-8 text-center text-sm text-slate-500">Tidak ada permintaan terbuka.</div>
        @endforelse
    </div>
    @if ($recent->isNotEmpty())
        <h2 class="mt-8 mb-3 font-bold text-slate-800">Riwayat terbaru</h2>
        <ul class="card divide-y divide-slate-100 text-sm">
            @foreach ($recent as $item)
                <li class="flex justify-between px-5 py-3"><span>{{ \App\Modules\Access\Models\ApprovalRequest::ACTIONS[$item->action] ?? $item->action }} — {{ $item->requester->name }}</span><span class="text-xs {{ $item->decision === 'approved' ? 'text-emerald-700' : 'text-rose-700' }}">{{ $item->decision === 'approved' ? 'Disetujui' : 'Ditolak' }} {{ $item->decided_at?->timezone('Asia/Jakarta')->format('d M H:i') }}</span></li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
