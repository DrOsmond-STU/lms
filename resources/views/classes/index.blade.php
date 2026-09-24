<x-layouts.app :title="$workspace === 'admin' ? 'Kelas & Jadwal' : 'Kelas Saya'" :workspace="$workspace">
    <x-slot:heading>{{ $workspace === 'admin' ? 'Kelas & Jadwal' : 'Kelas Saya' }}</x-slot:heading>
    <x-slot:subtitle>{{ $workspace === 'admin' ? 'Semua kelas/batch. Kelas baru dibuat dari halaman program.' : 'Kelas yang Anda ampu: kelola materi, asesmen, dan penilaian.' }}</x-slot:subtitle>

    @if ($workspace === 'admin')
        <form method="GET" class="card mb-5 flex flex-wrap items-end gap-3 p-4" role="search">
            <div class="min-w-48 flex-1"><label for="q" class="form-label">Cari batch / program</label><input id="q" name="q" value="{{ $search }}" maxlength="100" class="form-input"></div>
            <div><label for="status" class="form-label">Status</label><select id="status" name="status" class="form-select"><option value="">Semua</option>@foreach (\App\Modules\Learning\Models\CourseClass::STATUSES as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select></div>
            <button class="btn-secondary">Terapkan</button>
        </form>
    @endif
    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th scope="col">Kelas</th><th scope="col">Periode</th><th scope="col">Peserta</th><th scope="col">Status</th></tr></thead>
            <tbody>
                @forelse ($classes as $class)
                    <tr>
                        <td><a href="{{ route('classes.manage', $class) }}" class="font-bold text-link hover:underline">{{ $class->batch_name }}</a><span class="block text-xs text-slate-500">{{ $class->program->name }}</span></td>
                        <td class="text-xs">{{ $class->starts_on->translatedFormat('d M Y') }} – {{ $class->ends_on->translatedFormat('d M Y') }}</td>
                        <td>{{ $class->enrolled_count }}/{{ $class->quota }}</td>
                        <td><span class="badge bg-slate-100 text-slate-700">{{ \App\Modules\Learning\Models\CourseClass::STATUSES[$class->status] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-slate-500">Belum ada kelas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $classes->links() }}</div>
</x-layouts.app>
