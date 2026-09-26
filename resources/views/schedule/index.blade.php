<x-layouts.app title="Jadwal" :workspace="$workspace">
    <x-slot:heading>Jadwal {{ $month->translatedFormat('F Y') }}</x-slot:heading>
    <x-slot:subtitle>Sesi kelas, live class, tenggat tugas, dan kalender akademik{{ $workspace === 'trainer' ? ' untuk kelas yang Anda ampu' : ' dari pelatihan yang Anda ikuti' }}.</x-slot:subtitle>
    <x-slot:actions>
        <a href="{{ route($workspace === 'trainer' ? 'schedule.trainer' : 'schedule.participant', ['bulan' => $month->copy()->subMonth()->format('Y-m')]) }}" class="btn-secondary">&larr; {{ $month->copy()->subMonth()->translatedFormat('M') }}</a>
        <a href="{{ route($workspace === 'trainer' ? 'schedule.trainer' : 'schedule.participant') }}" class="btn-secondary">Bulan ini</a>
        <a href="{{ route($workspace === 'trainer' ? 'schedule.trainer' : 'schedule.participant', ['bulan' => $month->copy()->addMonth()->format('Y-m')]) }}" class="btn-secondary">{{ $month->copy()->addMonth()->translatedFormat('M') }} &rarr;</a>
    </x-slot:actions>
    <x-form-error field="checkin" />

    @php($grouped = $items->groupBy(fn ($i) => $i['at']->timezone(display_tz())->toDateString()))
    @forelse ($grouped as $date => $dayItems)
        @php($day = \Illuminate\Support\Carbon::parse($date, display_tz()))
        <section class="card mb-4 p-5" aria-labelledby="d-{{ $date }}">
            <h2 id="d-{{ $date }}" @class(['font-bold', 'text-link' => $day->isToday(), 'text-slate-800' => ! $day->isToday()])>{{ $day->translatedFormat('l, d F Y') }}{{ $day->isToday() ? ' · hari ini' : '' }}</h2>
            <ul class="mt-3 divide-y divide-slate-100 text-sm">
                @foreach ($dayItems as $item)
                    <li class="flex flex-wrap items-start justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <span @class(['badge mr-2', 'bg-brand-50 text-link' => $item['kind'] === 'session', 'bg-amber-50 text-amber-800' => $item['kind'] === 'deadline', 'bg-slate-100 text-slate-700' => $item['kind'] === 'event'])>{{ ['session' => 'Sesi', 'deadline' => 'Tenggat', 'event' => 'Kalender'][$item['kind']] }}</span>
                            <span class="font-bold text-slate-800">{{ $item['title'] }}</span>
                            <span class="block text-xs text-slate-500">
                                @if ($item['kind'] === 'session')
                                    {{ $item['session']->starts_at->timezone(display_tz())->format('H:i') }}–{{ $item['session']->ends_at->timezone(display_tz())->format('H:i') }} {{ tz_label() }} · {{ \App\Modules\Learning\Models\ClassSession::TYPES[$item['session']->type] }}@if ($item['session']->location) · {{ $item['session']->location }}@endif
                                @elseif ($item['kind'] === 'deadline')
                                    pukul {{ $item['at']->timezone(display_tz())->format('H:i') }} {{ tz_label() }}
                                @endif
                                @if ($item['class']) · {{ $item['class'] }}@endif
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            @if ($item['kind'] === 'session')
                                @php($session = $item['session'])
                                @php($enrollment = $enrollments->get($session->course_class_id))
                                @php($record = $attendance->get($session->id))
                                @if ($session->joinOpen())<a href="{{ $session->meeting_url }}" target="_blank" rel="noopener noreferrer" class="btn-primary min-h-0 w-auto px-3 py-1.5">Gabung Live Class</a>@endif
                                @if ($record)<span class="badge bg-emerald-50 text-emerald-700">{{ $record->statusLabel() }}</span>
                                @elseif ($enrollment && $session->checkinOpen())
                                    <form method="POST" action="{{ route('schedule.checkin', [$enrollment, $session]) }}" class="flex items-center gap-1">@csrf
                                        @if ($session->checkin_code)<input name="code" maxlength="8" placeholder="Kode" class="form-input min-h-0 w-24 py-1 font-mono text-xs uppercase" aria-label="Kode cek-in">@endif
                                        <button class="btn-secondary min-h-0 px-3 py-1.5">Cek-in Hadir</button>
                                    </form>
                                @endif
                                @if ($enrollment)<a href="{{ route('learning.classroom', $enrollment) }}" class="font-bold text-link hover:underline">Ruang kelas</a>@elseif ($workspace === 'trainer')<a href="{{ route('classes.sessions', $session->course_class_id) }}" class="font-bold text-link hover:underline">Kelola sesi</a>@endif
                            @elseif ($item['kind'] === 'deadline')
                                @php($enrollment = $enrollments->get($item['class_id']))
                                @if ($enrollment)<a href="{{ route('assignments.show', [$enrollment, $item['assignment_id']]) }}" class="font-bold text-link hover:underline">Buka tugas</a>@endif
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <div class="card p-10 text-center text-sm text-slate-500">Tidak ada jadwal pada bulan ini.</div>
    @endforelse
</x-layouts.app>
