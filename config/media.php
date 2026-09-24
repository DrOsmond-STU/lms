<?php

declare(strict_types=1);

/*
| Unggahan & penyajian media (FR-CNT-003/004, keamanan/05). Berkas disimpan di disk privat
| (storage/app/private), tidak pernah di bawah web root, dan disajikan lewat URL bertanda
| tangan berumur pendek setelah pemeriksaan hak akses.
*/

return [
    'disk' => 'local',

    // clamd (soket ClamAV) | none (hanya lokal/staging — ditolak di produksi)
    'scanner' => env('MEDIA_SCANNER', 'none'),
    'clamd_address' => env('CLAMD_ADDRESS', 'unix:///var/run/clamav/clamd.ctl'),

    'signed_url_minutes' => 10,

    // Lesson tautan eksternal hanya ke domain ini (termasuk subdomain) — FR-CNT-002.
    'link_allowlist' => array_values(array_filter(array_map('trim', explode(',', (string) env('LINK_ALLOWLIST',
        'youtube.com,youtu.be,vimeo.com,docs.google.com,drive.google.com,forms.gle,microsoft.com,office.com,sharepoint.com,github.com,aws.amazon.com,learn.microsoft.com,cloud.google.com,bnsp.go.id'))))),

    // Jenis sebenarnya dideteksi dari isi berkas (magic bytes), bukan ekstensi/header klien.
    'types' => [
        'pdf' => ['mimes' => ['application/pdf' => 'pdf'], 'max_mb' => 50],
        'video' => ['mimes' => ['video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm'], 'max_mb' => (int) env('MEDIA_MAX_VIDEO_MB', 2048)],
        'image' => ['mimes' => ['image/png' => 'png', 'image/jpeg' => 'jpg'], 'max_mb' => 5],
    ],
];
