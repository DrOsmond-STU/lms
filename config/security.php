<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Konfigurasi keamanan STU LMS
|--------------------------------------------------------------------------
| Nilai di sini adalah baseline dari docs/keamanan/. Pengaturan dari UI admin
| (Fase 1) hanya boleh MEMPERKETAT nilai ini, tidak melonggarkan.
*/

return [

    // Pepper untuk HMAC token/OTP/kode pemulihan/blind index (keamanan/06).
    'pepper' => env('SECURITY_PEPPER'),

    // Peran yang wajib MFA (keamanan/02 SEC-AUTH-10).
    'mfa_required_roles' => [
        'super_admin', 'academic_admin', 'finance_admin', 'support_admin', 'org_admin', 'supervisor', 'trainer',
    ],

    // Batas waktu sesi dalam menit (keamanan/02 SEC-AUTH-18).
    'session' => [
        'idle_minutes' => ['privileged' => 30, 'participant' => 120],
        'absolute_minutes' => ['privileged' => 480, 'participant' => 720],
        'reauth_minutes' => 15,
    ],

    // Rate limit login (keamanan/02 SEC-AUTH-03, SEC-AUTH-04).
    'login_throttle' => [
        'per_account_short' => ['attempts' => 5, 'decay_seconds' => 900],
        'per_account_long' => ['attempts' => 20, 'decay_seconds' => 86400],
        'per_ip' => ['attempts' => 30, 'decay_seconds' => 60],
    ],

    'mfa' => [
        'pending_ttl_seconds' => 300,
        'max_attempts' => 5,
        'recovery_codes' => 10,
    ],

    'password' => [
        'min_participant' => 8,
        'min_privileged' => 12,
        'max' => 128,
    ],

    // Registrasi mandiri peserta (FR-AUTH-001..003, keamanan/02 SEC-AUTH-09).
    'registration' => [
        'enabled' => (bool) env('REGISTRATION_ENABLED', true),
        'otp_ttl_minutes' => 10,
        'otp_max_attempts' => 5,
        'otp_resend_per_hour' => 3,
        'per_ip_per_hour' => 10,
        'prune_unverified_after_days' => 7,
    ],

    // Undangan akun dari admin (FR-USER-003, docs/07 §7).
    'invitation' => [
        'ttl_hours' => 72,
    ],

    // Domain email publik tidak boleh dijadikan domain terverifikasi organisasi: siapa pun
    // dapat memiliki alamatnya sehingga akan membuka keanggotaan otomatis (FR-AUTH-003).
    'public_email_domains' => [
        'gmail.com', 'googlemail.com', 'yahoo.com', 'yahoo.co.id', 'ymail.com', 'outlook.com', 'hotmail.com',
        'live.com', 'msn.com', 'icloud.com', 'me.com', 'aol.com', 'proton.me', 'protonmail.com', 'gmx.com',
        'mail.com', 'zoho.com', 'yandex.com', 'rocketmail.com',
    ],

    // Proxy tepercaya (rentang IP CDN/LB), dipisah koma. Kosong = tidak ada proxy; jangan '*'.
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),

    // Header keamanan (keamanan/13 SEC-INFRA-12).
    'headers' => [
        'hsts' => 'max-age=63072000; includeSubDomains; preload',
        'csp_report_uri' => env('CSP_REPORT_URI'),
    ],
];
