<x-layouts.app :title="'Peserta '.$class->batch_name" :workspace="$workspace">
    @include('classes._header')
    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th scope="col">Peserta</th><th scope="col">Status</th><th scope="col">Progres</th><th scope="col">Skor Akhir</th><th scope="col">Terdaftar</th></tr></thead>
            <tbody>
                @forelse ($enrollments as $enrollment)
                    <tr>
                        <td class="font-bold">{{ $enrollment->user->name }}<span class="block font-mono text-xs font-normal text-slate-500">{{ \App\Support\Privacy\Mask::email($enrollment->user->email) }}</span></td>
                        <td><span class="badge bg-slate-100 text-slate-700">{{ $enrollment->statusLabel() }}</span></td>
                        <td>
                            <div class="flex items-center gap-2"><div class="h-2 w-24 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-accent-500 progress-{{ (int) (round($enrollment->progress_percent / 5) * 5) }}"></div></div><span class="text-xs">{{ $enrollment->progress_percent }}%</span></div>
                        </td>
                        <td>{{ $enrollment->final_score !== null ? rtrim(rtrim($enrollment->final_score, '0'), '.') : '—' }}</td>
                        <td class="text-xs">{{ $enrollment->enrolled_at?->timezone('Asia/Jakarta')->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">Belum ada peserta.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $enrollments->links() }}</div>
</x-layouts.app>
