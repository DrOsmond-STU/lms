<x-layouts.app :title="'Presensi '.$session->title" :workspace="$workspace">
    <x-slot:back><a href="{{ route('classes.sessions', $class) }}" class="hero-back">&larr; Sesi {{ $class->batch_name }}</a></x-slot:back>
    <x-slot:heading>Presensi — {{ $session->title }}</x-slot:heading>
    <x-slot:subtitle>{{ $session->starts_at->timezone(display_tz())->translatedFormat('l, d M Y H:i') }}–{{ $session->ends_at->timezone(display_tz())->format('H:i') }} {{ tz_label() }} · {{ \App\Modules\Learning\Models\ClassSession::TYPES[$session->type] }} · {{ \App\Modules\Learning\Models\ClassSession::ATTENDANCE_MODES[$session->attendance_mode] }}@if ($session->checkin_code) · kode cek-in <span class="font-mono font-bold">{{ $session->checkin_code }}</span>@endif</x-slot:subtitle>
    <x-slot:aside><div class="num">{{ $records->whereIn('status', ['present', 'late'])->count() }}/{{ $enrollments->count() }}</div><div class="lbl">Hadir</div></x-slot:aside>

    <form method="POST" action="{{ route('classes.attendance.store', [$class, $session]) }}" class="card overflow-x-auto" novalidate>@csrf
        <table class="data-table">
            <thead><tr><th scope="col">Peserta</th><th scope="col">Status enrollment</th><th scope="col">Cek-in</th><th scope="col">Presensi</th><th scope="col">Catatan</th></tr></thead>
            <tbody>
                @forelse ($enrollments as $enrollment)
                    @php($record = $records->get($enrollment->id))
                    <tr>
                        <td class="font-bold">{{ $enrollment->user->name }}<span class="block font-mono text-xs font-normal text-slate-500">{{ \App\Support\Privacy\Mask::email($enrollment->user->email) }}</span></td>
                        <td class="text-xs">{{ $enrollment->statusLabel() }}</td>
                        <td class="text-xs">@if ($record?->checked_in_at){{ $record->checked_in_at->timezone(display_tz())->format('H:i') }} ({{ $record->method === 'self' ? 'mandiri' : 'trainer' }})@else — @endif</td>
                        <td>
                            @if ($canRecord)
                                <select name="status[{{ $enrollment->id }}]" class="form-select min-h-0 py-1 text-xs" aria-label="Presensi {{ $enrollment->user->name }}">
                                    <option value="">— belum dicatat —</option>
                                    @foreach (\App\Modules\Learning\Models\AttendanceRecord::STATUSES as $value => $label)
                                        <option value="{{ $value }}" @selected($record?->status === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            @else
                                {{ $record?->statusLabel() ?? '—' }}
                            @endif
                        </td>
                        <td>@if ($canRecord)<input name="note[{{ $enrollment->id }}]" value="{{ $record?->note }}" maxlength="300" class="form-input min-h-0 py-1 text-xs" aria-label="Catatan {{ $enrollment->user->name }}">@else{{ $record?->note }}@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">Belum ada peserta.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($canRecord && $enrollments->isNotEmpty())
            <div class="border-t border-slate-200 px-5 py-4"><button class="btn-primary w-auto">Simpan Presensi</button></div>
        @endif
    </form>
</x-layouts.app>
