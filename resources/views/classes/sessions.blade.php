<x-layouts.app :title="'Sesi '.$class->batch_name" :workspace="$workspace">
    <x-slot:back><a href="{{ $workspace === 'admin' ? route('admin.programs.show', $class->program) : route('trainer.classes') }}" class="hero-back">&larr; {{ $workspace === 'admin' ? $class->program->name : 'Kelas Saya' }}</a></x-slot:back>
    <x-slot:heading>{{ $class->program->name }} — {{ $class->batch_name }}</x-slot:heading>
    <x-slot:meta><span class="badge bg-slate-100 text-slate-700">{{ \App\Modules\Learning\Models\CourseClass::STATUSES[$class->status] ?? $class->status }}</span></x-slot:meta>
    <x-slot:subtitle>Jadwal pertemuan tatap muka dan live class beserta presensi. {{ $activeCount }} peserta aktif.</x-slot:subtitle>
    @include('classes._header')
    <x-form-error field="session" />

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-4 xl:col-span-2">
            <div class="card overflow-x-auto">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-5 py-4">
                    <h2 class="card-title">Daftar Sesi</h2>
                    @can('attendance.view_any')<a href="{{ route('classes.attendance.export', $class) }}" class="btn-secondary">Ekspor Presensi (CSV)</a>@endcan
                </div>
                <table class="data-table">
                    <thead><tr><th scope="col">Waktu</th><th scope="col">Sesi</th><th scope="col">Jenis</th><th scope="col">Presensi</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
                    <tbody>
                        @forelse ($sessions as $session)
                            <tr>
                                <td class="text-xs whitespace-nowrap">{{ $session->starts_at->timezone(display_tz())->translatedFormat('D, d M Y') }}<span class="block">{{ $session->starts_at->timezone(display_tz())->format('H:i') }}–{{ $session->ends_at->timezone(display_tz())->format('H:i') }} {{ tz_label() }}</span></td>
                                <td class="font-bold">{{ $session->title }}@if ($session->description)<span class="block text-xs font-normal text-slate-500">{{ \Illuminate\Support\Str::limit($session->description, 90) }}</span>@endif</td>
                                <td class="text-xs">{{ \App\Modules\Learning\Models\ClassSession::TYPES[$session->type] }}@if ($session->location)<span class="block text-slate-500">{{ $session->location }}</span>@endif @if ($session->meeting_url)<a href="{{ $session->meeting_url }}" target="_blank" rel="noopener noreferrer" class="block text-link hover:underline">Tautan meeting</a>@endif</td>
                                <td class="text-xs">{{ \App\Modules\Learning\Models\ClassSession::ATTENDANCE_MODES[$session->attendance_mode] }}@if ($session->attendance_mode !== 'none')<span class="block font-mono">{{ $session->present_count }}/{{ $activeCount }} hadir</span>@endif @if ($session->checkin_code)<span class="block">Kode: <span class="font-mono font-bold">{{ $session->checkin_code }}</span></span>@endif</td>
                                <td class="text-right text-xs whitespace-nowrap">
                                    @can('attendance.view_any')<a href="{{ route('classes.attendance', [$class, $session]) }}" class="font-bold text-link hover:underline">Presensi</a>@endcan
                                    @if ($canEditSessions)
                                        · <details class="inline text-left"><summary class="inline cursor-pointer font-bold text-link hover:underline">Ubah</summary>
                                            <div class="mt-2 w-80 rounded-lg border border-slate-200 bg-surface p-3 text-left shadow">
                                                @include('classes._session-form', ['action' => route('classes.sessions.update', [$class, $session]), 'method' => 'PUT', 'session' => $session])
                                            </div>
                                        </details>
                                        <form method="POST" action="{{ route('classes.sessions.destroy', [$class, $session]) }}" class="inline" data-confirm="Hapus sesi ini?">@csrf @method('DELETE')<button class="btn-mini-danger ml-1">Hapus</button></form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-slate-500">Belum ada sesi. Jadwalkan pertemuan atau live class di panel kanan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <aside class="space-y-6">
            @if ($canEditSessions)
                <section class="card p-5" aria-labelledby="new-session">
                    <h2 id="new-session" class="font-bold text-slate-800">Jadwalkan Sesi</h2>
                    <div class="mt-3">@include('classes._session-form', ['action' => route('classes.sessions.store', $class), 'method' => 'POST', 'session' => new \App\Modules\Learning\Models\ClassSession])</div>
                </section>
            @endif
            <section class="card p-5 text-sm text-slate-600">
                <h2 class="font-bold text-slate-800">Panduan</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li>Live class memakai tautan Zoom/Google Meet/Teams milik Anda; tautan dibagikan ke peserta 30 menit sebelum mulai.</li>
                    <li>Cek-in mandiri dibuka sejak N menit sebelum mulai; peserta yang cek-in lewat 10 menit dicatat terlambat.</li>
                    <li>Kode cek-in (opsional) disebutkan di ruang kelas/meeting agar hanya yang hadir dapat cek-in.</li>
                    <li>Peserta menerima notifikasi saat sesi dijadwalkan atau berubah.</li>
                </ul>
            </section>
        </aside>
    </div>
</x-layouts.app>
