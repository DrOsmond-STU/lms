{{-- Form pengumuman. $action, $method, $item (null = baru), $scopes, $organizations, $classes, $class --}}
<form method="POST" action="{{ $action }}" class="space-y-3" novalidate>@csrf @if ($method !== 'POST')@method($method)@endif
    @php($prefix = ($item?->id ?? 'new').'-')
    <div><label for="{{ $prefix }}scope" class="form-label">Lingkup</label>
        <select id="{{ $prefix }}scope" name="scope" class="form-input">
            @foreach ($scopes as $value => $label)<option value="{{ $value }}" @selected(old('scope', $item?->scope ?? array_key_first($scopes)) === $value)>{{ $label }}</option>@endforeach
        </select><x-form-error field="scope" /></div>
    @if ($organizations->isNotEmpty())
        <div><label for="{{ $prefix }}org" class="form-label">Organisasi (lingkup organisasi)</label>
            <select id="{{ $prefix }}org" name="organization_id" class="form-input"><option value="">—</option>@foreach ($organizations as $org)<option value="{{ $org->id }}" @selected(old('organization_id', $item?->organization_id) === $org->id)>{{ $org->name }}</option>@endforeach</select><x-form-error field="organization_id" /></div>
    @endif
    @if ($classes->isNotEmpty() && $class === null)
        <div><label for="{{ $prefix }}class" class="form-label">Kelas (lingkup kelas)</label>
            <select id="{{ $prefix }}class" name="course_class_id" class="form-input"><option value="">—</option>@foreach ($classes as $c)<option value="{{ $c->id }}" @selected(old('course_class_id', $item?->course_class_id) === $c->id)>{{ $c->label }}</option>@endforeach</select><x-form-error field="course_class_id" /></div>
    @endif
    <div><label for="{{ $prefix }}title" class="form-label">Judul</label><input id="{{ $prefix }}title" name="title" required minlength="3" maxlength="200" class="form-input" value="{{ old('title', $item?->title) }}"><x-form-error field="title" /></div>
    <div><label for="{{ $prefix }}body" class="form-label">Isi (Markdown)</label><textarea id="{{ $prefix }}body" name="body" rows="5" required maxlength="20000" class="form-input">{{ old('body', $item?->body) }}</textarea><x-form-error field="body" /></div>
    <div class="grid gap-3 sm:grid-cols-2">
        <div><label for="{{ $prefix }}publish" class="form-label">Terbit pada ({{ tz_label() }})</label><input id="{{ $prefix }}publish" type="datetime-local" name="publish_at" class="form-input" value="{{ old('publish_at', $item?->publish_at?->timezone(display_tz())->format('Y-m-d\TH:i')) }}"><x-form-error field="publish_at" /></div>
        <div><label for="{{ $prefix }}expires" class="form-label">Berakhir (opsional)</label><input id="{{ $prefix }}expires" type="datetime-local" name="expires_at" class="form-input" value="{{ old('expires_at', $item?->expires_at?->timezone(display_tz())->format('Y-m-d\TH:i')) }}"><x-form-error field="expires_at" /></div>
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_pinned" value="1" @checked(old('is_pinned', $item?->is_pinned ?? false))> Sematkan di atas</label>
    @if ($item === null)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="notify" value="1" checked> Kirim notifikasi ke audiens</label>@endif
    <button class="btn-primary w-full">{{ $item === null ? 'Terbitkan Pengumuman' : 'Simpan Perubahan' }}</button>
</form>
