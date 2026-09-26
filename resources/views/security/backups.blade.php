<x-layouts.app title="Backup" workspace="admin">
    <x-slot:heading>Backup</x-slot:heading>
    <x-slot:subtitle>Backup basis data otomatis {{ $enabled ? 'aktif setiap hari pukul 01.30' : 'NONAKTIF (Pengaturan → Keamanan)' }}; berkas disimpan {{ $keepDays }} hari{{ $encrypted ? ', dienkripsi (libsodium)' : ', TANPA enkripsi — isi BACKUP_ENCRYPTION_KEY di .env' }}. Simpan salinan di luar server secara berkala.</x-slot:subtitle>
    @can('backup.run')
        <x-slot:actions>
            <form method="POST" action="{{ route('admin.backups.run') }}">@csrf<input type="hidden" name="kind" value="db"><button class="btn-primary">Backup basis data sekarang</button></form>
            <form method="POST" action="{{ route('admin.backups.run') }}" data-confirm="Arsip media bisa berukuran besar. Lanjutkan?">@csrf<input type="hidden" name="kind" value="media"><button class="btn-secondary">Backup media</button></form>
        </x-slot:actions>
    @endcan
    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th scope="col">Waktu</th><th scope="col">Jenis</th><th scope="col">Berkas</th><th scope="col">Ukuran</th><th scope="col">Metode</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
            <tbody>
                @forelse ($backups as $backup)
                    <tr>
                        <td class="text-xs whitespace-nowrap">{{ $backup->started_at->timezone(display_tz())->format('d M Y H:i') }}</td>
                        <td class="text-xs">{{ $backup->kind === 'db' ? 'Basis data' : 'Media' }}</td>
                        <td class="font-mono text-xs">{{ $backup->filename }}@if ($backup->checksum)<span class="block text-slate-500">sha256 {{ substr($backup->checksum, 0, 16) }}…</span>@endif</td>
                        <td class="font-mono text-xs">{{ $backup->humanSize() }}</td>
                        <td class="text-xs">{{ $backup->method ?? '—' }}{{ $backup->encrypted ? ' · terenkripsi' : '' }}</td>
                        <td><span @class(['badge', 'bg-emerald-50 text-emerald-700' => $backup->status === 'ok', 'bg-rose-50 text-rose-700' => $backup->status === 'failed', 'bg-amber-50 text-amber-800' => $backup->status === 'running'])>{{ $backup->status }}</span>@if ($backup->error)<span class="block max-w-xs text-xs text-rose-700">{{ $backup->error }}</span>@endif</td>
                        <td class="text-right">@can('backup.download')@if ($backup->status === 'ok')<a href="{{ route('admin.backups.download', $backup) }}" class="btn-mini">Unduh</a>@endif @endcan</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-slate-500">Belum ada backup.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $backups->links() }}</div>
    <p class="mt-4 text-xs text-slate-500">Pemulihan: berkas <span class="font-mono">.dump</span> dipulihkan dengan <span class="font-mono">pg_restore</span>; berkas <span class="font-mono">.enc</span> didekripsi lebih dulu dengan kunci BACKUP_ENCRYPTION_KEY (lihat docs/11). Backup fallback (JSONL dalam ZIP) dipakai bila pg_dump tidak tersedia di hosting.</p>
</x-layouts.app>
