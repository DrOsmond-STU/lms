<x-layouts.app :title="$user->name" workspace="admin">
    <x-slot:back><a href="{{ route('admin.users.index') }}" class="hero-back">&larr; Pengguna</a></x-slot:back>
    <x-slot:heading>{{ $user->name }}</x-slot:heading>
    <x-slot:meta>
        @include('admin.users._status', ['status' => $user->status])
        @if ($hasMfa)
        <span class="badge bg-emerald-50 text-emerald-700">MFA aktif</span>
        @else
        <span class="badge bg-amber-50 text-amber-700">MFA belum aktif</span>
        @endif
    </x-slot:meta>

    @unless ($canManage)
        <div class="mb-5 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700" role="note">
            {{ $user->id === auth()->id() ? 'Ini akun Anda sendiri — kelola lewat halaman Keamanan Akun. Perubahan peran/status akun sendiri tidak diizinkan.' : 'Akun ini hanya dapat dikelola oleh Super Admin.' }}
        </div>
    @endunless

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card p-6" aria-labelledby="profile-heading">
                <h2 id="profile-heading" class="mb-4 font-bold text-slate-800">Profil</h2>
                <dl class="mb-5 grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-slate-500">Email</dt><dd class="font-bold break-all">{{ $user->email }}</dd></div>
                    <div><dt class="text-slate-500">Email terverifikasi</dt><dd class="font-bold">{{ $user->email_verified_at?->timezone('Asia/Jakarta')->translatedFormat('d M Y H:i') ?? 'Belum' }}</dd></div>
                    <div><dt class="text-slate-500">Terakhir masuk</dt><dd class="font-bold">{{ $user->last_login_at?->timezone('Asia/Jakarta')->translatedFormat('d M Y H:i') ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Dibuat</dt><dd class="font-bold">{{ $user->created_at?->timezone('Asia/Jakarta')->translatedFormat('d M Y') }}</dd></div>
                </dl>
                @if ($canManage)
                    @can('user.update')
                        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="flex flex-wrap items-end gap-3" novalidate>
                            @csrf
                            @method('PUT')
                            <div class="min-w-56 flex-1">
                                <label for="name" class="form-label">Nama Lengkap</label>
                                <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required maxlength="120" class="form-input">
                                <x-form-error field="name" />
                            </div>
                            <button type="submit" class="btn-secondary">Simpan</button>
                        </form>
                    @endcan
                @endif
            </section>

            <section class="card p-6" aria-labelledby="roles-heading">
                <h2 id="roles-heading" class="mb-4 font-bold text-slate-800">Peran</h2>
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse ($user->roles as $role)
                        @php($orgId = $role->pivot->organization_id)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <span>
                                <span class="font-bold">{{ \App\Modules\Access\RoleCode::from($role->code)->label() }}</span>
                                <span class="block text-xs text-slate-500">{{ $orgId ? ($organizationNames[$orgId] ?? 'Organisasi') : 'Platform / tanpa organisasi' }}</span>
                            </span>
                            @if ($canManage && in_array(\App\Modules\Access\RoleCode::from($role->code), $assignable, true))
                                @can('user.assign_role')
                                    <form method="POST" action="{{ route('admin.users.roles.destroy', [$user, $role->pivot->id]) }}" class="flex items-center gap-2" novalidate>
                                        @csrf
                                        @method('DELETE')
                                        <label for="reason-{{ $role->pivot->id }}" class="sr-only">Alasan pencabutan</label>
                                        <input id="reason-{{ $role->pivot->id }}" name="reason" type="text" required minlength="5" maxlength="500" placeholder="Alasan pencabutan" class="form-input py-1.5 text-xs">
                                        <button type="submit" class="btn-mini-danger">Cabut</button>
                                    </form>
                                @endcan
                            @endif
                        </li>
                    @empty
                        <li class="py-3 text-slate-500">Belum ada peran.</li>
                    @endforelse
                </ul>
                <x-form-error field="reason" />

                @if ($canManage && $assignable !== [])
                    @can('user.assign_role')
                        <form method="POST" action="{{ route('admin.users.roles.store', $user) }}" class="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2" novalidate>
                            @csrf
                            @include('admin.users._role-fields', ['selectedRole' => '', 'selectedOrganization' => ''])
                            <div class="sm:col-span-2"><button type="submit" class="btn-secondary">Tetapkan Peran</button></div>
                        </form>
                    @endcan
                @endif
            </section>

            @if ($memberships->isNotEmpty())
                <section class="card p-6" aria-labelledby="membership-heading">
                    <h2 id="membership-heading" class="mb-3 font-bold text-slate-800">Keanggotaan Organisasi</h2>
                    <ul class="space-y-1 text-sm">
                        @foreach ($memberships as $membership)
                            <li>{{ $membership->name }} <span class="font-mono text-xs text-slate-500">{{ $membership->code }}</span> — <span class="font-bold">{{ ['active' => 'aktif', 'pending' => 'menunggu persetujuan', 'rejected' => 'ditolak', 'removed' => 'dikeluarkan'][$membership->status] ?? $membership->status }}</span></li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <div class="space-y-6">
            @if ($awaitingInvitation && $canManage)
                @can('user.create')
                    <section class="card p-6" aria-labelledby="invite-heading">
                        <h2 id="invite-heading" class="font-bold text-slate-800">Undangan</h2>
                        <p class="mt-1 text-sm text-slate-600">Pengguna belum mengatur kata sandi.</p>
                        <form method="POST" action="{{ route('admin.users.invitation', $user) }}" class="mt-3">
                            @csrf
                            <button type="submit" class="btn-secondary">Kirim Ulang Undangan</button>
                        </form>
                    </section>
                @endcan
            @endif

            @if ($canManage && auth()->user()->hasRole(\App\Modules\Access\RoleCode::SuperAdmin) && ! $user->hasRole(\App\Modules\Access\RoleCode::SuperAdmin) && $user->status === 'active')
                <section class="card p-6" aria-labelledby="sa-heading">
                    <h2 id="sa-heading" class="font-bold text-slate-800">Jadikan Super Admin</h2>
                    <p class="mt-1 text-xs text-slate-500">Maks. 3 akun. Dieksekusi setelah disetujui Super Admin kedua.</p>
                    <form method="POST" action="{{ route('admin.users.super-admin', $user) }}" class="mt-3 space-y-2" novalidate>@csrf
                        <label for="sa_reason" class="form-label">Alasan</label>
                        <input id="sa_reason" name="reason" minlength="10" maxlength="500" class="form-input">
                        <button class="btn-secondary">Ajukan</button>
                    </form>
                </section>
            @endif

            @if ($canManage && $hasMfa)
                @can('user.reset_mfa')
                    <section class="card p-6" aria-labelledby="mfa-heading">
                        <h2 id="mfa-heading" class="font-bold text-slate-800">Reset MFA</h2>
                        <p class="mt-1 text-xs text-slate-500">Hanya setelah identitas pemohon diverifikasi (panggilan video/dokumen/tatap muka) dan tercatat di tiket dukungan. Tercatat di jejak audit.</p>
                        <form method="POST" action="{{ route('admin.users.mfa-reset', $user) }}" class="mt-3 space-y-3" novalidate>
                            @csrf
                            <div>
                                <label for="ticket_reference" class="form-label">Nomor Tiket</label>
                                <input id="ticket_reference" name="ticket_reference" type="text" value="{{ old('ticket_reference') }}" required maxlength="64" class="form-input">
                                <x-form-error field="ticket_reference" />
                            </div>
                            <div>
                                <label for="verification_method" class="form-label">Metode Verifikasi</label>
                                <select id="verification_method" name="verification_method" class="form-select">
                                    <option value="video_call">Panggilan video</option>
                                    <option value="document">Verifikasi dokumen</option>
                                    <option value="in_person">Tatap muka</option>
                                </select>
                            </div>
                            <div>
                                <label for="note" class="form-label">Catatan (opsional)</label>
                                <input id="note" name="note" type="text" maxlength="500" class="form-input">
                            </div>
                            <label class="flex items-start gap-2 text-xs text-slate-700">
                                <input type="checkbox" name="confirm_identity" value="1" class="mt-0.5">
                                <span>Saya telah memverifikasi identitas pemohon sesuai prosedur.</span>
                            </label>
                            <x-form-error field="confirm_identity" />
                            <button type="submit" class="btn-danger">Reset MFA</button>
                        </form>
                    </section>
                @endcan
            @endif

            @if ($canManage)
                @can('user.deactivate')
                    <section class="card p-6" aria-labelledby="status-heading">
                        <h2 id="status-heading" class="font-bold text-slate-800">{{ $user->status === 'deactivated' ? 'Aktifkan Kembali' : 'Nonaktifkan Akun' }}</h2>
                        <p class="mt-1 text-xs text-slate-500">{{ $user->status === 'deactivated' ? 'Pengguna dapat masuk kembali.' : 'Semua sesi & tautan dicabut seketika. Data tetap disimpan (sertifikat tetap dapat diverifikasi).' }}</p>
                        <form method="POST" action="{{ route('admin.users.status', $user) }}" class="mt-3 space-y-3" novalidate>
                            @csrf
                            <div>
                                <label for="status_reason" class="form-label">Alasan</label>
                                <input id="status_reason" name="reason" type="text" required minlength="5" maxlength="500" class="form-input">
                            </div>
                            <button type="submit" @class(['btn-danger' => $user->status !== 'deactivated', 'btn-secondary' => $user->status === 'deactivated'])>{{ $user->status === 'deactivated' ? 'Aktifkan' : 'Nonaktifkan' }}</button>
                        </form>
                    </section>
                @endcan
            @endif
        </div>
    </div>
</x-layouts.app>
