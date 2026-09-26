<x-layouts.app :title="'Kelompok '.$class->batch_name" :workspace="$workspace">
    <x-slot:back><a href="{{ $workspace === 'admin' ? route('admin.programs.show', $class->program) : route('trainer.classes') }}" class="hero-back">&larr; {{ $workspace === 'admin' ? $class->program->name : 'Kelas Saya' }}</a></x-slot:back>
    <x-slot:heading>{{ $class->program->name }} — {{ $class->batch_name }}</x-slot:heading>
    <x-slot:meta><span class="badge bg-slate-100 text-slate-700">{{ \App\Modules\Learning\Models\CourseClass::STATUSES[$class->status] ?? $class->status }}</span></x-slot:meta>
    <x-slot:subtitle>Bagi peserta ke kelompok belajar (mis. untuk tugas kelompok, mentoring, atau sesi paralel). {{ $enrollments->count() }} peserta aktif.</x-slot:subtitle>
    @include('classes._header')
    <x-form-error field="name" />
    <x-form-error field="assignments" />

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-4 xl:col-span-2">
            <div class="card overflow-x-auto">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-5 py-4">
                    <h2 class="card-title">Anggota per Kelompok</h2>
                    @if ($canEdit && $groups->isNotEmpty() && $enrollments->isNotEmpty())<button form="assign-form" class="btn-primary">Simpan Pembagian</button>@endif
                </div>
                <form id="assign-form" method="POST" action="{{ route('classes.groups.assign', $class) }}">@csrf
                    <table class="data-table">
                        <thead><tr><th scope="col">Peserta</th><th scope="col">Status</th><th scope="col">Kelompok</th></tr></thead>
                        <tbody>
                            @forelse ($enrollments as $enrollment)
                                <tr>
                                    <td class="font-bold">{{ $enrollment->user->name }}</td>
                                    <td class="text-xs">{{ $enrollment->statusLabel() }}</td>
                                    <td>
                                        @if ($canEdit && $groups->isNotEmpty())
                                            <label for="g-{{ $enrollment->id }}" class="sr-only">Kelompok {{ $enrollment->user->name }}</label>
                                            <select id="g-{{ $enrollment->id }}" name="assignments[{{ $enrollment->id }}]" class="form-input min-h-0 py-1 text-xs">
                                                <option value="">— Tanpa kelompok —</option>
                                                @foreach ($groups as $group)<option value="{{ $group->id }}" @selected($enrollment->group_id === $group->id)>{{ $group->name }}</option>@endforeach
                                            </select>
                                        @else
                                            <span class="text-xs">{{ $groups->firstWhere('id', $enrollment->group_id)?->name ?? '—' }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-8 text-center text-slate-500">Belum ada peserta aktif.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </form>
            </div>
        </div>
        <aside class="space-y-4">
            <section class="card p-5" aria-labelledby="groups-heading">
                <h2 id="groups-heading" class="card-title">Daftar Kelompok</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($groups as $group)
                        <li class="flex items-start justify-between gap-2 rounded-lg border border-slate-200 p-3">
                            <span><span class="font-bold text-slate-800">{{ $group->name }}</span><span class="block text-xs text-slate-500">{{ $group->enrollments_count }} anggota @if ($group->mentor) · mentor {{ $group->mentor->name }}@endif</span>@if ($group->description)<span class="block text-xs text-slate-500">{{ $group->description }}</span>@endif</span>
                            @if ($canEdit)<form method="POST" action="{{ route('classes.groups.destroy', [$class, $group]) }}" data-confirm="Hapus kelompok {{ $group->name }}? Anggota kembali tanpa kelompok.">@csrf @method('DELETE')<button class="btn-mini-danger">Hapus</button></form>@endif
                        </li>
                    @empty
                        <li class="text-slate-500">Belum ada kelompok.</li>
                    @endforelse
                </ul>
            </section>
            @if ($canEdit)
                <section class="card p-5" aria-labelledby="new-group">
                    <h2 id="new-group" class="card-title">Kelompok Baru</h2>
                    <form method="POST" action="{{ route('classes.groups.store', $class) }}" class="mt-3 space-y-3">@csrf
                        <div><label for="group-name" class="form-label">Nama kelompok</label><input id="group-name" name="name" required maxlength="80" class="form-input" placeholder="Kelompok A" value="{{ old('name') }}"></div>
                        <div><label for="group-desc" class="form-label">Keterangan (opsional)</label><input id="group-desc" name="description" maxlength="300" class="form-input" value="{{ old('description') }}"></div>
                        <div><label for="group-mentor" class="form-label">Mentor (opsional)</label>
                            <select id="group-mentor" name="mentor_id" class="form-input"><option value="">— Tidak ada —</option>@foreach ($mentors as $m)<option value="{{ $m['id'] }}">{{ $m['name'] }}</option>@endforeach</select></div>
                        <button class="btn-primary w-full">Buat Kelompok</button>
                    </form>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.app>
