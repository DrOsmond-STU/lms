<x-layouts.app :title="'Tugas '.$class->batch_name" :workspace="$workspace">
    <x-slot:back><a href="{{ $workspace === 'admin' ? route('admin.programs.show', $class->program) : route('trainer.classes') }}" class="hero-back">&larr; {{ $workspace === 'admin' ? $class->program->name : 'Kelas Saya' }}</a></x-slot:back>
    <x-slot:heading>{{ $class->program->name }} — {{ $class->batch_name }}</x-slot:heading>
    <x-slot:meta><span class="badge bg-slate-100 text-slate-700">{{ \App\Modules\Learning\Models\CourseClass::STATUSES[$class->status] ?? $class->status }}</span></x-slot:meta>
    <x-slot:subtitle>Tugas dengan pengumpulan teks/berkas, tenggat, rubrik, dan penilaian manual. Tugas wajib menjadi syarat kelulusan.</x-slot:subtitle>
    @include('classes._header')
    <x-form-error field="assignment" />
    @if ($canEditAssignments)
        <div class="mb-4"><a href="{{ route('assignments.create', $class) }}" class="btn-secondary">+ Tugas Baru</a></div>
    @endif
    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th scope="col">Tugas</th><th scope="col">Tenggat</th><th scope="col">Skor maks.</th><th scope="col">Pengumpulan</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
            <tbody>
                @forelse ($assignments as $assignment)
                    <tr>
                        <td class="font-bold">{{ $assignment->title }}<span class="block text-xs font-normal text-slate-500">{{ $assignment->is_required ? 'Wajib' : 'Opsional' }} · {{ $assignment->allow_text ? 'teks' : '' }}{{ $assignment->allow_text && $assignment->allow_file ? ' & ' : '' }}{{ $assignment->allow_file ? 'berkas' : '' }}@if ($assignment->rubric) · rubrik {{ count($assignment->rubric) }} kriteria @endif</span></td>
                        <td class="text-xs">{{ $assignment->due_at?->timezone(display_tz())->translatedFormat('d M Y H:i') ?? 'Tanpa tenggat' }}@if ($assignment->due_at && $assignment->allow_late)<span class="block text-slate-500">terlambat diizinkan</span>@endif</td>
                        <td>{{ fmt_score($assignment->max_score) }}@if ($assignment->passing_score !== null)<span class="block text-xs text-slate-500">min. {{ fmt_score($assignment->passing_score) }}</span>@endif</td>
                        <td class="font-mono text-xs">{{ $assignment->submissions_count }} terkumpul @if ($assignment->pending_count > 0)<span class="block font-bold text-amber-700">{{ $assignment->pending_count }} menunggu nilai</span>@endif</td>
                        <td class="text-right text-xs whitespace-nowrap">
                            <a href="{{ route('assignments.submissions', [$class, $assignment]) }}" class="font-bold text-link hover:underline">Pengumpulan</a>
                            @if ($canEditAssignments)
                                · <a href="{{ route('assignments.edit', [$class, $assignment]) }}" class="font-bold text-link hover:underline">Ubah</a>
                                <form method="POST" action="{{ route('assignments.destroy', [$class, $assignment]) }}" class="inline" data-confirm="Hapus tugas ini?">@csrf @method('DELETE')<button class="btn-mini-danger ml-1">Hapus</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">Belum ada tugas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
