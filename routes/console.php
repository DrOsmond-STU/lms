<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Verifikasi harian rantai hash audit (keamanan/11 SEC-LOG-13).
Schedule::command('stu:audit-verify')->dailyAt('02:30')->timezone('Asia/Jakarta')->onOneServer();
