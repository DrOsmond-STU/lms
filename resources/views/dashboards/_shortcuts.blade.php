{{-- Pintasan dari konfigurasi navigasi: hanya halaman yang boleh dibuka pengguna. $workspace, $user --}}
@php
    $hints = [
        'catalog.participant' => 'Program terbit & kelas yang dibuka',
        'learning.index' => 'Lanjutkan materi & asesmen',
        'certificates.mine' => 'Unduh PDF bertanda tangan',
        'trainer.classes' => 'Materi, asesmen & penilaian',
        'org.members' => 'Setujui & kelola anggota',
        'org.enrollments' => 'Progres pelatihan anggota',
        'org.audit' => 'Aktivitas di organisasi Anda',
        'admin.approvals.index' => 'Terbitkan sertifikat peserta lulus',
        'admin.second-approvals.index' => 'Aksi maker–checker',
        'admin.programs.index' => 'Buat, review & terbitkan',
        'admin.classes.index' => 'Batch, jadwal & trainer',
        'admin.organizations.index' => 'Institusi & korporat mitra',
        'admin.users.index' => 'Undang trainer, admin & peserta',
        'admin.templates.index' => 'Desain & versi sertifikat',
        'admin.certificates.index' => 'Cari, cabut & ekspor CSV',
        'admin.audit.index' => 'Rekam jejak berantai hash',
        'admin.settings.edit' => 'Batas keamanan sistem',
    ];
    $shortcuts = collect(config("navigation.{$workspace}", []))->flatMap(fn ($group) => $group['items'])
        ->filter(fn ($item) => isset($hints[$item['route'] ?? '']) && ($item['permission'] === null || $user->can($item['permission'])))
        ->take(4);
@endphp
@if ($shortcuts->isNotEmpty())
    <section class="mt-8">
        <div class="section-head"><h2>Pintasan</h2><span class="sub">Halaman yang paling sering Anda buka</span></div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($shortcuts as $item)
                @include('dashboards._shortcut', ['href' => route($item['route']), 'icon' => $item['icon'], 'title' => $item['label'], 'hint' => $hints[$item['route']]])
            @endforeach
        </div>
    </section>
@endif
