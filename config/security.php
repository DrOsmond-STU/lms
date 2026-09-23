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
        'super_admin', 'academic_admin', 'finance_admin', 'support_admin', 'org_admin', 'trainer',
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
        'issuer' => env('APP_NAME', 'STU LMS'),
        'pending_ttl_seconds' => 300,
        'max_attempts' => 5,
        'recovery_codes' => 10,
    ],

    'password' => [
        'min_participant' => 8,
        'min_privileged' => 12,
        'max' => 128,
    ],

    // Header keamanan (keamanan/13 SEC-INFRA-12).
    'headers' => [
        'hsts' => 'max-age=63072000; includeSubDomains; preload',
        'csp_report_uri' => env('CSP_REPORT_URI'),
    ],
];
