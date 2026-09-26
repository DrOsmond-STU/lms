<x-layouts.app :title="'Peserta '.$class->batch_name" :workspace="$workspace">
    <x-slot:back><a href="{{ $workspace === 'admin' ? route('admin.programs.show', $class->program) : route('trainer.classes') }}" class="hero-back">&larr; {{ $workspace === 'admin' ? $class->program->name : 'Kelas Saya' }}</a></x-slot:back>
    <x-slot:heading>{{ $class->program->name }} — {{ $class->batch_name }}</x-slot:heading>
    <x-slot:meta><span class="badge bg-slate-100 text-slate-700">{{ \App\Modules\Learning\Models\CourseClass::STATUSES[$class->status] ?? $class->status }}</span></x-slot:meta>
    <x-slot:subtitle>{{ $class->starts_on->translatedFormat('d M Y') }} – {{ $class->ends_on->translatedFormat('d M Y') }} · {{ \App\Modules\Catalog\Models\Program::MODES[$class->mode] ?? $class->mode }} · {{ $class->enrolled_count }}/{{ $class->quota }} peserta · Trainer: {{ $class->trainers->pluck('name')->implode(', ') ?: 'belum ada' }}</x-slot:subtitle>
    @include('classes._header')
    <x-form-error field="enrollment" />
    <x-form-error field="reason" />
    @if ($enrollments->firstWhere('status', 'applied'))
        <p class="mb-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">Kelas ini mensyaratkan persetujuan pendaftaran. Setujui atau tolak pendaftar yang menunggu; kursi sudah dipesan untuk mereka.</p>
    @endif
    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th scope="col">Peserta</th><th scope="col">Status</th><th scope="col">Kelompok</th><th scope="col">Progres</th><th scope="col">Skor Akhir</th><th scope="col">Terdaftar</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
            <tbody>
                @forelse ($enrollments as $enrollment)
                    <tr>
                        <td class="font-bold">{{ $enrollment->user->name }}<span class="block font-mono text-xs font-normal text-slate-500">{{ \App\Support\Privacy\Mask::email($enrollment->user->email) }}</span></td>
                        <td><span @class(['badge', 'bg-amber-50 text-amber-800' => $enrollment->status === 'applied', 'bg-slate-100 text-slate-700' => $enrollment->status !== 'applied'])>{{ $enrollment->statusLabel() }}</span></td>
                        <td class="text-xs">{{ $enrollment->group?->name ?? '—' }}</td>
                        <td>
                            <div class="flex items-center gap-2"><div class="h-2 w-24 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-accent-500 progress-{{ (int) (round($enrollment->progress_percent / 5) * 5) }}"></div></div><span class="text-xs">{{ $enrollment->progress_percent }}%</span></div>
                        </td>
                        <td>{{ $enrollment->final_score !== null ? fmt_score($enrollment->final_score) : '—' }}</td>
                        <td class="text-xs">{{ $enrollment->enrolled_at?->timezone(display_tz())->format('d M Y') }}</td>
                        <td class="text-right">
                            @if ($canApprove && $enrollment->status === 'applied')
                                <form method="POST" action="{{ route('classes.enrollments.decide', [$class, $enrollment]) }}" class="inline">@csrf<input type="hidden" name="decision" value="approve"><button class="btn-mini">Setujui</button></form>
                                <details class="mt-1 text-left"><summary class="btn-mini-danger cursor-pointer list-none">Tolak</summary>
                                    <form method="POST" action="{{ route('classes.enrollments.decide', [$class, $enrollment]) }}" class="mt-2 flex gap-1">@csrf<input type="hidden" name="decision" value="reject">
                                        <label for="reject-{{ $enrollment->id }}" class="sr-only">Alasan penolakan</label>
                                        <input id="reject-{{ $enrollment->id }}" name="reason" minlength="5" maxlength="300" required class="form-input min-h-0 py-1 text-xs" placeholder="Alasan penolakan">
                                        <button class="btn-danger min-h-0 px-3 py-1 text-xs">Tolak</button>
                                    </form>
                                </details>
                            @elseif (auth()->user()->can('enrollment.cancel') && in_array($enrollment->status, \App\Modules\Enrollment\Models\Enrollment::CANCELLABLE, true))
                                <details class="text-left"><summary class="btn-mini-danger cursor-pointer list-none">Batalkan</summary>
                                    <form method="POST" action="{{ route('classes.enrollments.cancel', [$class, $enrollment]) }}" class="mt-2 flex gap-1" data-confirm="Batalkan enrollment peserta ini? Kuota kelas akan kembali.">@csrf
                                        <label for="cancel-{{ $enrollment->id }}" class="sr-only">Alasan</label>
                                        <input id="cancel-{{ $enrollment->id }}" name="reason" minlength="5" maxlength="300" required class="form-input min-h-0 py-1 text-xs" placeholder="Alasan pembatalan">
                                        <button class="btn-danger min-h-0 px-3 py-1 text-xs">Batalkan</button>
                                    </form>
                                </details>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-slate-500">Belum ada peserta.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $enrollments->links() }}</div>
</x-layouts.app>
