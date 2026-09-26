@php($eid = $event->id ?? 'new')
<form method="POST" action="{{ $action }}" class="space-y-2 text-sm" novalidate>
    @csrf
    @if ($method !== 'POST') @method($method) @endif
    <div><label for="et-{{ $eid }}" class="form-label">Judul</label><input id="et-{{ $eid }}" name="title" value="{{ $event->title }}" required maxlength="200" class="form-input py-1.5"></div>
    <div><label for="ed-{{ $eid }}" class="form-label">Keterangan</label><input id="ed-{{ $eid }}" name="description" value="{{ $event->description }}" maxlength="1000" class="form-input py-1.5"></div>
    <div class="grid grid-cols-2 gap-2">
        <div><label for="ek-{{ $eid }}" class="form-label">Jenis</label><select id="ek-{{ $eid }}" name="kind" class="form-select py-1.5">@foreach (\App\Modules\Learning\Models\AcademicEvent::KINDS as $v => $l)<option value="{{ $v }}" @selected(($event->kind ?? 'event') === $v)>{{ $l }}</option>@endforeach</select></div>
        <div><label for="es-{{ $eid }}" class="form-label">Lingkup</label><select id="es-{{ $eid }}" name="scope" class="form-select py-1.5">@foreach (\App\Modules\Learning\Models\AcademicEvent::SCOPES as $v => $l)<option value="{{ $v }}" @selected(($event->scope ?? 'platform') === $v)>{{ $l }}</option>@endforeach</select></div>
    </div>
    <div><label for="eo-{{ $eid }}" class="form-label">Organisasi (bila lingkup organisasi)</label><select id="eo-{{ $eid }}" name="organization_id" class="form-select py-1.5"><option value="">—</option>@foreach ($organizations as $o)<option value="{{ $o->id }}" @selected($event->organization_id === $o->id)>{{ $o->name }}</option>@endforeach</select></div>
    <div><label for="ec-{{ $eid }}" class="form-label">Kelas (bila lingkup kelas)</label><select id="ec-{{ $eid }}" name="course_class_id" class="form-select py-1.5"><option value="">—</option>@foreach ($classes as $c)<option value="{{ $c->id }}" @selected($event->course_class_id === $c->id)>{{ $c->program_name }} · {{ $c->batch_name }}</option>@endforeach</select></div>
    <div class="grid grid-cols-2 gap-2">
        <div><label for="e1-{{ $eid }}" class="form-label">Mulai</label><input id="e1-{{ $eid }}" name="starts_on" type="date" value="{{ $event->starts_on?->format('Y-m-d') }}" required class="form-input py-1.5"></div>
        <div><label for="e2-{{ $eid }}" class="form-label">Selesai</label><input id="e2-{{ $eid }}" name="ends_on" type="date" value="{{ $event->ends_on?->format('Y-m-d') }}" required class="form-input py-1.5"></div>
    </div>
    @foreach (['title', 'starts_on', 'ends_on', 'organization_id', 'course_class_id'] as $field)<x-form-error :field="$field" />@endforeach
    <button class="btn-primary w-full">Simpan</button>
</form>
