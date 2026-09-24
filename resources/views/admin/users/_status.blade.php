@switch($status)
    @case('active')<span class="badge bg-emerald-50 text-emerald-700">Aktif</span>@break
    @case('pending_verification')<span class="badge bg-amber-50 text-amber-700">Menunggu aktivasi</span>@break
    @case('deactivated')<span class="badge bg-slate-100 text-slate-600">Nonaktif</span>@break
    @case('suspended')<span class="badge bg-rose-50 text-rose-700">Ditangguhkan</span>@break
    @default<span class="badge bg-slate-100 text-slate-600">{{ $status }}</span>
@endswitch
