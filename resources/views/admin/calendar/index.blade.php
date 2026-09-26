<x-layouts.app title="Kalender Akademik" workspace="admin">
    <x-slot:heading>Kalender Akademik</x-slot:heading>
    <x-slot:subtitle>Libur, periode ujian, jadwal pendaftaran, dan acara. Tampil di menu Jadwal peserta dan trainer sesuai lingkupnya.</x-slot:subtitle>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card overflow-x-auto xl:col-span-2">
            <table class="data-table">
                <thead><tr><th scope="col">Tanggal</th><th scope="col">Agenda</th><th scope="col">Jenis</th><th scope="col">Lingkup</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
                <tbody>
                    @forelse ($events as $event)
                        <tr>
                            <td class="text-xs whitespace-nowrap">{{ $event->starts_on->translatedFormat('d M Y') }}@if ($event->ends_on->ne($event->starts_on)) – {{ $event->ends_on->translatedFormat('d M Y') }}@endif</td>
                            <td class="font-bold">{{ $event->title }}@if ($event->description)<span class="block text-xs font-normal text-slate-500">{{ $event->description }}</span>@endif</td>
                            <td class="text-xs">{{ $event->kindLabel() }}</td>
                            <td class="text-xs">{{ \App\Modules\Learning\Models\AcademicEvent::SCOPES[$event->scope] }}@if ($event->organization_id || $event->course_class_id)<span class="block text-slate-500">{{ $names[$event->organization_id ?? $event->course_class_id] ?? '' }}</span>@endif</td>
                            <td class="text-right text-xs whitespace-nowrap">
                                <details class="inline text-left"><summary class="inline cursor-pointer font-bold text-link hover:underline">Ubah</summary>
                                    <div class="mt-2 w-80 rounded-lg border border-slate-200 bg-surface p-3 shadow">@include('admin.calendar._form', ['action' => route('admin.calendar.update', $event), 'method' => 'PUT', 'event' => $event])</div>
                                </details>
                                <form method="POST" action="{{ route('admin.calendar.destroy', $event) }}" class="inline" data-confirm="Hapus agenda ini?">@csrf @method('DELETE')<button class="btn-mini-danger ml-1">Hapus</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-slate-500">Belum ada agenda.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <aside>
            <section class="card p-5" aria-labelledby="new-event">
                <h2 id="new-event" class="font-bold text-slate-800">Tambah Agenda</h2>
                <div class="mt-3">@include('admin.calendar._form', ['action' => route('admin.calendar.store'), 'method' => 'POST', 'event' => new \App\Modules\Learning\Models\AcademicEvent])</div>
            </section>
        </aside>
    </div>
</x-layouts.app>
