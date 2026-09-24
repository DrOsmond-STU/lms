<x-layouts.app title="Template Sertifikat" workspace="admin">
    <x-slot:heading>Template Sertifikat</x-slot:heading>
    <x-slot:subtitle>Satu template aktif per kategori/program. Template yang sudah dipakai tidak dapat diubah — buat versi baru. Aktivasi memerlukan persetujuan admin kedua.</x-slot:subtitle>
    <x-slot:actions>
        @can('certificate_template.create')<a href="{{ route('admin.templates.create') }}" class="btn-primary w-auto">Template Baru</a>@endcan
    </x-slot:actions>

    <x-form-error field="template" />
    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th scope="col">Nama</th><th scope="col">Kategori / Program</th><th scope="col">Versi</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
            <tbody>
                @forelse ($templates as $template)
                    <tr>
                        <td class="font-bold">{{ $template->name }}</td>
                        <td>{{ \App\Modules\Catalog\Models\Program::CATEGORIES[$template->category] }}<span class="block text-xs text-slate-500">{{ $template->program_id ? ($programs[$template->program_id] ?? 'Program') : 'Semua program kategori ini' }}</span></td>
                        <td>v{{ $template->version }}</td>
                        <td>
                            @if ($template->is_active)<span class="badge bg-emerald-50 text-emerald-700">Aktif</span>@elseif (isset($pending[$template->id]))<span class="badge bg-amber-50 text-amber-700">Menunggu aktivasi</span>@else<span class="badge bg-slate-100 text-slate-600">Tidak aktif</span>@endif
                            @if ($template->isLocked())<span class="badge bg-slate-100 text-slate-600">Terkunci</span>@endif
                        </td>
                        <td class="text-right text-xs whitespace-nowrap">
                            <a href="{{ route('admin.templates.preview', $template) }}" target="_blank" rel="noopener" class="font-bold text-link hover:underline">Pratinjau</a>
                            · <a href="{{ route('admin.templates.create', ['dari' => $template->id]) }}" class="font-bold text-link hover:underline">Versi baru</a>
                            @if (! $template->isLocked() && ! $template->is_active)
                                · <a href="{{ route('admin.templates.edit', $template) }}" class="font-bold text-link hover:underline">Ubah</a>
                                @if (! isset($pending[$template->id]))
                                    @can('certificate_template.update')
                                        <form method="POST" action="{{ route('admin.templates.destroy', $template) }}" class="inline" data-confirm="Hapus template ini?">@csrf @method('DELETE')<button class="btn-mini-danger ml-1">Hapus</button></form>
                                    @endcan
                                @endif
                            @endif
                            @if (! $template->is_active && ! isset($pending[$template->id]))
                                @can('certificate_template.activate')
                                    <details class="mt-1 text-left"><summary class="cursor-pointer font-bold text-emerald-700">Ajukan aktivasi</summary>
                                        <form method="POST" action="{{ route('admin.templates.activate', $template) }}" class="mt-1 flex gap-1">@csrf
                                            <label for="act-{{ $template->id }}" class="sr-only">Alasan</label>
                                            <input id="act-{{ $template->id }}" name="reason" minlength="5" maxlength="500" required class="form-input py-1 text-xs" placeholder="Alasan">
                                            <button class="btn-secondary px-2 py-1 text-xs">Ajukan</button>
                                        </form>
                                    </details>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">Belum ada template.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-form-error field="reason" />
</x-layouts.app>
