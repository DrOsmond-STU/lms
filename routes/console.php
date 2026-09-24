<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Verifikasi harian rantai hash audit (keamanan/11 SEC-LOG-13).
Schedule::command('stu:audit-verify')->dailyAt('02:30')->timezone('Asia/Jakarta')->onOneServer();

// Minimisasi data: registrasi tak terverifikasi & token kedaluwarsa (keamanan/12).
Schedule::command('stu:prune-unverified')->dailyAt('03:00')->timezone('Asia/Jakarta')->onOneServer();

// Auto-submit attempt ujian yang melewati deadline (keamanan/08 SEC-EXAM-05).
Schedule::command('stu:exams-auto-submit')->everyMinute()->withoutOverlapping()->onOneServer();

// Pengingat sertifikat yang akan kedaluwarsa (FR-CERT-012).
Schedule::command('stu:certificates-expiry-reminders')->dailyAt('08:00')->timezone('Asia/Jakarta')->onOneServer();
