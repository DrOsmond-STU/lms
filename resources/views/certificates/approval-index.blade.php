<x-layouts.app title="Approval Sertifikat" workspace="admin">
    <x-slot:heading>Approval Sertifikat</x-slot:heading>
    <x-slot:subtitle>Peserta yang memenuhi syarat kelulusan. Trainer pengampu tidak dapat menyetujui kelasnya sendiri.</x-slot:subtitle>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th scope="col">Peserta</th><th scope="col">Program / Kelas</th><th scope="col">Skor Akhir</th><th scope="col">Selesai</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
            <tbody>
                @forelse ($queue as $enrollment)
                    <tr>
                        <td class="font-bold">{{ $enrollment->user->name }}<span class="block font-mono text-xs font-normal text-slate-500">{{ \App\Support\Privacy\Mask::email($enrollment->user->email) }}</span></td>
                        <td>{{ $enrollment->program->name }}<span class="block text-xs text-slate-500">{{ $enrollment->courseClass->batch_name }}</span></td>
                        <td>{{ $enrollment->final_score !== null ? fmt_score($enrollment->final_score) : '—' }}</td>
                        <td class="text-xs">{{ $enrollment->completed_at?->timezone(display_tz())->format('d M Y H:i') }}</td>
                        <td class="text-right"><a href="{{ route('admin.approvals.show', $enrollment) }}" class="font-bold text-link hover:underline">Tinjau</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">Antrean kosong.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $queue->links() }}</div>
</x-layouts.app>
