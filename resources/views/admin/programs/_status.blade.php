@switch($status)
    @case('draft')<span class="badge bg-slate-100 text-slate-700">Draf</span>@break
    @case('in_review')<span class="badge bg-amber-50 text-amber-700">Menunggu Review</span>@break
    @case('published')<span class="badge bg-emerald-50 text-emerald-700">Terbit</span>@break
    @case('archived')<span class="badge bg-slate-100 text-slate-500">Diarsipkan</span>@break
    @default<span class="badge bg-slate-100 text-slate-600">{{ $status }}</span>
@endswitch
