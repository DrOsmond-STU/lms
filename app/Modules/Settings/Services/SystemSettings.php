<?php

declare(strict_types=1);

namespace App\Modules\Settings\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Pengaturan sistem (FR-SET-001/002/005): identitas, teks beranda, pendaftaran, pembelajaran,
 * sertifikat, dan keamanan — tidak ada nilai operasional yang harus diubah lewat kode.
 * Nilai keamanan hanya boleh berada dalam batas aman yang ditetapkan di sini, sehingga UI tidak
 * dapat melonggarkan baseline (config/security.php). Setiap perubahan diaudit (sebelum/sesudah).
 */
final class SystemSettings
{
    private const CACHE_KEY = 'system_settings.v1';

    /** Tab pengaturan: slug => [label, izin lihat, izin ubah, butuh re-autentikasi]. */
    public const TABS = [
        'umum' => ['Umum', 'system_setting.view', 'system_setting.update', true],
        'pemilik' => ['Profil Pemilik', 'cms.view', 'cms.update', false],
        'beranda' => ['Beranda', 'cms.view', 'cms.update', false],
        'pendaftaran' => ['Pendaftaran & Akun', 'system_setting.view', 'system_setting.update', true],
        'pembelajaran' => ['Pembelajaran & Ujian', 'system_setting.view', 'system_setting.update', true],
        'sertifikat' => ['Sertifikat', 'system_setting.view', 'system_setting.update', true],
        'pembayaran' => ['Pembayaran', 'system_setting.view', 'system_setting.update', true],
        'legal' => ['Dokumen Hukum', 'system_setting.view', 'system_setting.update', true],
        'keamanan' => ['Keamanan', 'system_setting.view', 'system_setting.update', true],
        'integrasi' => ['Integrasi', 'system_setting.view', 'system_setting.update', true],
    ];

    /**
     * @var array<string, array{label: string, type: 'string'|'text'|'email'|'int'|'bool'|'select'|'secret', group: string, section?: string, default?: string|int|bool, min?: int, max?: int, config?: string, options?: array<string, string>, pattern?: string, required?: bool, help?: string, rows?: int}>
     */
    public const DEFINITIONS = [
        // ---- Umum -------------------------------------------------------------------------
        'branding.app_display_name' => ['label' => 'Nama aplikasi (judul tab & email)', 'type' => 'string', 'max' => 60, 'config' => 'app.name', 'required' => true, 'group' => 'umum', 'section' => 'Identitas'],
        'branding.short_name' => ['label' => 'Nama singkat pada logo', 'type' => 'string', 'max' => 24, 'default' => 'STU LMS', 'required' => true, 'group' => 'umum', 'section' => 'Identitas'],
        'branding.tagline' => ['label' => 'Tagline di bawah logo', 'type' => 'string', 'max' => 60, 'default' => 'Pelatihan & Sertifikasi', 'group' => 'umum', 'section' => 'Identitas'],
        'branding.display_timezone' => ['label' => 'Zona waktu tampilan', 'type' => 'select', 'options' => ['Asia/Jakarta' => 'WIB (Asia/Jakarta)', 'Asia/Makassar' => 'WITA (Asia/Makassar)', 'Asia/Jayapura' => 'WIT (Asia/Jayapura)'], 'config' => 'lms.display_timezone', 'group' => 'umum', 'section' => 'Regional'],
        'login.panel_eyebrow' => ['label' => 'Label kecil', 'type' => 'string', 'max' => 60, 'default' => 'Sertifikasi BNSP & Internasional', 'group' => 'umum', 'section' => 'Panel halaman masuk'],
        'login.panel_title' => ['label' => 'Judul', 'type' => 'string', 'max' => 120, 'default' => 'Belajar, dinilai, dan buktikan kompetensi Anda.', 'group' => 'umum', 'section' => 'Panel halaman masuk'],
        'login.panel_text' => ['label' => 'Deskripsi', 'type' => 'text', 'max' => 300, 'default' => 'Pendaftaran, materi, ujian, hingga sertifikat digital dalam satu platform.', 'group' => 'umum', 'section' => 'Panel halaman masuk'],
        'login.panel_quote' => ['label' => 'Kutipan', 'type' => 'text', 'max' => 200, 'default' => 'Setiap sertifikat dapat diverifikasi publik melalui kode unik dan QR.', 'group' => 'umum', 'section' => 'Panel halaman masuk'],

        // ---- Beranda (teks; slide/testimoni/mitra dikelola di halaman masing-masing) -------
        'landing.hero_eyebrow' => ['label' => 'Label kecil', 'type' => 'string', 'max' => 80, 'default' => 'Pelatihan & Sertifikasi', 'group' => 'beranda', 'section' => 'Pembuka (dipakai bila belum ada slide aktif)'],
        'landing.hero_title' => ['label' => 'Judul', 'type' => 'string', 'max' => 120, 'default' => 'Tingkatkan kompetensi Anda bersama kami', 'required' => true, 'group' => 'beranda', 'section' => 'Pembuka (dipakai bila belum ada slide aktif)'],
        'landing.hero_subtitle' => ['label' => 'Deskripsi', 'type' => 'text', 'max' => 300, 'default' => 'Program pelatihan bersertifikat Internasional & BNSP — belajar fleksibel, ujian yang adil, dan sertifikat digital yang dapat diverifikasi publik.', 'group' => 'beranda', 'section' => 'Pembuka (dipakai bila belum ada slide aktif)'],
        'landing.feature1_title' => ['label' => 'Keunggulan 1 — judul', 'type' => 'string', 'max' => 60, 'default' => 'Belajar terarah', 'group' => 'beranda', 'section' => 'Keunggulan'],
        'landing.feature1_text' => ['label' => 'Keunggulan 1 — teks', 'type' => 'text', 'max' => 200, 'default' => 'Modul, video, materi PDF, dan kuis dengan progres yang tersimpan otomatis.', 'group' => 'beranda', 'section' => 'Keunggulan'],
        'landing.feature2_title' => ['label' => 'Keunggulan 2 — judul', 'type' => 'string', 'max' => 60, 'default' => 'Ujian yang adil', 'group' => 'beranda', 'section' => 'Keunggulan'],
        'landing.feature2_text' => ['label' => 'Keunggulan 2 — teks', 'type' => 'text', 'max' => 200, 'default' => 'Waktu dijaga server, soal diacak per peserta, dan penilaian otomatis.', 'group' => 'beranda', 'section' => 'Keunggulan'],
        'landing.feature3_title' => ['label' => 'Keunggulan 3 — judul', 'type' => 'string', 'max' => 60, 'default' => 'Sertifikat terverifikasi', 'group' => 'beranda', 'section' => 'Keunggulan'],
        'landing.feature3_text' => ['label' => 'Keunggulan 3 — teks', 'type' => 'text', 'max' => 200, 'default' => 'PDF bertanda tangan digital dengan QR & kode verifikasi publik.', 'group' => 'beranda', 'section' => 'Keunggulan'],
        'landing.programs_eyebrow' => ['label' => 'Label kecil', 'type' => 'string', 'max' => 60, 'default' => 'Pelatihan tersedia', 'group' => 'beranda', 'section' => 'Bagian pelatihan'],
        'landing.programs_title' => ['label' => 'Judul', 'type' => 'string', 'max' => 120, 'default' => 'Pilih pelatihan, langsung bergabung', 'group' => 'beranda', 'section' => 'Bagian pelatihan'],
        'landing.steps_title' => ['label' => 'Judul', 'type' => 'string', 'max' => 60, 'default' => 'Cara bergabung', 'group' => 'beranda', 'section' => 'Cara bergabung'],
        'landing.steps_subtitle' => ['label' => 'Keterangan', 'type' => 'string', 'max' => 120, 'default' => 'Empat langkah menuju sertifikat', 'group' => 'beranda', 'section' => 'Cara bergabung'],
        'landing.step1_title' => ['label' => 'Langkah 1 — judul', 'type' => 'string', 'max' => 60, 'default' => 'Daftar akun', 'group' => 'beranda', 'section' => 'Cara bergabung'],
        'landing.step1_text' => ['label' => 'Langkah 1 — teks', 'type' => 'text', 'max' => 160, 'default' => 'Buat akun peserta gratis dan verifikasi email Anda.', 'group' => 'beranda', 'section' => 'Cara bergabung'],
        'landing.step2_title' => ['label' => 'Langkah 2 — judul', 'type' => 'string', 'max' => 60, 'default' => 'Pilih kelas', 'group' => 'beranda', 'section' => 'Cara bergabung'],
        'landing.step2_text' => ['label' => 'Langkah 2 — teks', 'type' => 'text', 'max' => 160, 'default' => 'Tentukan program & batch yang sesuai jadwal, lalu klik "Ikut Pelatihan".', 'group' => 'beranda', 'section' => 'Cara bergabung'],
        'landing.step3_title' => ['label' => 'Langkah 3 — judul', 'type' => 'string', 'max' => 60, 'default' => 'Belajar & ujian', 'group' => 'beranda', 'section' => 'Cara bergabung'],
        'landing.step3_text' => ['label' => 'Langkah 3 — teks', 'type' => 'text', 'max' => 160, 'default' => 'Ikuti materi, kerjakan kuis, dan selesaikan ujian akhir.', 'group' => 'beranda', 'section' => 'Cara bergabung'],
        'landing.step4_title' => ['label' => 'Langkah 4 — judul', 'type' => 'string', 'max' => 60, 'default' => 'Terima sertifikat', 'group' => 'beranda', 'section' => 'Cara bergabung'],
        'landing.step4_text' => ['label' => 'Langkah 4 — teks', 'type' => 'text', 'max' => 160, 'default' => 'Sertifikat digital terbit dan dapat diverifikasi publik.', 'group' => 'beranda', 'section' => 'Cara bergabung'],
        'landing.testimonials_title' => ['label' => 'Judul bagian testimoni', 'type' => 'string', 'max' => 120, 'default' => 'Apa kata peserta & mitra kami', 'group' => 'beranda', 'section' => 'Testimoni, mitra & ajakan'],
        'landing.partners_title' => ['label' => 'Judul bagian mitra', 'type' => 'string', 'max' => 120, 'default' => 'Dipercaya perusahaan & perguruan tinggi', 'group' => 'beranda', 'section' => 'Testimoni, mitra & ajakan'],
        'landing.cta_title' => ['label' => 'Judul ajakan', 'type' => 'string', 'max' => 120, 'default' => 'Siap meningkatkan kompetensi Anda?', 'group' => 'beranda', 'section' => 'Testimoni, mitra & ajakan'],
        'landing.cta_text' => ['label' => 'Teks ajakan', 'type' => 'text', 'max' => 300, 'default' => 'Daftar sekarang dan ikuti pelatihan bersertifikat — atau verifikasi keaslian sertifikat yang Anda terima.', 'group' => 'beranda', 'section' => 'Testimoni, mitra & ajakan'],

        // ---- Pendaftaran & akun --------------------------------------------------------------
        'security.registration_enabled' => ['label' => 'Registrasi mandiri peserta dibuka', 'type' => 'bool', 'config' => 'security.registration.enabled', 'group' => 'pendaftaran', 'section' => 'Pendaftaran'],
        'security.registration_otp_ttl' => ['label' => 'Masa berlaku kode OTP pendaftaran (menit)', 'type' => 'int', 'min' => 5, 'max' => 30, 'config' => 'security.registration.otp_ttl_minutes', 'group' => 'pendaftaran', 'section' => 'Pendaftaran'],
        'security.prune_unverified_days' => ['label' => 'Hapus akun yang tidak diverifikasi setelah (hari)', 'type' => 'int', 'min' => 1, 'max' => 30, 'config' => 'security.registration.prune_unverified_after_days', 'group' => 'pendaftaran', 'section' => 'Pendaftaran'],
        'security.invitation_ttl_hours' => ['label' => 'Masa berlaku tautan undangan (jam)', 'type' => 'int', 'min' => 24, 'max' => 168, 'config' => 'security.invitation.ttl_hours', 'group' => 'pendaftaran', 'section' => 'Undangan'],

        // ---- Pembelajaran & ujian -------------------------------------------------------------
        'learning.video_completion_percent' => ['label' => 'Video dianggap selesai bila ditonton (%)', 'type' => 'int', 'min' => 50, 'max' => 100, 'config' => 'lms.video_completion_percent', 'group' => 'pembelajaran', 'section' => 'Pembelajaran'],
        'exam.grace_seconds' => ['label' => 'Toleransi pengumpulan setelah waktu habis (detik)', 'type' => 'int', 'min' => 0, 'max' => 60, 'config' => 'lms.exam_grace_seconds', 'group' => 'pembelajaran', 'section' => 'Ujian', 'help' => 'Menutupi jeda jaringan saat autosave terakhir; jawaban setelah batas ini ditolak.'],
        'program.default_passing_score' => ['label' => 'Skor minimal lulus bawaan program baru', 'type' => 'int', 'min' => 0, 'max' => 100, 'config' => 'lms.default_passing_score', 'group' => 'pembelajaran', 'section' => 'Bawaan program baru'],
        'program.default_validity_months' => ['label' => 'Masa berlaku sertifikat bawaan (bulan, 0 = selamanya)', 'type' => 'int', 'min' => 0, 'max' => 240, 'config' => 'lms.default_certificate_validity_months', 'group' => 'pembelajaran', 'section' => 'Bawaan program baru'],

        // ---- Sertifikat ------------------------------------------------------------------------
        'certificate.issuer_code' => ['label' => 'Kode penerbit pada nomor sertifikat (peserta tanpa organisasi)', 'type' => 'string', 'max' => 10, 'pattern' => '/^[A-Z0-9]{2,10}$/', 'required' => true, 'config' => 'lms.certificate_issuer_code', 'group' => 'sertifikat', 'section' => 'Penomoran', 'help' => '2–10 huruf besar/angka. Contoh nomor: INT/DEMO-KI/STU/2026/00001.'],
        'certificate.expiry_reminder_days' => ['label' => 'Kirim pengingat sebelum kedaluwarsa (hari)', 'type' => 'int', 'min' => 7, 'max' => 120, 'config' => 'lms.certificate_expiry_reminder_days', 'group' => 'sertifikat', 'section' => 'Masa berlaku'],
        'certificate.disclaimer' => ['label' => 'Catatan kaki pada PDF sertifikat', 'type' => 'text', 'max' => 300, 'default' => 'Sertifikat pelatihan yang diterbitkan {penerbit}. Bukan sertifikat resmi vendor/BNSP kecuali dinyatakan lain.', 'group' => 'sertifikat', 'section' => 'Teks', 'help' => '{penerbit} diganti nama pemilik situs (tab Profil Pemilik).'],
        'certificate.verification_note' => ['label' => 'Catatan pada halaman hasil verifikasi', 'type' => 'text', 'max' => 300, 'default' => 'Sertifikat pelatihan yang diterbitkan {penerbit}. Status di halaman ini adalah sumber kebenaran.', 'group' => 'sertifikat', 'section' => 'Teks'],

        // ---- Pembayaran (transfer manual tercatat; FR-PAY tahap A) ------------------------
        'payment.bank_name' => ['label' => 'Nama bank', 'type' => 'string', 'max' => 60, 'default' => '', 'group' => 'pembayaran', 'section' => 'Rekening tujuan transfer', 'help' => 'Kosongkan bila pendaftaran program berbayar belum dibuka; peserta akan diminta menghubungi admin.'],
        'payment.bank_account_number' => ['label' => 'Nomor rekening', 'type' => 'string', 'max' => 30, 'pattern' => '/^[0-9][0-9 -]{4,28}[0-9]$/', 'default' => '', 'group' => 'pembayaran', 'section' => 'Rekening tujuan transfer'],
        'payment.bank_account_name' => ['label' => 'Atas nama', 'type' => 'string', 'max' => 100, 'default' => '', 'group' => 'pembayaran', 'section' => 'Rekening tujuan transfer'],
        'payment.instructions' => ['label' => 'Petunjuk untuk peserta', 'type' => 'text', 'max' => 1000, 'rows' => 5, 'default' => 'Transfer tepat sesuai jumlah tagihan ke rekening di atas, lalu unggah bukti transfer di halaman ini. Verifikasi dilakukan pada jam kerja, paling lambat 1×24 jam setelah bukti diterima.', 'group' => 'pembayaran', 'section' => 'Rekening tujuan transfer'],
        'payment.deadline_hours' => ['label' => 'Batas waktu pembayaran (jam)', 'type' => 'int', 'min' => 6, 'max' => 336, 'config' => 'lms.payment_deadline_hours', 'group' => 'pembayaran', 'section' => 'Aturan', 'help' => 'Tagihan tanpa bukti transfer yang melewati batas ini otomatis kedaluwarsa dan kursi dilepas.'],
        'payment.manual_settle_threshold' => ['label' => 'Ambang persetujuan admin kedua (Rp)', 'type' => 'int', 'min' => 0, 'max' => 1000000000, 'config' => 'lms.payment_manual_settle_threshold', 'group' => 'pembayaran', 'section' => 'Aturan', 'help' => 'Konfirmasi lunas dengan nilai di atas ambang ini memerlukan persetujuan admin lain (maker–checker, FR-PAY-003).'],
        'invoice.tax_id' => ['label' => 'NPWP penerbit (opsional)', 'type' => 'string', 'max' => 25, 'pattern' => '/^[0-9.\- ]{0,25}$/', 'default' => '', 'group' => 'pembayaran', 'section' => 'Invoice'],
        'invoice.footer_note' => ['label' => 'Catatan kaki invoice', 'type' => 'text', 'max' => 300, 'default' => 'Invoice ini diterbitkan secara elektronik dan sah tanpa tanda tangan.', 'group' => 'pembayaran', 'section' => 'Invoice'],
        'referral.enabled' => ['label' => 'Program referral aktif', 'type' => 'bool', 'config' => 'lms.referral_enabled', 'group' => 'pembayaran', 'section' => 'Program referral', 'help' => 'Peserta mendapat kode & tautan referral; komisi lahir saat pembayaran akun yang direferensikan dikonfirmasi lunas.'],
        'referral.commission_percent' => ['label' => 'Komisi (% dari nilai pembayaran)', 'type' => 'int', 'min' => 0, 'max' => 50, 'config' => 'lms.referral_commission_percent', 'group' => 'pembayaran', 'section' => 'Program referral'],
        'referral.max_commission' => ['label' => 'Batas komisi per transaksi (Rp; 0 = tanpa batas)', 'type' => 'int', 'min' => 0, 'max' => 100000000, 'config' => 'lms.referral_max_commission', 'group' => 'pembayaran', 'section' => 'Program referral'],
        'referral.validity_months' => ['label' => 'Masa berlaku referral sejak registrasi (bulan)', 'type' => 'int', 'min' => 1, 'max' => 36, 'config' => 'lms.referral_validity_months', 'group' => 'pembayaran', 'section' => 'Program referral'],
        'referral.terms' => ['label' => 'Ketentuan program referral (tampil di halaman peserta)', 'type' => 'text', 'max' => 1000, 'rows' => 4, 'default' => 'Komisi diberikan untuk setiap pembayaran pelatihan yang dikonfirmasi lunas dari peserta yang mendaftar memakai kode Anda. Komisi dicairkan ke rekening yang Anda daftarkan setelah diverifikasi Admin Keuangan. Penyalahgunaan (akun ganda, referral diri sendiri) membatalkan komisi.', 'group' => 'pembayaran', 'section' => 'Program referral'],
        // ---- Dokumen hukum (Markdown; mengubah versi → pengguna diminta menyetujui ulang) ----
        'legal.terms_version' => ['label' => 'Versi Syarat & Ketentuan', 'type' => 'string', 'max' => 30, 'pattern' => '/^[0-9]{4}-[0-9]{2}[A-Za-z0-9.-]{0,20}$/', 'required' => true, 'config' => 'legal.terms_version', 'group' => 'legal', 'section' => 'Syarat & Ketentuan', 'help' => 'Format TAHUN-BULAN, mis. 2026-10 atau 2026-10-rev1. Mengubah versi meminta semua pengguna menyetujui ulang saat masuk.'],
        'legal.terms_body' => ['label' => 'Isi (Markdown)', 'type' => 'text', 'max' => 30000, 'rows' => 14, 'default' => "Akun bersifat pribadi dan tidak boleh dipinjamkan. Anda bertanggung jawab menjaga kerahasiaan kata sandi dan kode autentikasi.\n\nPengerjaan kuis dan ujian wajib dilakukan sendiri. Kecurangan dapat mengakibatkan pembatalan kelulusan dan pencabutan sertifikat.\n\nSertifikat diterbitkan setelah syarat kelulusan terpenuhi dan disetujui penyelenggara, serta dapat diverifikasi publik.\n\nMateri pelatihan dilindungi hak cipta dan hanya untuk penggunaan pribadi peserta terdaftar.", 'required' => true, 'group' => 'legal', 'section' => 'Syarat & Ketentuan'],
        'legal.privacy_version' => ['label' => 'Versi Kebijakan Privasi', 'type' => 'string', 'max' => 30, 'pattern' => '/^[0-9]{4}-[0-9]{2}[A-Za-z0-9.-]{0,20}$/', 'required' => true, 'config' => 'legal.privacy_version', 'group' => 'legal', 'section' => 'Kebijakan Privasi'],
        'legal.privacy_body' => ['label' => 'Isi (Markdown)', 'type' => 'text', 'max' => 30000, 'rows' => 14, 'default' => "Data yang kami proses: nama, email, nomor HP (opsional), organisasi, aktivitas belajar, nilai, dan data teknis keamanan (alamat IP, perangkat) — hanya untuk menyelenggarakan pelatihan, sertifikasi, dan menjaga keamanan akun.\n\nOrganisasi tempat Anda terdaftar dapat melihat progres dan nilai pelatihan yang Anda ikuti melalui organisasi tersebut.\n\nData tidak dijual kepada pihak mana pun. Pemroses pihak ketiga (email, pembayaran) terikat perjanjian pemrosesan data.\n\nAnda berhak mengakses, memperbaiki, dan meminta penghapusan data pribadi sesuai UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi, dengan pengecualian data sertifikat yang wajib disimpan untuk verifikasi.", 'required' => true, 'group' => 'legal', 'section' => 'Kebijakan Privasi'],

        // ---- Keamanan --------------------------------------------------------------------------
        'security.session_idle_privileged' => ['label' => 'Batas idle sesi admin/trainer (menit)', 'type' => 'int', 'min' => 5, 'max' => 30, 'config' => 'security.session.idle_minutes.privileged', 'group' => 'keamanan', 'section' => 'Sesi'],
        'security.session_idle_participant' => ['label' => 'Batas idle sesi peserta (menit)', 'type' => 'int', 'min' => 15, 'max' => 120, 'config' => 'security.session.idle_minutes.participant', 'group' => 'keamanan', 'section' => 'Sesi'],
        'security.password_min_privileged' => ['label' => 'Panjang minimal kata sandi admin/trainer', 'type' => 'int', 'min' => 12, 'max' => 64, 'config' => 'security.password.min_privileged', 'group' => 'keamanan', 'section' => 'Kata sandi'],
        'security.password_min_participant' => ['label' => 'Panjang minimal kata sandi peserta', 'type' => 'int', 'min' => 8, 'max' => 64, 'config' => 'security.password.min_participant', 'group' => 'keamanan', 'section' => 'Kata sandi'],

        // ---- Integrasi (kanal notifikasi eksternal) --------------------------------------------
        'push.enabled' => ['label' => 'Notifikasi push peramban (Web Push) aktif', 'type' => 'bool', 'default' => false, 'group' => 'integrasi', 'section' => 'Web Push', 'help' => 'Perlu kunci VAPID (tombol "Buat kunci VAPID" di bawah). Pengguna mengaktifkannya sendiri di Notifikasi → Preferensi.'],
        'push.subject' => ['label' => 'Kontak VAPID (mailto: atau https://)', 'type' => 'string', 'max' => 120, 'pattern' => '/^(mailto:[^\\s@]+@[^\\s@]+|https:\\/\\/[^\\s]+)$/', 'default' => '', 'group' => 'integrasi', 'section' => 'Web Push', 'help' => 'Dikirim ke layanan push peramban sebagai identitas pengirim. Kosong = email pemilik situs.'],
        'push.vapid_public' => ['label' => 'Kunci publik VAPID', 'type' => 'string', 'max' => 120, 'pattern' => '/^[A-Za-z0-9_-]{80,120}$/', 'default' => '', 'group' => 'integrasi', 'section' => 'Web Push'],
        'push.vapid_private' => ['label' => 'Kunci privat VAPID', 'type' => 'secret', 'max' => 400, 'default' => '', 'group' => 'integrasi', 'section' => 'Web Push', 'help' => 'Disimpan terenkripsi. Kosongkan untuk mempertahankan nilai tersimpan.'],
        'whatsapp.enabled' => ['label' => 'Kirim notifikasi lewat WhatsApp gateway', 'type' => 'bool', 'default' => false, 'group' => 'integrasi', 'section' => 'WhatsApp Gateway', 'help' => 'Hanya untuk pengguna yang mengisi nomor HP dan mengaktifkan kanal WhatsApp di preferensinya.'],
        'whatsapp.endpoint' => ['label' => 'URL endpoint gateway (HTTPS)', 'type' => 'string', 'max' => 300, 'pattern' => '/^https:\\/\\/[^\\s]+$/', 'default' => '', 'group' => 'integrasi', 'section' => 'WhatsApp Gateway', 'help' => 'Gateway generik: permintaan POST berisi nomor tujuan dan pesan (mis. WAHA, Fonnte, wa-gateway sendiri).'],
        'whatsapp.token' => ['label' => 'Token/API key gateway', 'type' => 'secret', 'max' => 400, 'default' => '', 'group' => 'integrasi', 'section' => 'WhatsApp Gateway', 'help' => 'Dikirim sebagai header Authorization: Bearer. Disimpan terenkripsi.'],
        'whatsapp.payload' => ['label' => 'Format payload', 'type' => 'select', 'options' => ['json' => 'JSON {"to","message","sender"}', 'form' => 'Form-urlencoded (to, message, sender)'], 'default' => 'json', 'group' => 'integrasi', 'section' => 'WhatsApp Gateway'],
        'whatsapp.sender' => ['label' => 'ID pengirim/sesi (opsional)', 'type' => 'string', 'max' => 80, 'default' => '', 'group' => 'integrasi', 'section' => 'WhatsApp Gateway', 'help' => 'Diteruskan sebagai field "sender" bila gateway memerlukan nama sesi/perangkat.'],
        'reminder.deadline_hours' => ['label' => 'Pengingat tenggat tugas/sesi (jam sebelum)', 'type' => 'int', 'min' => 6, 'max' => 168, 'default' => 48, 'group' => 'integrasi', 'section' => 'Pengingat otomatis'],
        'reminder.inactive_days' => ['label' => 'Pengingat peserta tidak aktif setelah (hari)', 'type' => 'int', 'min' => 3, 'max' => 60, 'default' => 7, 'group' => 'integrasi', 'section' => 'Pengingat otomatis', 'help' => 'Dikirim sekali per pekan selama peserta tetap tidak aktif.'],
        'reminder.new_program' => ['label' => 'Beri tahu peserta saat program baru terbit', 'type' => 'bool', 'default' => true, 'group' => 'integrasi', 'section' => 'Pengingat otomatis'],
        'ai.enabled' => ['label' => 'Asisten AI (Claude) aktif', 'type' => 'bool', 'default' => false, 'group' => 'integrasi', 'section' => 'Asisten AI', 'help' => 'Tutor materi, rangkuman, rekomendasi & jalur belajar (peserta); generator soal, saran penilaian esai, analisis kelas & rancangan kurikulum (trainer/admin).'],
        'ai.api_key' => ['label' => 'Claude API key', 'type' => 'secret', 'max' => 400, 'default' => '', 'group' => 'integrasi', 'section' => 'Asisten AI', 'help' => 'Dari console.anthropic.com. Disimpan terenkripsi; kosongkan untuk mempertahankan.'],
        'ai.model' => ['label' => 'Model', 'type' => 'select', 'options' => ['claude-sonnet-5' => 'Claude Sonnet 5 (seimbang)', 'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 (cepat & hemat)', 'claude-opus-5-5' => 'Claude Opus 5.5 (paling mampu)'], 'default' => 'claude-sonnet-5', 'group' => 'integrasi', 'section' => 'Asisten AI'],
        'ai.daily_limit' => ['label' => 'Batas permintaan AI per pengguna per hari', 'type' => 'int', 'min' => 1, 'max' => 1000, 'default' => 40, 'group' => 'integrasi', 'section' => 'Asisten AI'],
        'ai.tutor_enabled' => ['label' => 'Tutor AI di halaman materi peserta', 'type' => 'bool', 'default' => true, 'group' => 'integrasi', 'section' => 'Asisten AI'],
    ];

    /** @var array<string, mixed>|null nilai tersimpan per permintaan */
    private static ?array $memo = null;

    /** @return array<string, mixed> nilai tersimpan (mentah) */
    public static function all(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }
        try {
            /** @var array<string, mixed> $values */
            $values = Cache::rememberForever(self::CACHE_KEY, fn (): array => Schema::hasTable('system_settings')
                ? DB::table('system_settings')->pluck('value', 'key')->map(fn ($value) => json_decode((string) $value, true))->all()
                : []);
        } catch (Throwable) {
            $values = [];
        }

        return self::$memo = $values;
    }

    /** Nilai efektif: tersimpan (bila valid) → konfigurasi dasar → bawaan definisi. */
    public static function get(string $key): mixed
    {
        $definition = self::definition($key);
        $stored = self::all()[$key] ?? null;
        if ($definition['type'] === 'secret') {
            return is_string($stored) && $stored !== '' ? self::reveal($stored) : '';
        }
        if ($stored !== null && self::withinBounds($definition, $stored) && ! (($definition['required'] ?? false) && $stored === '')) {
            return $stored;
        }
        if (isset($definition['config'])) {
            return config($definition['config']);
        }

        return $definition['default'] ?? ($definition['type'] === 'bool' ? false : '');
    }

    /**
     * @return array<string, mixed> definisi satu kunci
     *
     * @throws \InvalidArgumentException
     */
    private static function definition(string $key): array
    {
        if (! array_key_exists($key, self::DEFINITIONS)) {
            throw new \InvalidArgumentException("Pengaturan tidak dikenal: {$key}");
        }

        return self::DEFINITIONS[$key];
    }

    /** Teks dengan placeholder {penerbit} diganti nama pemilik situs. */
    public static function text(string $key, string $issuer): string
    {
        return str_replace('{penerbit}', $issuer, (string) self::get($key));
    }

    /** Terapkan nilai tersimpan ke konfigurasi runtime (dipanggil saat boot). */
    public static function applyToConfig(): void
    {
        self::$memo = null;
        foreach (self::all() as $key => $value) {
            $definition = self::DEFINITIONS[$key] ?? null;
            if ($definition !== null && isset($definition['config']) && $value !== '' && self::withinBounds($definition, $value)) {
                config([$definition['config'] => $value]);
            }
        }
    }

    /** @return array<string, array<string, mixed>> definisi satu tab */
    public static function forTab(string $tab): array
    {
        return array_filter(self::DEFINITIONS, fn (array $definition): bool => $definition['group'] === $tab);
    }

    /**
     * Simpan nilai satu tab. Hanya kunci tab tersebut yang disentuh.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function update(string $tab, array $input, User $actor, AuditLogger $audit): void
    {
        $current = self::all();
        $changes = [];
        $errors = [];
        foreach (self::forTab($tab) as $key => $definition) {
            $field = self::field($key);
            $value = match ($definition['type']) {
                'bool' => (bool) ($input[$field] ?? false),
                'int' => filter_var($input[$field] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
                default => str_replace("\r\n", "\n", trim((string) ($input[$field] ?? ''))),
            };
            if ($definition['type'] === 'secret') {
                if ($value === '') {
                    continue; // kosong = pertahankan nilai tersimpan
                }
                if (mb_strlen($value) > ($definition['max'] ?? 400)) {
                    $errors[$field] = self::boundsMessage($definition);

                    continue;
                }
                $stored = $current[$key] ?? null;
                if (! is_string($stored) || self::reveal($stored) !== $value) {
                    $changes[$key] = ['from' => is_string($stored) && $stored !== '' ? '••••' : '', 'to' => '••••', 'store' => Crypt::encryptString($value)];
                }

                continue;
            }
            if ($value === null || ! self::withinBounds($definition, $value)) {
                $errors[$field] = self::boundsMessage($definition);

                continue;
            }
            if (($definition['required'] ?? false) && $value === '') {
                $errors[$field] = $definition['label'].' wajib diisi.';

                continue;
            }
            $old = $current[$key] ?? self::get($key);
            if ($old !== $value) {
                $changes[$key] = ['from' => $old, 'to' => $value];
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        if ($changes === []) {
            return;
        }

        DB::transaction(function () use ($changes, $actor, $audit, $tab): void {
            foreach ($changes as $key => $change) {
                DB::table('system_settings')->upsert([[
                    'key' => $key, 'value' => json_encode($change['store'] ?? $change['to']), 'updated_by' => $actor->id, 'updated_at' => now(),
                ]], ['key'], ['value', 'updated_by', 'updated_at']);
            }
            $audit->record('system_setting.updated', $actor, 'system_setting', null, ['tab' => $tab] + array_map(fn (array $c) => ['from' => $c['from'], 'to' => $c['to']], $changes));
        });
        Cache::forget(self::CACHE_KEY);
        self::applyToConfig();
    }

    /**
     * Simpan beberapa kunci secara programatik (mis. kunci VAPID yang dibangkitkan). Tipe `secret`
     * dienkripsi; nilai lain harus dalam batas definisi.
     *
     * @param  array<string, string|int|bool>  $values
     */
    public function put(array $values, User $actor, AuditLogger $audit, string $reason): void
    {
        $audited = [];
        DB::transaction(function () use ($values, $actor, &$audited): void {
            foreach ($values as $key => $value) {
                $definition = self::definition($key);
                if ($definition['type'] === 'secret') {
                    $store = is_string($value) && $value !== '' ? Crypt::encryptString($value) : '';
                    $audited[$key] = '••••';
                } elseif (self::withinBounds($definition, $value)) {
                    $store = $value;
                    $audited[$key] = $value;
                } else {
                    throw new \InvalidArgumentException("Nilai pengaturan {$key} di luar batas.");
                }
                DB::table('system_settings')->upsert([[
                    'key' => $key, 'value' => json_encode($store), 'updated_by' => $actor->id, 'updated_at' => now(),
                ]], ['key'], ['value', 'updated_by', 'updated_at']);
            }
        });
        $audit->record('system_setting.updated', $actor, 'system_setting', null, ['reason' => $reason, 'keys' => $audited]);
        Cache::forget(self::CACHE_KEY);
        self::applyToConfig();
    }

    private static function reveal(string $stored): string
    {
        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            return '';
        }
    }

    public static function field(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    /** @param array<string, mixed> $definition */
    private static function boundsMessage(array $definition): string
    {
        return match ($definition['type']) {
            'int' => $definition['label'].' harus angka '.($definition['min'] ?? '').'–'.($definition['max'] ?? '').' (batas aman).',
            'email' => 'Format email tidak valid.',
            'select' => 'Pilihan tidak valid.',
            default => isset($definition['pattern']) ? 'Format tidak sesuai ketentuan.' : 'Maksimal '.($definition['max'] ?? 255).' karakter.',
        };
    }

    /** @param array<string, mixed> $definition */
    private static function withinBounds(array $definition, mixed $value): bool
    {
        return match ($definition['type']) {
            'int' => is_int($value) && $value >= ($definition['min'] ?? PHP_INT_MIN) && $value <= ($definition['max'] ?? PHP_INT_MAX),
            'bool' => is_bool($value),
            'email' => is_string($value) && ($value === '' || filter_var($value, FILTER_VALIDATE_EMAIL) !== false),
            'select' => is_string($value) && array_key_exists($value, $definition['options'] ?? []),
            default => is_string($value) && mb_strlen($value) <= ($definition['max'] ?? 255)
                && ($value === '' || ! isset($definition['pattern']) || preg_match($definition['pattern'], $value) === 1)
                && ($definition['type'] === 'text' || ! str_contains($value, "\n")),
        };
    }
}
