<x-layouts.app :title="'Attempt '.$assessment->title" :workspace="$workspace">
    <x-slot:back><a href="{{ route('classes.assessments', $class) }}" class="hero-back">&larr; Asesmen {{ $class->batch_name }}</a></x-slot:back>
    <x-slot:heading>{{ $assessment->title }} — Attempt</x-slot:heading>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card overflow-x-auto xl:col-span-2">
            <table class="data-table">
                <thead><tr><th scope="col">Peserta</th><th scope="col">#</th><th scope="col">Status</th><th scope="col">Skor</th><th scope="col">Indikator</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
                <tbody>
                    @forelse ($attempts as $attempt)
                        <tr>
                            <td class="font-bold">{{ $attempt->enrollment->user->name }}<span class="block text-xs font-normal text-slate-500">{{ $attempt->started_at->timezone('Asia/Jakarta')->format('d M H:i') }}</span></td>
                            <td>{{ $attempt->attempt_no }}</td>
                            <td><span class="badge bg-slate-100 text-slate-700">{{ \App\Modules\Assessment\Models\ExamAttempt::STATUSES[$attempt->status] }}</span></td>
                            <td>{{ $attempt->score !== null ? rtrim(rtrim($attempt->score, '0'), '.') : '—' }}{{ $attempt->passed === true ? ' ✓' : '' }}</td>
                            <td class="text-xs text-slate-600">
                                @foreach ($attempt->integrity_flags ?? [] as $flag => $count){{ ['blur' => 'pindah tab', 'copy' => 'salin', 'paste' => 'tempel'][$flag] ?? $flag }}: {{ $count }}<br>@endforeach
                            </td>
                            <td class="text-right text-xs whitespace-nowrap">
                                @if (in_array($attempt->status, ['submitted', 'auto_submitted'], true))
                                    <a href="{{ route('assessments.grade', [$class, $attempt]) }}" class="font-bold text-link hover:underline">Nilai</a>
                                @endif
                                @if ($attempt->status !== 'voided')
                                    <details class="inline-block text-left"><summary class="cursor-pointer font-bold text-rose-700">Batalkan</summary>
                                        <form method="POST" action="{{ route('assessments.void', [$class, $attempt]) }}" class="mt-1 flex gap-1">@csrf
                                            <label for="void-{{ $attempt->id }}" class="sr-only">Alasan</label>
                                            <input id="void-{{ $attempt->id }}" name="reason" minlength="5" maxlength="500" required class="form-input py-1 text-xs" placeholder="Alasan">
                                            <button class="btn-danger px-2 py-1 text-xs">OK</button>
                                        </form>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-slate-500">Belum ada attempt.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <x-form-error field="reason" />
        </div>
        <section class="card space-y-3 p-5" aria-labelledby="grant-heading">
            <h2 id="grant-heading" class="font-bold text-slate-800">Kesempatan Tambahan</h2>
            <p class="text-xs text-slate-500">Alasan wajib dan tercatat di jejak audit. Peserta dinotifikasi.</p>
            <form method="POST" action="{{ route('assessments.grant', [$class, $assessment]) }}" class="space-y-2" novalidate>@csrf
                <label for="enrollment_id" class="form-label">Peserta</label>
                <select id="enrollment_id" name="enrollment_id" class="form-select">
                    @foreach ($enrollments as $enrollment)
                        <option value="{{ $enrollment->id }}">{{ $enrollment->user->name }} ({{ $enrollment->statusLabel() }})</option>
                    @endforeach
                </select>
                <label for="extra_attempts" class="form-label">Tambahan</label>
                <input id="extra_attempts" name="extra_attempts" type="number" min="1" max="5" value="1" class="form-input">
                <label for="grant_reason" class="form-label">Alasan</label>
                <input id="grant_reason" name="reason" minlength="5" maxlength="500" class="form-input">
                <x-form-error field="extra_attempts" />
                <button class="btn-secondary">Berikan</button>
            </form>
        </section>
    </div>
    <div class="mt-4">{{ $attempts->links() }}</div>
</x-layouts.app>
