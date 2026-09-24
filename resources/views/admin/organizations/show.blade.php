@php($typeLabels = ['institution' => 'Institusi pendidikan', 'corporate' => 'Korporat'])
<x-layouts.app :title="$organization->name" workspace="admin">
    <x-slot:back><a href="{{ route('admin.organizations.index') }}" class="hero-back">&larr; Organisasi</a></x-slot:back>
    <x-slot:heading>{{ $organization->name }}</x-slot:heading>
    <x-slot:meta>
        <span class="badge bg-brand-50 font-mono text-link">{{ $organization->code }}</span>
        <span class="badge bg-slate-100 text-slate-700">{{ $typeLabels[$organization->type] ?? $organization->type }}</span>
        @if ($organization->status === 'active')
        <span class="badge bg-emerald-50 text-emerald-700">Aktif</span>
        @else
        <span class="badge bg-slate-100 text-slate-600">Diarsipkan</span>
        @endif
    </x-slot:meta>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card p-6 lg:col-span-2" aria-labelledby="profile-heading">
            <h2 id="profile-heading" class="mb-4 font-bold text-slate-800">Profil</h2>
            @can('organization.update')
                <form method="POST" action="{{ route('admin.organizations.update', $organization) }}" class="space-y-4" novalidate>
                    @csrf
                    @method('PUT')
                    @include('admin.organizations._fields', ['editing' => true])
                    <button type="submit" class="btn-primary w-auto">Simpan Perubahan</button>
                </form>
            @else
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-slate-500">Kota</dt><dd class="font-bold">{{ $organization->city ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Akreditasi / Industri</dt><dd class="font-bold">{{ $organization->accreditation ?? $organization->industry ?? '—' }}</dd></div>
                </dl>
            @endcan
        </section>

        <div class="space-y-6">
            <section class="card p-6" aria-labelledby="members-heading">
                <h2 id="members-heading" class="font-bold text-slate-800">Anggota</h2>
                <p class="mt-2 text-sm text-slate-600"><span class="text-lg font-extrabold text-slate-800">{{ number_format((int) $members?->active, 0, ',', '.') }}</span> aktif · {{ number_format((int) $members?->pending, 0, ',', '.') }} menunggu persetujuan</p>
                <h3 class="mt-4 text-xs font-bold tracking-wide text-slate-500 uppercase">Admin Organisasi</h3>
                <ul class="mt-2 space-y-1 text-sm">
                    @forelse ($admins as $admin)
                        <li>
                            @can('user.view')
                                <a href="{{ route('admin.users.show', $admin) }}" class="font-bold text-link hover:underline">{{ $admin->name }}</a>
                            @else
                                {{ $admin->name }}
                            @endcan
                            @if ($admin->status !== 'active')<span class="text-xs text-slate-500">({{ $admin->status === 'pending_verification' ? 'undangan tertunda' : 'nonaktif' }})</span>@endif
                        </li>
                    @empty
                        <li class="text-slate-500">Belum ada.</li>
                    @endforelse
                </ul>
                @can('user.create')
                    <a href="{{ route('admin.users.create', ['peran' => 'org_admin', 'organisasi' => $organization->id]) }}" class="mt-3 inline-block text-sm font-bold text-link hover:underline">Undang Admin Organisasi</a>
                @endcan
            </section>

            <section class="card p-6" aria-labelledby="domains-heading">
                <h2 id="domains-heading" class="font-bold text-slate-800">Domain Email Terverifikasi</h2>
                <p class="mt-1 text-xs text-slate-500">Peserta yang mendaftar dengan email domain ini otomatis menjadi anggota aktif. Tambahkan hanya domain yang dikendalikan organisasi.</p>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($domains as $domain)
                        <li class="flex items-center justify-between gap-2">
                            <span class="font-mono">{{ $domain->domain }}</span>
                            @can('organization.update')
                                <form method="POST" action="{{ route('admin.organizations.domains.destroy', [$organization, $domain->id]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-mini-danger">Hapus</button>
                                </form>
                            @endcan
                        </li>
                    @empty
                        <li class="text-slate-500">Belum ada domain.</li>
                    @endforelse
                </ul>
                @can('organization.update')
                    <form method="POST" action="{{ route('admin.organizations.domains.store', $organization) }}" class="mt-4 flex gap-2" novalidate>
                        @csrf
                        <label for="domain" class="sr-only">Domain baru</label>
                        <input id="domain" name="domain" type="text" value="{{ old('domain') }}" placeholder="kampus.ac.id" maxlength="253" class="form-input">
                        <button type="submit" class="btn-secondary">Tambah</button>
                    </form>
                    <x-form-error field="domain" />
                @endcan
            </section>

            @can('organization.archive')
                <section class="card p-6" aria-labelledby="archive-heading">
                    <h2 id="archive-heading" class="font-bold text-slate-800">{{ $organization->status === 'active' ? 'Arsipkan Organisasi' : 'Aktifkan Kembali' }}</h2>
                    <p class="mt-1 text-xs text-slate-500">{{ $organization->status === 'active' ? 'Registrasi baru dengan kode ini ditolak; data & sertifikat tetap tersimpan.' : 'Organisasi dapat dipakai kembali untuk registrasi.' }}</p>
                    <form method="POST" action="{{ route('admin.organizations.status', $organization) }}" class="mt-3 space-y-3" novalidate>
                        @csrf
                        <div>
                            <label for="reason" class="form-label">Alasan</label>
                            <input id="reason" name="reason" type="text" required minlength="5" maxlength="500" class="form-input">
                            <x-form-error field="reason" />
                        </div>
                        <button type="submit" @class(['btn-danger' => $organization->status === 'active', 'btn-secondary' => $organization->status !== 'active'])>{{ $organization->status === 'active' ? 'Arsipkan' : 'Aktifkan' }}</button>
                    </form>
                </section>
            @endcan
        </div>
    </div>
</x-layouts.app>
