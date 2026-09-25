<x-layouts.app title="Pembayaran" workspace="participant">
    <x-slot:back><a href="{{ route('payments.mine') }}" class="hero-back">&larr; Transaksi Saya</a></x-slot:back>
    <x-slot:heading>{{ $transaction->program->name }}</x-slot:heading>
    <x-slot:subtitle>{{ $transaction->courseClass->batch_name }} · Tagihan <span class="font-mono">{{ $transaction->order_id }}</span></x-slot:subtitle>
    <x-slot:meta><span class="badge chip-{{ $transaction->statusTone() }}">{{ $transaction->statusLabel() }}</span></x-slot:meta>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if ($transaction->isSettled())
                <section class="card p-6">
                    <h2 class="font-bold text-emerald-700">Pembayaran diterima</h2>
                    <p class="mt-2 text-sm text-slate-600">Invoice <span class="font-mono font-bold">{{ $transaction->invoice_number }}</span> · diterima {{ $transaction->settled_at?->timezone(display_tz())->translatedFormat('d M Y H:i') }} {{ tz_label() }}.</p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <a href="{{ route('learning.classroom', $transaction->enrollment_id) }}" class="btn-primary w-auto">Mulai Belajar</a>
                        <a href="{{ route('payments.invoice', $transaction) }}" class="btn-secondary w-auto">Unduh Invoice (PDF)</a>
                    </div>
                </section>
            @elseif ($transaction->isPending())
                <section class="card p-6">
                    <h2 class="font-bold text-slate-800">1. Transfer ke rekening berikut</h2>
                    <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                        <div><dt class="text-xs text-slate-500">Bank</dt><dd class="font-bold">{{ $bank['bank_name'] }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Nomor rekening</dt><dd class="font-mono text-lg font-extrabold text-slate-800">{{ $bank['account_number'] }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Atas nama</dt><dd class="font-bold">{{ $bank['account_name'] }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Jumlah transfer</dt><dd class="text-lg font-extrabold text-brand-700">{{ $transaction->amountLabel() }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs text-slate-500">Batas waktu</dt><dd class="font-bold {{ $transaction->expires_at->isPast() ? 'text-rose-700' : 'text-amber-700' }}">{{ $transaction->expires_at->timezone(display_tz())->translatedFormat('l, d F Y H:i') }} {{ tz_label() }}</dd></div>
                    </dl>
                    @if ($bank['instructions'] !== '')<p class="mt-3 whitespace-pre-line text-sm text-slate-600">{{ $bank['instructions'] }}</p>@endif
                </section>

                <section class="card p-6">
                    <h2 class="font-bold text-slate-800">2. Unggah bukti transfer</h2>
                    @if ($transaction->needs_review)
                        <p class="mt-2 rounded-lg bg-sky-50 p-3 text-sm text-sky-800">Bukti Anda ({{ $transaction->proof_submitted_at?->timezone(display_tz())->translatedFormat('d M Y H:i') }}) sedang diverifikasi Admin Keuangan. Anda akan diberi tahu setelah dikonfirmasi.</p>
                    @elseif ($transaction->review_note)
                        <p class="mt-2 rounded-lg bg-rose-50 p-3 text-sm text-rose-800"><span class="font-bold">Bukti sebelumnya ditolak:</span> {{ $transaction->review_note }} Silakan unggah ulang.</p>
                    @endif
                    @if ($transaction->proof)
                        <p class="mt-2 text-sm"><a href="{{ route('payments.proof.view', $transaction) }}" target="_blank" rel="noopener" class="font-bold text-link hover:underline">Lihat bukti yang diunggah</a></p>
                    @endif
                    @if ($transaction->acceptsProof())
                        <form method="POST" enctype="multipart/form-data" action="{{ route('payments.proof', $transaction) }}" class="mt-4 space-y-4">
                            @csrf
                            <div>
                                <label for="proof" class="form-label">Berkas bukti (JPG, PNG, atau PDF; maks. 5 MB)</label>
                                <input id="proof" name="proof" type="file" accept="image/jpeg,image/png,application/pdf" required class="form-input">
                                <x-form-error field="proof" />
                            </div>
                            <div>
                                <label for="note" class="form-label">Catatan (opsional) — bank pengirim, nama pemilik rekening, tanggal transfer</label>
                                <input id="note" name="note" value="{{ old('note', $transaction->proof_note) }}" maxlength="300" class="form-input">
                                <x-form-error field="note" />
                            </div>
                            <details class="rounded-lg border border-slate-200 p-3 text-sm" @if ($errors->hasAny(["billing_name", "billing_tax_id", "billing_address"])) open @endif>
                                <summary class="cursor-pointer font-bold text-slate-700">Data invoice (opsional, untuk faktur atas nama perusahaan)</summary>
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <div><label for="billing_name" class="form-label">Nama pada invoice</label><input id="billing_name" name="billing_name" value="{{ old('billing_name', $transaction->billing_name) }}" maxlength="160" class="form-input"><x-form-error field="billing_name" /></div>
                                    <div><label for="billing_tax_id" class="form-label">NPWP</label><input id="billing_tax_id" name="billing_tax_id" value="{{ old('billing_tax_id') }}" maxlength="25" class="form-input" autocomplete="off"><x-form-error field="billing_tax_id" /></div>
                                    <div class="sm:col-span-2"><label for="billing_address" class="form-label">Alamat</label><textarea id="billing_address" name="billing_address" maxlength="300" rows="2" class="form-input">{{ old('billing_address') }}</textarea><x-form-error field="billing_address" /></div>
                                </div>
                            </details>
                            <button type="submit" class="btn-primary w-auto">{{ $transaction->proof ? 'Ganti Bukti Transfer' : 'Kirim Bukti Transfer' }}</button>
                        </form>
                    @else
                        <p class="mt-3 text-sm text-rose-700">Batas waktu pembayaran sudah lewat. Tagihan akan ditutup otomatis; silakan daftar ulang bila masih berminat.</p>
                    @endif
                </section>
            @else
                <section class="card p-6">
                    <h2 class="font-bold text-slate-800">Tagihan {{ strtolower($transaction->statusLabel()) }}</h2>
                    @if ($transaction->review_note)<p class="mt-2 text-sm text-slate-600">{{ $transaction->review_note }}</p>@endif
                    <a href="{{ route('catalog.participant.show', $transaction->program->slug) }}" class="btn-secondary mt-4 w-auto">Lihat program</a>
                </section>
            @endif
        </div>

        <aside class="space-y-6">
            <section class="card p-5 text-sm">
                <h2 class="font-bold text-slate-800">Ringkasan</h2>
                <dl class="mt-3 space-y-2">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Harga program</dt><dd class="font-bold">{{ \App\Modules\Payment\Models\PaymentTransaction::rupiah($transaction->list_price) }}</dd></div>
                    @if ($transaction->discount_amount > 0)<div class="flex justify-between gap-3"><dt class="text-slate-500">Diskon</dt><dd class="font-bold">-{{ \App\Modules\Payment\Models\PaymentTransaction::rupiah($transaction->discount_amount) }}</dd></div>@endif
                    <div class="flex justify-between gap-3 border-t border-slate-100 pt-2"><dt class="text-slate-500">Total</dt><dd class="text-base font-extrabold">{{ $transaction->amountLabel() }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Metode</dt><dd>{{ \App\Modules\Payment\Models\PaymentTransaction::METHODS[$transaction->payment_method] ?? $transaction->payment_method }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Dibuat</dt><dd>{{ $transaction->created_at->timezone(display_tz())->translatedFormat('d M Y H:i') }}</dd></div>
                </dl>
            </section>
            <section class="card p-5 text-sm">
                <h2 class="font-bold text-slate-800">Riwayat</h2>
                <ol class="mt-3 space-y-2">
                    @foreach ($events as $event)
                        <li><span class="block text-xs text-slate-500">{{ $event->created_at->timezone(display_tz())->translatedFormat('d M Y H:i') }}</span>{{ $event->label() }}@if (($event->payload['reason'] ?? null) && $event->event !== 'failed')<span class="block text-xs text-slate-500">{{ $event->payload['reason'] }}</span>@endif</li>
                    @endforeach
                </ol>
            </section>
        </aside>
    </div>
</x-layouts.app>
