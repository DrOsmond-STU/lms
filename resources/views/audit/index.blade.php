<x-layouts.app title="Jejak Audit" :workspace="$workspace">
    <x-slot:heading>Jejak Audit</x-slot:heading>
    <x-slot:subtitle>Append-only dan berantai hash — tidak dapat diubah siapa pun. Nilai sensitif disamarkan.</x-slot:subtitle>
    <x-slot:actions>
        @can('audit_log.export')<a href="{{ route($workspace === 'admin' ? 'admin.audit.export' : 'org.audit.export', request()->query()) }}" class="btn-secondary">Ekspor CSV</a>@endcan
    </x-slot:actions>

    <form method="GET" class="card mb-5 flex flex-wrap items-end gap-3 p-4" role="search">
        <div><label for="aktor" class="form-label">Email aktor</label><input id="aktor" name="aktor" value="{{ $filters['aktor'] ?? '' }}" maxlength="254" class="form-input"></div>
        <div><label for="aksi" class="form-label">Aksi (awalan)</label><input id="aksi" name="aksi" value="{{ $filters['aksi'] ?? '' }}" maxlength="64" placeholder="certificate." class="form-input"></div>
        <div><label for="objek" class="form-label">Jenis objek</label><input id="objek" name="objek" value="{{ $filters['objek'] ?? '' }}" maxlength="64" placeholder="user" class="form-input"></div>
        <div><label for="dari" class="form-label">Dari</label><input id="dari" name="dari" type="date" value="{{ $filters['dari'] ?? '' }}" class="form-input"></div>
        <div><label for="sampai" class="form-label">Sampai</label><input id="sampai" name="sampai" type="date" value="{{ $filters['sampai'] ?? '' }}" class="form-input"></div>
        <button class="btn-secondary">Terapkan</button>
    </form>
    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th scope="col">Waktu ({{ tz_label() }})</th><th scope="col">Aktor</th><th scope="col">Aksi</th><th scope="col">Objek</th><th scope="col">Detail</th></tr></thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td class="text-xs whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($entry->occurred_at)->timezone(display_tz())->format('d M Y H:i:s') }}</td>
                        <td class="text-xs">{{ $entry->actor_id ? ($actors[$entry->actor_id] ?? 'pengguna') : 'sistem' }}<span class="block text-slate-500">{{ $entry->actor_role }}</span></td>
                        <td class="font-mono text-xs">{{ $entry->action }}</td>
                        <td class="font-mono text-xs">{{ $entry->subject_type }}<span class="block text-slate-500">{{ $entry->subject_id ? \Illuminate\Support\Str::limit($entry->subject_id, 13, '…') : '' }}</span></td>
                        <td class="max-w-md text-xs break-all text-slate-600">@if ($entry->reason)<span class="font-bold">Alasan:</span> {{ $entry->reason }}<br>@endif{{ \Illuminate\Support\Str::limit((string) $entry->changes, 300) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">Tidak ada entri.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $entries->links() }}</div>
</x-layouts.app>
