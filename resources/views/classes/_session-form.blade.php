{{-- Formulir sesi kelas. $action, $method, $session --}}
@php($sid = $session->id ?? 'new')
<form method="POST" action="{{ $action }}" class="space-y-2 text-sm" novalidate>
    @csrf
    @if ($method !== 'POST') @method($method) @endif
    <div><label for="st-{{ $sid }}" class="form-label">Judul</label><input id="st-{{ $sid }}" name="title" value="{{ $session->title }}" required maxlength="200" class="form-input py-1.5"></div>
    <div><label for="sd-{{ $sid }}" class="form-label">Keterangan (opsional)</label><textarea id="sd-{{ $sid }}" name="description" rows="2" maxlength="2000" class="form-input py-1.5">{{ $session->description }}</textarea></div>
    <div><label for="sty-{{ $sid }}" class="form-label">Jenis</label>
        <select id="sty-{{ $sid }}" name="type" class="form-select py-1.5">@foreach (\App\Modules\Learning\Models\ClassSession::TYPES as $v => $l)<option value="{{ $v }}" @selected(($session->type ?? 'online') === $v)>{{ $l }}</option>@endforeach</select></div>
    <div class="grid grid-cols-2 gap-2">
        <div><label for="ss-{{ $sid }}" class="form-label">Mulai</label><input id="ss-{{ $sid }}" name="starts_at" type="datetime-local" value="{{ $session->starts_at?->timezone(display_tz())->format('Y-m-d\TH:i') }}" required class="form-input py-1.5"></div>
        <div><label for="se-{{ $sid }}" class="form-label">Selesai</label><input id="se-{{ $sid }}" name="ends_at" type="datetime-local" value="{{ $session->ends_at?->timezone(display_tz())->format('Y-m-d\TH:i') }}" required class="form-input py-1.5"></div>
    </div>
    <div><label for="sm-{{ $sid }}" class="form-label">Tautan meeting (live class, https)</label><input id="sm-{{ $sid }}" name="meeting_url" type="url" value="{{ $session->meeting_url }}" maxlength="500" class="form-input py-1.5" placeholder="https://meet.google.com/..."></div>
    <div><label for="sl-{{ $sid }}" class="form-label">Lokasi (tatap muka)</label><input id="sl-{{ $sid }}" name="location" value="{{ $session->location }}" maxlength="200" class="form-input py-1.5"></div>
    <div><label for="sa-{{ $sid }}" class="form-label">Presensi</label>
        <select id="sa-{{ $sid }}" name="attendance_mode" class="form-select py-1.5">@foreach (\App\Modules\Learning\Models\ClassSession::ATTENDANCE_MODES as $v => $l)<option value="{{ $v }}" @selected(($session->attendance_mode ?? 'self') === $v)>{{ $l }}</option>@endforeach</select></div>
    <div class="grid grid-cols-3 gap-2">
        <div><label for="sc-{{ $sid }}" class="form-label">Kode cek-in</label><input id="sc-{{ $sid }}" name="checkin_code" value="{{ $session->checkin_code }}" maxlength="8" class="form-input py-1.5 font-mono uppercase" placeholder="opsional"></div>
        <div><label for="so-{{ $sid }}" class="form-label">Buka (mnt sblm)</label><input id="so-{{ $sid }}" name="checkin_opens_before" type="number" min="0" max="1440" value="{{ $session->checkin_opens_before ?? 15 }}" class="form-input py-1.5"></div>
        <div><label for="scl-{{ $sid }}" class="form-label">Tutup (mnt stlh)</label><input id="scl-{{ $sid }}" name="checkin_closes_after" type="number" min="0" max="1440" value="{{ $session->checkin_closes_after ?? 30 }}" class="form-input py-1.5"></div>
    </div>
    @foreach (['title', 'starts_at', 'ends_at', 'meeting_url', 'type', 'checkin_code'] as $field)<x-form-error :field="$field" />@endforeach
    <button class="btn-primary w-full">Simpan Sesi</button>
</form>
