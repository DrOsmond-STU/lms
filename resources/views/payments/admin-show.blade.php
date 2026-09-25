<x-layouts.app title="Tinjau Pembayaran" workspace="admin">
    <x-slot:back><a href="{{ route('admin.payments.index') }}" class="hero-back">&larr; Pembayaran</a></x-slot:back>
    <x-slot:heading>{{ $transaction->user->name }}</x-slot:heading>
    <x-slot:subtitle>{{ $transaction->program->name }} · {{ $transaction->courseClass->batch_name }} · <span class="font-mono">{{ $transaction->order_id }}</span></x-slot:subtitle>
    <x-slot:meta><span class="badge chip-{{ $transaction->statusTone() }}">{{ $transaction->statusLabel() }}</span>@if ($transaction->needs_review && $transaction->isPending())<span class="badge chip-medium">perlu verifikasi</span>@endif</x-slot:meta>

    <x-form-error field="reason" />
    <x-form-error field="note" />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card p-6">
                <h2 class="font-bold text-slate-800">Tagihan</h2>
                <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs text-slate-500">Nilai</dt><dd class="text-lg font-extrabold">{{ $transaction->amountLabel() }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Batas waktu</dt><dd class="font-bold">{{ $transaction->expires_at->timezone(display_tz())->translatedFormat('d M Y H:i') }} {{ tz_label() }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Peserta</dt><dd>{{ $transaction->user->name }} · <span class="font-mono text-xs">{{ \App\Support\Privacy\Mask::email($transaction->user->email) }}</span></dd></div>
                    <div><dt class="text-xs text-slate-500">Enrollment</dt><dd>{{ $transaction->enrollment->statusLabel() }}</dd></div>
                    @if ($transaction->invoice_number)<div><dt class="text-xs text-slate-500">Invoice</dt><dd class="font-mono font-bold">{{ $transaction->invoice_number }} · <a href="{{ route('admin.payments.invoice', $transaction) }}" class="text-link hover:underline">PDF</a></dd></div>@endif
                    @if ($transaction->settler)<div><dt class="text-xs text-slate-500">Dikonfirmasi oleh</dt><dd>{{ $transaction->settler->name }} · {{ $transaction->settled_at?->timezone(display_tz())->translatedFormat('d M Y H:i') }}</dd></div>@endif
                    @if ($transaction->billing_name)<div class="sm:col-span-2"><dt class="text-xs text-slate-500">Data invoice peserta</dt><dd>{{ $transaction->billing_name }}@if ($transaction->billingTaxId()) · NPWP {{ $transaction->billingTaxId() }}@endif @if ($transaction->billingAddress())<span class="block text-xs text-slate-500">{{ $transaction->billingAddress() }}</span>@endif</dd></div>@endif
                </dl>
            </section>

            <section class="card p-6">
                <h2 class="font-bold text-slate-800">Bukti transfer</h2>
                @if ($transaction->proof)
                    <p class="mt-2 text-sm text-slate-600">Diunggah {{ $transaction->proof_submitted_at?->timezone(display_tz())->translatedFormat('d M Y H:i') }} · {{ $transaction->proof->original_filename }} · {{ number_format($transaction->proof->size_bytes / 1024, 0, ',', '.') }} KB · pindai: {{ $transaction->proof->scan_status }}</p>
                    @if ($transaction->proof_note)<p class="mt-1 text-sm"><span class="text-slate-500">Catatan peserta:</span> {{ $transaction->proof_note }}</p>@endif
                    @if (str_starts_with($transaction->proof->mime_type, 'image/'))
                        <a href="{{ route('admin.payments.proof', $transaction) }}" target="_blank" rel="noopener" class="mt-3 block"><img src="{{ route('admin.payments.proof', $transaction) }}" alt="Bukti transfer {{ $transaction->order_id }}" class="max-h-96 rounded-lg border border-slate-200"></a>
                    @else
                        <a href="{{ route('admin.payments.proof', $transaction) }}" target="_blank" rel="noopener" class="btn-secondary mt-3 w-auto">Buka bukti (PDF)</a>
                    @endif
                    @if ($transaction->review_note && ! $transaction->isSettled())<p class="mt-3 rounded-lg bg-rose-50 p-3 text-sm text-rose-800">Catatan verifikasi terakhir: {{ $transaction->review_note }}</p>@endif
                @else
                    <p class="mt-2 text-sm text-slate-500">Peserta belum mengunggah bukti.</p>
                @endif
            </section>

            @if ($transaction->isPending())
                <section class="card p-6">
                    <h2 class="font-bold text-slate-800">Keputusan</h2>
                    <p class="mt-1 text-xs text-slate-500">Setiap keputusan memerlukan konfirmasi identitas dan tercatat di jejak audit. Peserta tidak dapat memproses tagihannya sendiri.</p>
                    <div class="mt-4 grid gap-4 md:grid-cols-3">
                        <form method="POST" action="{{ route('admin.payments.settle', $transaction) }}" class="space-y-2 rounded-lg border border-emerald-200 p-3" data-confirm="Konfirmasi dana {{ $transaction->amountLabel() }} sudah diterima?">
                            @csrf
                            <label for="settle-note" class="form-label">Konfirmasi lunas</label>
                            <input id="settle-note" name="note" maxlength="300" placeholder="Catatan (opsional): tanggal & referensi mutasi" class="form-input">
                            <button type="submit" class="btn-primary w-full" @disabled(! $transaction->proof || $transaction->approval_request_id)>{{ $transaction->gross_amount > $threshold ? 'Ajukan konfirmasi (perlu admin kedua)' : 'Konfirmasi Lunas' }}</button>
                            @if ($transaction->approval_request_id)<p class="text-xs text-amber-700">Menunggu keputusan admin kedua di <a href="{{ route('admin.second-approvals.index') }}" class="font-bold text-link">Persetujuan Kedua</a>.</p>@endif
                        </form>
                        <form method="POST" action="{{ route('admin.payments.reject-proof', $transaction) }}" class="space-y-2 rounded-lg border border-amber-200 p-3">
                            @csrf
                            <label for="reject-reason" class="form-label">Tolak bukti (minta unggah ulang)</label>
                            <input id="reject-reason" name="reason" maxlength="500" minlength="5" required placeholder="Alasan, mis. nominal tidak sesuai" class="form-input">
                            <button type="submit" class="btn-secondary w-full" @disabled(! $transaction->needs_review)>Tolak Bukti</button>
                        </form>
                        <form method="POST" action="{{ route('admin.payments.fail', $transaction) }}" class="space-y-2 rounded-lg border border-rose-200 p-3" data-confirm="Batalkan tagihan dan pendaftaran peserta ini?">
                            @csrf
                            <label for="fail-reason" class="form-label">Batalkan tagihan</label>
                            <input id="fail-reason" name="reason" maxlength="500" minlength="5" required placeholder="Alasan pembatalan" class="form-input">
                            <button type="submit" class="btn-mini-danger w-full">Batalkan &amp; lepas kursi</button>
                        </form>
                    </div>
                </section>
            @endif
        </div>

        <aside class="card p-5 text-sm">
            <h2 class="font-bold text-slate-800">Riwayat</h2>
            <ol class="mt-3 space-y-2">
                @foreach ($events as $event)
                    <li>
                        <span class="block text-xs text-slate-500">{{ $event->created_at->timezone(display_tz())->translatedFormat('d M Y H:i') }} · {{ $event->actor?->name ?? 'Sistem' }}</span>
                        {{ $event->label() }}
                        @if ($event->payload['reason'] ?? null)<span class="block text-xs text-slate-500">{{ $event->payload['reason'] }}</span>@endif
                        @if ($event->payload['invoice_number'] ?? null)<span class="block font-mono text-xs text-slate-500">{{ $event->payload['invoice_number'] }}</span>@endif
                    </li>
                @endforeach
            </ol>
        </aside>
    </div>
</x-layouts.app>
