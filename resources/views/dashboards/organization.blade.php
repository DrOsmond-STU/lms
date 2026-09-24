@php
    $statuses = \App\Modules\Enrollment\Models\Enrollment::STATUSES;
    $totalEnrollments = max(1, collect($enrollments)->sum());
    $learning = (int) (($enrollments['enrolled'] ?? 0) + ($enrollments['in_progress'] ?? 0));
    $pending = (int) ($members->pending ?? 0);
@endphp
<x-layouts.app title="Dashboard Organisasi" workspace="organization" :eyebrow="'Admin Organisasi · '.now()->timezone(display_tz())->translatedFormat('F Y')">
    <x-slot:heading>{{ $organizations->pluck('name')->implode(', ') ?: 'Organisasi Anda' }}</x-slot:heading>
    <x-slot:subtitle>Pantau keanggotaan dan progres pelatihan anggota organisasi Anda.</x-slot:subtitle>
    <x-slot:aside>
        <div class="num">{{ (int) ($members->active ?? 0) }}</div>
        <div class="lbl">Anggota aktif</div>
    </x-slot:aside>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @include('dashboards._tile', ['label' => 'Anggota aktif', 'value' => (int) ($members->active ?? 0), 'icon' => 'users', 'note' => 'Terverifikasi sebagai anggota', 'link' => $user->can('organization.manage_members') ? route('org.members') : null])
        @include('dashboards._tile', ['label' => 'Menunggu persetujuan', 'value' => $pending, 'icon' => 'check', 'trend' => $pending > 0 ? 'Perlu ditinjau' : 'Tidak ada antrean', 'tone' => $pending > 0 ? 'bad' : 'good', 'note' => 'Pendaftar dengan kode organisasi', 'link' => $user->can('organization.manage_members') ? route('org.members', ['status' => 'pending']) : null])
        @include('dashboards._tile', ['label' => 'Sedang belajar', 'value' => $learning, 'icon' => 'book', 'note' => 'Terdaftar atau sedang belajar', 'link' => $user->can('report.view_organization') ? route('org.enrollments') : null])
        @include('dashboards._tile', ['label' => 'Sertifikat aktif', 'value' => $certificates, 'icon' => 'cert', 'note' => 'Milik anggota organisasi'])
    </div>

    <section class="mt-8 grid gap-4 lg:grid-cols-2">
        <div class="card p-5">
            <h2 class="card-title">Enrollment Anggota per Status</h2>
            <p class="card-sub">Seluruh pendaftaran pelatihan anggota</p>
            <div class="space-y-3">
                @foreach ($statuses as $status => $label)
                    @php($count = (int) ($enrollments[$status] ?? 0))
                    <div class="grid grid-cols-[1fr_auto] items-center gap-x-3 gap-y-1">
                        <span class="text-sm text-slate-800">{{ $label }}</span>
                        <span class="font-mono text-sm font-semibold text-slate-800">{{ $count }}</span>
                        <span class="bar col-span-2 h-1.5"><span class="progress-{{ (int) (round($count * 20 / $totalEnrollments) * 5) }}"></span></span>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card p-5">
            <h2 class="card-title">Perhatian Segera</h2>
            <p class="card-sub">Tindakan yang menunggu Anda</p>
            @if ($pending > 0)
                <a href="{{ route('org.members', ['status' => 'pending']) }}" class="feed-item group">
                    <span class="min-w-0"><span class="chip chip-high">Anggota baru</span><span class="feed-title mt-1.5 group-hover:text-link">{{ $pending }} pendaftar menunggu persetujuan keanggotaan</span><span class="feed-meta">Setujui hanya bila Anda yakin mereka anggota sah.</span></span>
                </a>
            @else
                <p class="py-6 text-center text-sm text-slate-500">Tidak ada antrean. Semua beres.</p>
            @endif
        </div>
    </section>

    @include('dashboards._shortcuts', ['workspace' => 'organization'])
</x-layouts.app>
