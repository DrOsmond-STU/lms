<x-layouts.app title="Tinjau Approval" workspace="admin">
    <a href="{{ route('admin.approvals.index') }}" class="text-sm font-bold text-brand-700 hover:underline">&larr; Approval Sertifikat</a>
    <h1 class="mt-2 text-xl font-extrabold text-slate-800">{{ $enrollment->user->name }}</h1>
    <p class="mt-0.5 mb-6 text-sm text-slate-600">{{ $enrollment->program->name }} · {{ $enrollment->courseClass->batch_name }}</p>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card p-6">
                <h2 class="font-bold text-slate-800">Ringkasan Bukti</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    <li>Materi wajib selesai: <span class="font-bold">{{ $check['lessons_done'] }}/{{ $check['lessons_total'] }}</span></li>
                    <li>Kuis wajib: <span class="font-bold">{{ $check['quizzes_ok'] ? 'lulus semua' : 'belum' }}</span></li>
                    <li>Ujian akhir terbaik: <span class="font-bold">{{ $check['final_score'] ?? '—' }}</span> (minimal {{ $check['min_score'] }})</li>
                    <li>Indikator integritas progres: <span class="font-bold">{{ $progressFlags }}</span> lesson ditandai</li>
                </ul>
            </section>
            <section class="card overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th scope="col">Asesmen</th><th scope="col">#</th><th scope="col">Status</th><th scope="col">Skor</th><th scope="col">Indikator</th></tr></thead>
                    <tbody>
                        @forelse ($attempts as $attempt)
                            <tr>
                                <td>{{ $attempt->title }} <span class="text-xs text-slate-500">({{ \App\Modules\Assessment\Models\Assessment::KINDS[$attempt->kind] }})</span></td>
                                <td>{{ $attempt->attempt_no }}</td>
                                <td>{{ \App\Modules\Assessment\Models\ExamAttempt::STATUSES[$attempt->status] ?? $attempt->status }}</td>
                                <td>{{ $attempt->score ?? '—' }}{{ $attempt->passed ? ' ✓' : '' }}</td>
                                <td class="text-xs">{{ $attempt->integrity_flags ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-slate-500">Tidak ada attempt.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>
        <aside class="space-y-6">
            <x-form-error field="enrollment" />
            @if ($blocker)
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800" role="alert">{{ $blocker }}</div>
            @else
                @can('certificate.approve')
                    <form method="POST" action="{{ route('admin.approvals.approve', $enrollment) }}" class="card p-5" data-confirm="Setujui dan terbitkan sertifikat untuk {{ $enrollment->user->name }}?">@csrf
                        <h2 class="font-bold text-slate-800">Setujui</h2>
                        <p class="mt-1 text-xs text-slate-500">Nomor & kode verifikasi dibuat otomatis; PDF ditandatangani digital.</p>
                        <button class="btn-primary mt-3">Setujui &amp; Terbitkan</button>
                    </form>
                @endcan
            @endif
            @can('certificate.reject')
                <form method="POST" action="{{ route('admin.approvals.reject', $enrollment) }}" class="card space-y-2 p-5" novalidate>@csrf
                    <h2 class="font-bold text-slate-800">Tolak</h2>
                    <label for="reason" class="form-label">Alasan (dikirim ke peserta)</label>
                    <textarea id="reason" name="reason" rows="3" minlength="5" maxlength="500" class="form-input"></textarea>
                    <x-form-error field="reason" />
                    <button class="btn-danger">Tolak</button>
                </form>
            @endcan
        </aside>
    </div>
</x-layouts.app>
