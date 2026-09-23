<?php

declare(strict_types=1);

/*
| Navigasi sidebar per area, diturunkan dari prototype/assets/js/layout.js (NAV).
| 'route' => null berarti halaman dibangun di fase berikutnya (ditampilkan nonaktif).
| 'permission' => izin yang dibutuhkan untuk menampilkan item (docs/07).
*/

return [
    'participant' => [
        ['group' => null, 'items' => [
            ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'participant.dashboard', 'permission' => null],
            ['label' => 'Pilih Pelatihan', 'icon' => 'cert', 'route' => null, 'permission' => 'program.view_any'],
            ['label' => 'Pembelajaran Saya', 'icon' => 'book', 'route' => null, 'permission' => 'enrollment.view'],
            ['label' => 'Jadwal', 'icon' => 'calendar', 'route' => null, 'permission' => null],
            ['label' => 'Sertifikat Saya', 'icon' => 'shield', 'route' => null, 'permission' => 'certificate.view'],
            ['label' => 'Pencapaian', 'icon' => 'trophy', 'route' => null, 'permission' => 'gamification.view_leaderboard'],
            ['label' => 'Notifikasi', 'icon' => 'bell', 'route' => null, 'permission' => null],
            ['label' => 'Profil & Keamanan', 'icon' => 'user', 'route' => 'mfa.setup', 'permission' => null],
        ]],
    ],
    'trainer' => [
        ['group' => null, 'items' => [
            ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'trainer.dashboard', 'permission' => null],
            ['label' => 'Kelas Saya', 'icon' => 'layers', 'route' => null, 'permission' => 'course_class.view_any'],
            ['label' => 'Peserta & Nilai', 'icon' => 'users', 'route' => null, 'permission' => 'submission.review'],
            ['label' => 'Diskusi', 'icon' => 'chat', 'route' => null, 'permission' => 'discussion.moderate'],
            ['label' => 'Laporan', 'icon' => 'chart', 'route' => null, 'permission' => 'report.view_class'],
        ]],
    ],
    'organization' => [
        ['group' => null, 'items' => [
            ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'organization.dashboard', 'permission' => null],
            ['label' => 'Anggota', 'icon' => 'users', 'route' => null, 'permission' => 'organization.manage_members'],
            ['label' => 'Pendaftaran Massal', 'icon' => 'clipboard', 'route' => null, 'permission' => 'enrollment.bulk_create'],
            ['label' => 'Laporan Organisasi', 'icon' => 'chart', 'route' => null, 'permission' => 'report.view_organization'],
            ['label' => 'Tinjauan Akses', 'icon' => 'shield', 'route' => null, 'permission' => 'organization.access_review'],
        ]],
    ],
    'admin' => [
        ['group' => null, 'items' => [
            ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'admin.dashboard', 'permission' => null],
            ['label' => 'Approval Sertifikat', 'icon' => 'check', 'route' => null, 'permission' => 'certificate.approve'],
        ]],
        ['group' => 'Master Data', 'items' => [
            ['label' => 'Program Pelatihan', 'icon' => 'cert', 'route' => null, 'permission' => 'program.view_any'],
            ['label' => 'Kelas & Jadwal', 'icon' => 'layers', 'route' => null, 'permission' => 'course_class.view_any'],
            ['label' => 'Organisasi', 'icon' => 'building', 'route' => null, 'permission' => 'organization.view_any'],
            ['label' => 'Pengguna', 'icon' => 'users', 'route' => null, 'permission' => 'user.view_any'],
            ['label' => 'Template Sertifikat', 'icon' => 'doc', 'route' => null, 'permission' => 'certificate_template.view_any'],
        ]],
        ['group' => 'Operasional', 'items' => [
            ['label' => 'Enrollment', 'icon' => 'clipboard', 'route' => null, 'permission' => 'enrollment.view_any'],
            ['label' => 'Pembayaran', 'icon' => 'card', 'route' => null, 'permission' => 'payment.view_any'],
            ['label' => 'Basis Data Sertifikat', 'icon' => 'doc', 'route' => null, 'permission' => 'certificate.view_any'],
        ]],
        ['group' => 'Laporan', 'items' => [
            ['label' => 'Laporan Platform', 'icon' => 'chart', 'route' => null, 'permission' => 'report.view_platform'],
            ['label' => 'Jejak Audit', 'icon' => 'history', 'route' => null, 'permission' => 'audit_log.view'],
        ]],
        ['group' => 'Pengaturan', 'items' => [
            ['label' => 'Pengaturan Sistem', 'icon' => 'settings', 'route' => null, 'permission' => 'system_setting.view'],
        ]],
    ],
];
