<?php

declare(strict_types=1);

/*
| Nilai dasar operasional LMS. Semua dapat diubah admin di Pengaturan Sistem (dalam batas
| aman) — lihat App\Modules\Settings\Services\SystemSettings::DEFINITIONS. Nilai di sini hanya
| dipakai bila belum ada pengaturan tersimpan.
*/

return [
    'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'Asia/Jakarta'),
    'video_completion_percent' => 90,
    'exam_grace_seconds' => 30,
    'default_passing_score' => 70,
    'default_certificate_validity_months' => 36,
    'certificate_issuer_code' => 'STU',
    'certificate_expiry_reminder_days' => 60,
    'payment_deadline_hours' => 72,
    'payment_manual_settle_threshold' => 1_000_000,
];
