<x-layouts.app :title="'Peserta '.$class->batch_name" :workspace="$workspace">
    <x-slot:back><a href="{{ $workspace === 'admin' ? route('admin.programs.show', $class->program) : route('trainer.classes') }}" class="hero-back">&larr; {{ $workspace === 'admin' ? $class->program->name : 'Kelas Saya' }}</a></x-slot:back>
    <x-slot:heading>{{ $class->program->name }} — {{ $class->batch_name }}</x-slot:heading>
    <x-slot:meta><span class="badge bg-slate-100 text-slate-700">{{ \App\Modules\Learning\Models\CourseClass::STATUSES[$class->status] ?? $class->status }}</span></x-slot:meta>
    <x-slot:subtitle>{{ $class->starts_on->translatedFormat('d M Y') }} – {{ $class->ends_on->translatedFormat('d M Y') }} · {{ \App\Modules\Catalog\Models\Program::MODES[$class->mode] ?? $class->mode }} · {{ $class->enrolled_count }}/{{ $class->quota }} peserta · Trainer: {{ $class->trainers->pluck('name')->implode(', ') ?: 'belum ada' }}</x-slot:subtitle>
    @include('classes._header')
    <x-form-error field="enrollment" />
    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th scope="col">Peserta</th><th scope="col">Status</th><th scope="col">Progres</th><th scope="col">Skor Akhir</th><th scope="col">Terdaftar</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
            <tbody>
                @forelse ($enrollments as $enrollment)
                    <tr>
                        <td class="font-bold">{{ $enrollment->user->name }}<span class="block font-mono text-xs font-normal text-slate-500">{{ \App\Support\Privacy\Mask::email($enrollment->user->email) }}</span></td>
                        <td><span class="badge bg-slate-100 text-slate-700">{{ $enrollment->statusLabel() }}</span></td>
                        <td>
                            <div class="flex items-center gap-2"><div class="h-2 w-24 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-accent-500 progress-{{ (int) (round($enrollment->progress_percent / 5) * 5) }}"></div></div><span class="text-xs">{{ $enrollment->progress_percent }}%</span></div>
                        </td>
                        <td>{{ $enrollment->final_score !== null ? fmt_score($enrollment->final_score) : '—' }}</td>
                        <td class="text-xs">{{ $enrollment->enrolled_at?->timezone(display_tz())->format('d M Y') }}</td>
                        <td class="text-right">
                            @if (auth()->user()->can('enrollment.cancel') && in_array($enrollment->status, ['enrolled', 'in_progress', 'awaiting_payment'], true))
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
                    <tr><td colspan="6" class="py-8 text-center text-slate-500">Belum ada peserta.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $enrollments->links() }}</div>
</x-layouts.app>
