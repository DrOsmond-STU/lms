<x-layouts.app title="Laporan Organisasi" workspace="organization">
    <x-slot:heading>Progres Pelatihan Anggota</x-slot:heading>
    <x-slot:subtitle>Enrollment yang dilakukan saat peserta tercatat sebagai anggota organisasi Anda.</x-slot:subtitle>

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
                        <td>{{ $enrollment->final_score !== null ? fmt_score($enrollment->final_score) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">Belum ada enrollment anggota.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $enrollments->links() }}</div>
</x-layouts.app>
