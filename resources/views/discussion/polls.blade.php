<x-layouts.app :title="'Polling '.$class->batch_name" :workspace="$member['workspace']">
    <x-slot:back><a href="{{ app(\App\Modules\Discussion\Services\ClassMembership::class)->backUrl($member['workspace'], $class, $member['enrollment']) }}" class="hero-back">&larr; {{ $class->program->name }}</a></x-slot:back>
    <x-slot:heading>Polling — {{ $class->batch_name }}</x-slot:heading>
    <x-slot:subtitle>Jajak pendapat cepat dari trainer. Hasil ditampilkan agregat{{ '' }}.</x-slot:subtitle>
    @include('discussion._nav', ['tab' => 'polls'])
    <x-form-error field="options" />
    <x-form-error field="question" />

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-4 xl:col-span-2">
            @forelse ($polls as $poll)
                @php($vote = $mine->get($poll->id))
                @php($tally = $poll->tally())
                @php($total = max(1, $poll->votes->count()))
                <article class="card p-5">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <h2 class="font-bold text-slate-800">{{ $poll->question }}</h2>
                        <span @class(['badge', 'bg-emerald-50 text-emerald-700' => $poll->isOpen(), 'bg-slate-100 text-slate-600' => ! $poll->isOpen()])>{{ $poll->isOpen() ? 'Dibuka' : 'Ditutup' }}</span>
                    </div>
                    <p class="text-xs text-slate-500">{{ $poll->votes->count() }} suara · {{ $poll->is_anonymous ? 'anonim' : 'tidak anonim' }}{{ $poll->multiple ? ' · boleh lebih dari satu' : '' }}{{ $poll->closes_at ? ' · ditutup '.$poll->closes_at->timezone(display_tz())->translatedFormat('d M H:i') : '' }}</p>
                    @if ($vote || ! $poll->isOpen() || $member['role'] !== 'participant')
                        <ul class="mt-3 space-y-2 text-sm">
                            @foreach ($poll->options as $index => $option)
                                @php($pct = (int) round(($tally[$index] ?? 0) * 100 / $total))
                                <li>
                                    <div class="flex justify-between"><span>{{ $option }}{{ $vote && in_array($index, $vote->option_indexes, true) ? ' ✓' : '' }}</span><span class="font-mono text-xs">{{ $tally[$index] ?? 0 }} ({{ $pct }}%)</span></div>
                                    <div class="bar mt-1"><span class="progress-{{ (int) (round($pct / 5) * 5) }}"></span></div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($poll->isOpen() && $member['role'] === 'participant')
                        <form method="POST" action="{{ route('discussion.polls.vote', [$class, $poll]) }}" class="mt-3 space-y-2 text-sm">@csrf
                            @foreach ($poll->options as $index => $option)
                                <label class="flex items-center gap-2"><input type="{{ $poll->multiple ? 'checkbox' : 'radio' }}" name="options[]" value="{{ $index }}" @checked($vote && in_array($index, $vote->option_indexes, true))> {{ $option }}</label>
                            @endforeach
                            <button class="btn-secondary">{{ $vote ? 'Ubah suara' : 'Kirim suara' }}</button>
                        </form>
                    @endif
                    @if ($member['role'] === 'moderator')
                        <div class="mt-3 flex gap-2 text-xs">
                            <form method="POST" action="{{ route('discussion.polls.close', [$class, $poll]) }}">@csrf<button class="btn-secondary min-h-0 px-3 py-1">{{ $poll->is_closed ? 'Buka kembali' : 'Tutup' }}</button></form>
                            <form method="POST" action="{{ route('discussion.polls.destroy', [$class, $poll]) }}" data-confirm="Hapus polling beserta suaranya?">@csrf @method('DELETE')<button class="btn-mini-danger">Hapus</button></form>
                        </div>
                    @endif
                </article>
            @empty
                <div class="card p-8 text-center text-sm text-slate-500">Belum ada polling.</div>
            @endforelse
        </div>
        <aside>
            @if ($member['role'] === 'moderator')
                <section class="card p-5" aria-labelledby="new-poll">
                    <h2 id="new-poll" class="font-bold text-slate-800">Buat Polling</h2>
                    <form method="POST" action="{{ route('discussion.polls.store', $class) }}" class="mt-3 space-y-2 text-sm" novalidate>@csrf
                        <div><label for="question" class="form-label">Pertanyaan</label><input id="question" name="question" required maxlength="300" class="form-input py-1.5"></div>
                        @for ($i = 0; $i < 5; $i++)<input name="options[]" maxlength="200" class="form-input py-1.5" placeholder="Opsi {{ $i + 1 }}{{ $i > 1 ? ' (opsional)' : '' }}" aria-label="Opsi {{ $i + 1 }}">@endfor
                        <label class="flex items-center gap-2"><input type="checkbox" name="is_anonymous" value="1" checked> Anonim</label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="multiple" value="1"> Boleh memilih lebih dari satu</label>
                        <div><label for="closes_at" class="form-label">Ditutup otomatis (opsional)</label><input id="closes_at" name="closes_at" type="datetime-local" class="form-input py-1.5"></div>
                        <button class="btn-primary w-full">Buat</button>
                    </form>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.app>
