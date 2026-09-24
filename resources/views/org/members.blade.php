<x-layouts.app title="Anggota" workspace="organization">
    <x-slot:heading>Anggota Organisasi</x-slot:heading>
    <x-slot:subtitle>Setujui peserta yang mendaftar dengan kode organisasi Anda hanya bila Anda yakin mereka anggota sah.</x-slot:subtitle>

    <nav class="mb-4 flex flex-wrap gap-2 text-sm" aria-label="Filter status">
        @foreach (['' => 'Semua', 'pending' => 'Menunggu', 'active' => 'Aktif', 'rejected' => 'Ditolak', 'removed' => 'Dikeluarkan'] as $value => $label)
            <a href="{{ route('org.members', $value === '' ? [] : ['status' => $value]) }}" @class(['badge', 'bg-brand-800 text-white' => ($status ?? '') === $value, 'bg-slate-100 text-slate-700' => ($status ?? '') !== $value])>{{ $label }}</a>
        @endforeach
    </nav>
    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th scope="col">Nama</th><th scope="col">Organisasi</th><th scope="col">Status</th><th scope="col">Daftar</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
            <tbody>
                @forelse ($members as $member)
                    <tr>
                        <td class="font-bold">{{ $member->user->name }}<span class="block font-mono text-xs font-normal text-slate-500">{{ $member->user->email }}</span></td>
                        <td>{{ $member->organization->code }}</td>
                        <td><span class="badge bg-slate-100 text-slate-700">{{ ['pending' => 'Menunggu', 'active' => 'Aktif', 'rejected' => 'Ditolak', 'removed' => 'Dikeluarkan'][$member->status] }}</span></td>
                        <td class="text-xs">{{ $member->created_at?->timezone(display_tz())->format('d M Y') }}</td>
                        <td class="text-right whitespace-nowrap">
                            @if ($member->status === 'pending')
                                <form method="POST" action="{{ route('org.members.decide', $member) }}" class="inline">@csrf<input type="hidden" name="decision" value="approve"><button class="btn-primary w-auto px-3 py-1.5 text-xs">Setujui</button></form>
                                <form method="POST" action="{{ route('org.members.decide', $member) }}" class="inline">@csrf<input type="hidden" name="decision" value="reject"><button class="btn-secondary px-3 py-1.5 text-xs">Tolak</button></form>
                            @elseif ($member->status === 'active')
                                <form method="POST" action="{{ route('org.members.decide', $member) }}" class="inline" data-confirm="Keluarkan {{ $member->user->name }} dari organisasi?">@csrf<input type="hidden" name="decision" value="remove"><button class="btn-mini-danger">Keluarkan</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">Tidak ada anggota.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $members->links() }}</div>
</x-layouts.app>
