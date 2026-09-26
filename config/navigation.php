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
            ['label' => 'Pilih Pelatihan', 'icon' => 'cert', 'route' => 'catalog.participant', 'permission' => 'program.view_any'],
            ['label' => 'Pembelajaran Saya', 'icon' => 'book', 'route' => 'learning.index', 'permission' => 'enrollment.view'],
            ['label' => 'Jadwal', 'icon' => 'calendar', 'route' => 'schedule.participant', 'permission' => null],
            ['label' => 'Pengumuman', 'icon' => 'chat-bubble', 'route' => 'announcements.index', 'permission' => null],
            ['label' => 'Sertifikat Saya', 'icon' => 'shield', 'route' => 'certificates.mine', 'permission' => 'certificate.view'],
            ['label' => 'Transaksi', 'icon' => 'card', 'route' => 'payments.mine', 'permission' => 'payment.view'],
            ['label' => 'Referral', 'icon' => 'star', 'route' => 'referral.mine', 'permission' => 'referral.view'],
            ['label' => 'Pencapaian', 'icon' => 'trophy', 'route' => null, 'permission' => 'gamification.view_leaderboard'],
            ['label' => 'Notifikasi', 'icon' => 'bell', 'route' => 'notifications.index', 'permission' => null],
            ['label' => 'Akun Saya', 'icon' => 'user', 'route' => 'account.profile', 'permission' => null],
        ]],
    ],
    'trainer' => [
        ['group' => null, 'items' => [
            ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'trainer.dashboard', 'permission' => null],
            ['label' => 'Kelas Saya', 'icon' => 'layers', 'route' => 'trainer.classes', 'permission' => 'course_class.view_any'],
            ['label' => 'Jadwal', 'icon' => 'calendar', 'route' => 'schedule.trainer', 'permission' => null],
            ['label' => 'Pengumuman', 'icon' => 'chat-bubble', 'route' => 'announcements.index', 'permission' => null],
            ['label' => 'Laporan', 'icon' => 'chart', 'route' => null, 'permission' => 'report.view_class'],
            ['label' => 'Notifikasi', 'icon' => 'bell', 'route' => 'notifications.index', 'permission' => null],
            ['label' => 'Akun Saya', 'icon' => 'user', 'route' => 'account.profile', 'permission' => null],
        ]],
    ],
    'organization' => [
        ['group' => null, 'items' => [
            ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'organization.dashboard', 'permission' => null],
            ['label' => 'Anggota', 'icon' => 'users', 'route' => 'org.members', 'permission' => 'organization.manage_members'],
            ['label' => 'Progres Anggota', 'icon' => 'chart', 'route' => 'org.enrollments', 'permission' => 'report.view_organization'],
            ['label' => 'Pengumuman', 'icon' => 'chat-bubble', 'route' => 'org.announcements.index', 'permission' => 'announcement.manage'],
            ['label' => 'Pendaftaran Massal', 'icon' => 'clipboard', 'route' => null, 'permission' => 'enrollment.bulk_create'],
            ['label' => 'Jejak Audit', 'icon' => 'history', 'route' => 'org.audit', 'permission' => 'audit_log.view'],
            ['label' => 'Notifikasi', 'icon' => 'bell', 'route' => 'notifications.index', 'permission' => null],
            ['label' => 'Akun Saya', 'icon' => 'user', 'route' => 'account.profile', 'permission' => null],
        ]],
    ],
    'admin' => [
        ['group' => null, 'items' => [
            ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'admin.dashboard', 'permission' => null],
            ['label' => 'Approval Sertifikat', 'icon' => 'check', 'route' => 'admin.approvals.index', 'permission' => 'certificate.approve'],
            ['label' => 'Persetujuan Kedua', 'icon' => 'shield', 'route' => 'admin.second-approvals.index', 'permission' => null],
        ]],
        ['group' => 'Master Data', 'items' => [
            ['label' => 'Program Pelatihan', 'icon' => 'cert', 'route' => 'admin.programs.index', 'permission' => 'program.view_any'],
            ['label' => 'Kelas & Jadwal', 'icon' => 'layers', 'route' => 'admin.classes.index', 'permission' => 'course_class.view_any'],
            ['label' => 'Organisasi', 'icon' => 'building', 'route' => 'admin.organizations.index', 'permission' => 'organization.view_any'],
            ['label' => 'Pengguna', 'icon' => 'users', 'route' => 'admin.users.index', 'permission' => 'user.view_any'],
            ['label' => 'Template Sertifikat', 'icon' => 'doc', 'route' => 'admin.templates.index', 'permission' => 'certificate_template.view_any'],
            ['label' => 'Kalender Akademik', 'icon' => 'calendar', 'route' => 'admin.calendar.index', 'permission' => 'calendar.manage'],
            ['label' => 'Pengumuman', 'icon' => 'chat-bubble', 'route' => 'admin.announcements.index', 'permission' => 'announcement.manage'],
        ]],
        ['group' => 'Operasional', 'items' => [
            ['label' => 'Pembayaran', 'icon' => 'card', 'route' => 'admin.payments.index', 'permission' => 'payment.view_any'],
            ['label' => 'Basis Data Sertifikat', 'icon' => 'doc', 'route' => 'admin.certificates.index', 'permission' => 'certificate.view_any'],
        ]],
        ['group' => 'Laporan', 'items' => [
            ['label' => 'Per Organisasi', 'icon' => 'building', 'route' => 'admin.reports.organizations', 'permission' => 'report.view_platform'],
            ['label' => 'Referral', 'icon' => 'star', 'route' => 'admin.reports.referral', 'permission' => 'referral.view_any'],
            ['label' => 'Jejak Audit', 'icon' => 'history', 'route' => 'admin.audit.index', 'permission' => 'audit_log.view'],
        ]],
        ['group' => 'Pengaturan', 'items' => [
            ['label' => 'Pengaturan Sistem', 'icon' => 'settings', 'route' => 'admin.settings.edit', 'permission' => ['system_setting.view', 'cms.view'], 'active' => ['admin.settings.*', 'admin.landing.*']],
            ['label' => 'Notifikasi', 'icon' => 'bell', 'route' => 'notifications.index', 'permission' => null],
            ['label' => 'Akun Saya', 'icon' => 'user', 'route' => 'account.profile', 'permission' => null],
        ]],
    ],
];
