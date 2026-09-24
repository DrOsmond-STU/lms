<x-layouts.app title="Laporan Organisasi" workspace="organization">
    <h1 class="text-xl font-extrabold text-slate-800">Progres Pelatihan Anggota</h1>
    <p class="mt-0.5 mb-6 text-sm text-slate-600">Enrollment yang dilakukan saat peserta tercatat sebagai anggota organisasi Anda.</p>
    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th scope="col">Peserta</th><th scope="col">Program / Kelas</th><th scope="col">Status</th><th scope="col">Progres</th><th scope="col">Skor</th></tr></thead>
            <tbody>
                @forelse ($enrollments as $enrollment)
                    <tr>
                        <td class="font-bold">{{ $enrollment->user->name }}</td>
                        <td>{{ $enrollment->program->name }}<span class="block text-xs text-slate-500">{{ $enrollment->courseClass->batch_name }}</span></td>
                        <td><span class="badge bg-slate-100 text-slate-700">{{ $enrollment->statusLabel() }}</span></td>
                        <td>{{ $enrollment->progress_percent }}%</td>
                        <td>{{ $enrollment->final_score !== null ? rtrim(rtrim($enrollment->final_score, '0'), '.') : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">Belum ada enrollment anggota.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $enrollments->links() }}</div>
</x-layouts.app>
