<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Verifikasi harian rantai hash audit (keamanan/11 SEC-LOG-13).
Schedule::command('stu:audit-verify')->dailyAt('02:30')->timezone(display_tz())->onOneServer();

// Minimisasi data: registrasi tak terverifikasi & token kedaluwarsa (keamanan/12).
Schedule::command('stu:prune-unverified')->dailyAt('03:00')->timezone(display_tz())->onOneServer();

// Auto-submit attempt ujian yang melewati deadline (keamanan/08 SEC-EXAM-05).
Schedule::command('stu:exams-auto-submit')->everyMinute()->withoutOverlapping()->onOneServer();

// Pengingat sertifikat yang akan kedaluwarsa (FR-CERT-012).
Schedule::command('stu:certificates-expiry-reminders')->dailyAt('08:00')->timezone(display_tz())->onOneServer();

// Tagihan transfer manual yang melewati batas waktu tanpa bukti (FR-PAY, tahap A).
Schedule::command('stu:payments-expire')->hourly()->withoutOverlapping()->onOneServer();

// Pengingat tenggat tugas/sesi, peserta tidak aktif, program baru (kanal sesuai preferensi).
Schedule::command('stu:reminders')->hourly()->withoutOverlapping()->onOneServer();

// Backup harian, pemindaian keamanan tiap jam, retensi data harian (dini hari).
Schedule::command('stu:backup')->dailyAt('01:30')->timezone(display_tz())->withoutOverlapping()->onOneServer();
Schedule::command('stu:security-scan')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('stu:retention-prune')->dailyAt('03:30')->timezone(display_tz())->onOneServer();
