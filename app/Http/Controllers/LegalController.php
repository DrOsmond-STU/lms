<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Halaman dokumen hukum berversi (docs/08 PUB-07/PUB-08). Isi final dari tim legal
 * menggantikan ringkasan draf ini sebelum go-live.
 */
final class LegalController
{
    public function terms(): View
    {
        return view('legal.document', [
            'title' => 'Syarat & Ketentuan',
            'version' => (string) config('legal.terms_version'),
            'points' => [
                'Akun bersifat pribadi dan tidak boleh dipinjamkan. Anda bertanggung jawab menjaga kerahasiaan kata sandi dan kode autentikasi.',
                'Pengerjaan kuis dan ujian wajib dilakukan sendiri. Kecurangan dapat mengakibatkan pembatalan kelulusan dan pencabutan sertifikat.',
                'Sertifikat diterbitkan setelah syarat kelulusan terpenuhi dan disetujui penyelenggara, serta dapat diverifikasi publik.',
                'Materi pelatihan dilindungi hak cipta dan hanya untuk penggunaan pribadi peserta terdaftar.',
            ],
        ]);
    }

    public function privacy(): View
    {
        return view('legal.document', [
            'title' => 'Kebijakan Privasi',
            'version' => (string) config('legal.privacy_version'),
            'points' => [
                'Data yang kami proses: nama, email, nomor HP (opsional), organisasi, aktivitas belajar, nilai, dan data teknis keamanan (alamat IP, perangkat) — hanya untuk menyelenggarakan pelatihan, sertifikasi, dan menjaga keamanan akun.',
                'Organisasi tempat Anda terdaftar dapat melihat progres dan nilai pelatihan yang Anda ikuti melalui organisasi tersebut.',
                'Data tidak dijual kepada pihak mana pun. Pemroses pihak ketiga (email, pembayaran) terikat perjanjian pemrosesan data.',
                'Anda berhak mengakses, memperbaiki, dan meminta penghapusan data pribadi sesuai UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi, dengan pengecualian data sertifikat yang wajib disimpan untuk verifikasi.',
            ],
        ]);
    }
}
