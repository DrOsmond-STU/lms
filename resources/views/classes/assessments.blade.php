<x-layouts.app :title="'Asesmen '.$class->batch_name" :workspace="$workspace">
    <x-slot:back><a href="{{ $workspace === 'admin' ? route('admin.programs.show', $class->program) : route('trainer.classes') }}" class="hero-back">&larr; {{ $workspace === 'admin' ? $class->program->name : 'Kelas Saya' }}</a></x-slot:back>
    <x-slot:heading>{{ $class->program->name }} — {{ $class->batch_name }}</x-slot:heading>
    <x-slot:meta><span class="badge bg-slate-100 text-slate-700">{{ \App\Modules\Learning\Models\CourseClass::STATUSES[$class->status] ?? $class->status }}</span></x-slot:meta>
    <x-slot:subtitle>{{ $class->starts_on->translatedFormat('d M Y') }} – {{ $class->ends_on->translatedFormat('d M Y') }} · {{ \App\Modules\Catalog\Models\Program::MODES[$class->mode] ?? $class->mode }} · {{ $class->enrolled_count }}/{{ $class->quota }} peserta · Trainer: {{ $class->trainers->pluck('name')->implode(', ') ?: 'belum ada' }}</x-slot:subtitle>
    @include('classes._header')
    <x-form-error field="assessment" />
    @if ($pendingGrading > 0)
        <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="status">{{ $pendingGrading }} attempt menunggu penilaian manual (esai).</div>
    @endif
    @if ($canEditAssessments)
        <div class="mb-4 flex flex-wrap gap-2">
            <a href="{{ route('assessments.create', [$class, 'jenis' => 'pretest']) }}" class="btn-secondary">+ Pre-test</a>
            <a href="{{ route('assessments.create', [$class, 'jenis' => 'quiz']) }}" class="btn-secondary">+ Kuis</a>
            <a href="{{ route('assessments.create', [$class, 'jenis' => 'posttest']) }}" class="btn-secondary">+ Post-test</a>
            <a href="{{ route('assessments.create', [$class, 'jenis' => 'final_exam']) }}" class="btn-secondary">+ Ujian Akhir</a>
            <a href="{{ route('banks.index', $class->program) }}" class="btn-secondary">Bank Soal Program</a>
        </div>
    @endif
    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th scope="col">Asesmen</th><th scope="col">Jenis</th><th scope="col">Soal</th><th scope="col">Durasi</th><th scope="col">Kesempatan</th><th scope="col">Skor min.</th><th scope="col">Jendela</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
            <tbody>
                @forelse ($assessments as $assessment)
                    <tr>
                        <td class="font-bold">{{ $assessment->title }}<span class="block text-xs font-normal text-slate-500">Bank: {{ $assessment->bank->name }}</span></td>
                        <td>{{ \App\Modules\Assessment\Models\Assessment::KINDS[$assessment->kind] }}{{ $assessment->is_required ? '' : ' (opsional)' }}</td>
                        <td>{{ $assessment->question_count }}</td>
                        <td>{{ $assessment->duration_minutes }} mnt</td>
                        <td>{{ $assessment->max_attempts }}×</td>
                        <td>{{ fmt_score($assessment->passing_score) }}</td>
                        <td class="text-xs">{{ $assessment->opens_at?->timezone(display_tz())->format('d/m H:i') ?? 'kapan saja' }} – {{ $assessment->closes_at?->timezone(display_tz())->format('d/m H:i') ?? '∞' }}</td>
                        <td class="text-right text-xs whitespace-nowrap">
                            <a href="{{ route('assessments.attempts', [$class, $assessment]) }}" class="font-bold text-link hover:underline">Attempt</a>
                            @if ($canEditAssessments)
                                · <a href="{{ route('assessments.edit', [$class, $assessment]) }}" class="font-bold text-link hover:underline">Ubah</a>
                                <form method="POST" action="{{ route('assessments.destroy', [$class, $assessment]) }}" class="inline" data-confirm="Hapus asesmen ini? Hanya bisa bila belum pernah dikerjakan.">@csrf @method('DELETE')<button class="btn-mini-danger ml-1">Hapus</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-8 text-center text-slate-500">Belum ada asesmen.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
