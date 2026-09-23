# 01 — Visi, Tujuan & Ruang Lingkup Produk (PRD Ringkas)

> Status: **Disetujui untuk development** · Versi 1.0 · Pemilik: Product Owner
>
> Dokumen ini menjelaskan *mengapa* dan *apa* yang dibangun. Rincian kebutuhan ada di
> [`02-kebutuhan-fungsional.md`](02-kebutuhan-fungsional.md) dan
> [`03-kebutuhan-non-fungsional.md`](03-kebutuhan-non-fungsional.md).

## 1. Latar Belakang

Semesta Teknologi Utama (STU) menyelenggarakan pelatihan sertifikasi **internasional** (AWS,
Microsoft Azure, Cisco CCNA, PMI CAPM, dsb.) dan **skema kompetensi BNSP** (Junior Web Developer,
Digital Marketing, Ahli K3 Umum, Junior Network Administrator, dsb.) untuk:

- **Institusi pendidikan** (universitas, politeknik) — pelatihan bagi mahasiswa, umumnya gratis
  karena ditanggung institusi;
- **Korporat** — pelatihan karyawan yang didaftarkan massal oleh HR;
- **Peserta mandiri** — membayar sendiri untuk program berbayar.

Saat ini proses pendaftaran, distribusi materi, penilaian, dan penerbitan sertifikat masih
tersebar (formulir, grup chat, spreadsheet, sertifikat dibuat manual). Akibatnya: data tidak
konsisten, sulit memantau progres, sertifikat rawan dipalsukan, dan laporan ke mitra lambat.

Purwarupa UI/UX (HTML statis di repositori ini) telah memvalidasi alur untuk tiga peran utama
(Peserta, Trainer, Admin) dan 42 modul fitur. Tahap berikutnya adalah membangun **aplikasi
produksi** yang aman, andal, dan patuh regulasi.

## 2. Visi Produk

> *"Satu platform tepercaya untuk belajar, dinilai, dan membuktikan kompetensi — dengan
> sertifikat yang dapat diverifikasi siapa pun, kapan pun."*

## 3. Tujuan Bisnis & Indikator Keberhasilan

| # | Tujuan | KPI | Target 12 bulan setelah go-live |
|---|---|---|---|
| G1 | Mendigitalkan seluruh siklus pelatihan | % kelas yang dijalankan penuh di LMS | ≥ 90% |
| G2 | Meningkatkan tingkat penyelesaian | *Completion rate* peserta | ≥ 70% |
| G3 | Sertifikat tepercaya & anti-pemalsuan | Kasus pemalsuan sertifikat yang lolos verifikasi | 0 |
| G4 | Mempercepat penerbitan sertifikat | Median waktu lulus → sertifikat terbit | ≤ 2 hari kerja |
| G5 | Melayani korporat skala besar | Organisasi korporat aktif | ≥ 10 |
| G6 | Pendapatan program berbayar | Transaksi berhasil / bulan | ditetapkan bisnis |
| G7 | Keamanan & privasi | Insiden kebocoran data pribadi | 0; temuan pentest Critical/High terbuka saat rilis = 0 |
| G8 | Kepuasan pengguna | CSAT / NPS peserta | CSAT ≥ 4,2/5 |

## 4. Pemangku Kepentingan

| Pihak | Kepentingan |
|---|---|
| Manajemen STU (sponsor) | Pendapatan, reputasi, skalabilitas bisnis |
| Product Owner | Prioritas fitur & penerimaan |
| Tim Akademik STU | Kurikulum, kualitas penilaian, penerbitan sertifikat |
| Tim Keuangan STU | Transaksi, rekonsiliasi, refund, faktur |
| Security Lead / DPO (Pejabat Pelindungan Data Pribadi) | Keamanan informasi, kepatuhan UU PDP |
| Institusi mitra | Laporan progres mahasiswa, kemudahan pendaftaran |
| Korporat mitra (HR/L&D) | Pendaftaran massal, laporan tim, integrasi HRIS |
| LSP / penyelenggara sertifikasi | Keselarasan skema & integritas asesmen |
| Peserta | Pengalaman belajar, sertifikat sah |
| Trainer | Kemudahan kelola kelas & penilaian |
| Verifikator publik (HRD perusahaan lain) | Verifikasi sertifikat cepat & tepercaya |

## 5. Persona

| Persona | Deskripsi | Kebutuhan utama | Kekhawatiran |
|---|---|---|---|
| **Raka — Peserta mahasiswa** | Mahasiswa TI semester 7, akses dari ponsel & laptop | Materi runtut, bisa belajar mandiri, sertifikat untuk melamar kerja | Kuota data, ujian gagal karena koneksi putus |
| **Wahyu — Peserta karyawan** | Staf gudang, didaftarkan perusahaan ke pelatihan K3 | Jadwal jelas, presensi mudah, materi sederhana | Tidak familiar teknologi |
| **Nadia — Peserta mandiri berbayar** | Fresh graduate membayar program CAPM | Pembayaran mudah & aman, invoice, refund jelas | Penipuan pembayaran |
| **Dr. Andi — Trainer** | Mengampu 2 kelas cloud | Kelola materi & soal cepat, lihat progres, nilai tugas | Kebocoran soal ujian |
| **Hendra — Admin Organisasi (HR korporat)** | Mengelola 50+ karyawan peserta | Bulk enroll, laporan progres tim, integrasi HRIS | Data karyawan bocor ke pihak lain |
| **Dewi — Admin Akademik / Super Admin** | Operator platform | Approval sertifikat, master data, laporan lintas organisasi | Kesalahan penerbitan, akun admin dibobol |
| **Rina — Verifikator HRD eksternal** | Rekruter yang menerima CV berisi sertifikat | Verifikasi instan tanpa akun | Sertifikat palsu |

## 6. Ruang Lingkup

### 6.1 Termasuk (In Scope) — Rilis 1.0

Dikelompokkan per modul (kode modul dipakai sebagai prefiks kebutuhan `FR-{MODUL}-NNN`):

| Kode | Modul | Ringkasan |
|---|---|---|
| AUTH | Autentikasi & Akun | Registrasi + OTP, login, MFA (TOTP/WebAuthn), lupa kata sandi, manajemen sesi & perangkat, SSO Google (opsional per organisasi) |
| USER | Manajemen Pengguna | CRUD pengguna, penetapan peran, aktivasi/nonaktif, impor massal |
| ORG | Organisasi | Institusi & korporat, unit/departemen, PIC, verifikasi domain email |
| CAT | Katalog Program | Program pelatihan internasional & BNSP, lifecycle draft→review→published→archived, harga |
| CLS | Kelas & Jadwal | Batch, kuota, mode (online/offline/hybrid), trainer pengampu |
| CNT | Konten Pembelajaran | Struktur modul→bab→lesson, video (HLS), PDF, teks, tautan; progres |
| ASM | Asesmen | Bank soal, kuis per modul, ujian akhir, pengacakan, batas waktu & kesempatan, penilaian otomatis |
| ENR | Enrollment | Daftar mandiri, enrollment oleh admin, bulk enroll korporat, status & progres, syarat kelulusan |
| ASG | Tugas | Pembuatan tugas, unggah berkas, penilaian, revisi |
| ATT | Presensi | Sesi presensi, check-in QR dinamis, presensi manual, rekap |
| LIVE | Live Class | Jadwal sesi Zoom/Meet/Teams, tautan, rekaman |
| CERT | Sertifikat | Template, approval, penerbitan PDF bertanda tangan digital, QR, unduh, pencabutan, verifikasi publik & API |
| PAY | Pembayaran | Checkout Midtrans (VA, QRIS, e-wallet), kupon, invoice, refund, rekonsiliasi |
| GAM | Gamifikasi | Poin, lencana, leaderboard (opsional per organisasi, dengan kontrol privasi) |
| DSC | Diskusi | Forum per kelas, balasan, suka, laporkan, moderasi |
| NTF | Notifikasi | In-app, email, WhatsApp; preferensi pengguna |
| RPT | Laporan & Dashboard | Dashboard per peran, laporan per organisasi/program, operasional, ekspor CSV/XLSX |
| API | API & Integrasi | API verifikasi sertifikat, API mitra (HRIS) dengan API key ber-scope, webhook keluar |
| CMS | Konten Beranda | Hero, FAQ, testimoni, halaman kebijakan |
| PRV | Privasi Data | Consent, permintaan akses/ekspor/koreksi/hapus, retensi |
| AUD | Audit Trail | Jejak audit tamper-evident, log keamanan |
| SET | Pengaturan Sistem | Branding, kebijakan keamanan, notifikasi, integrasi |

### 6.2 Tidak Termasuk (Out of Scope) Rilis 1.0

| Item | Alasan / rencana |
|---|---|
| Aplikasi mobile native (Android/iOS) | Web responsif dulu; API disiapkan untuk rilis berikutnya. |
| Proctoring berbasis kamera/AI | Isu privasi (data biometrik = data pribadi spesifik) & biaya; evaluasi di fase lanjut dengan DPIA. |
| Integrasi resmi ke sistem BNSP/LSP & vendor sertifikasi internasional (Pearson VUE, dsb.) | Bergantung kerja sama; rilis 1.0 menerbitkan **sertifikat pelatihan STU**, bukan sertifikat resmi vendor/BNSP. |
| Marketplace trainer eksternal & bagi hasil | Model bisnis belum final. |
| SCORM/xAPI/LTI | Dipertimbangkan rilis 2.0. |
| Multi-bahasa (EN) | Struktur i18n disiapkan; konten Bahasa Indonesia dulu. |
| Tanda tangan elektronik tersertifikasi per peserta | Hanya platform yang menandatangani sertifikat. |
| Kartu kredit langsung (non-hosted) | Tidak akan pernah — hanya via hosted payment page. |

> **Penting (kepatuhan & reputasi):** Sertifikat yang diterbitkan platform harus dengan jelas
> menyatakan dirinya sebagai **sertifikat pelatihan dari STU** dan tidak boleh dirancang
> menyerupai sertifikat resmi AWS/Microsoft/Cisco/PMI/BNSP kecuali ada perjanjian tertulis dan
> izin penggunaan merek. Template default pada purwarupa ("SERTIFIKAT KOMPETENSI BNSP") harus
> ditinjau tim legal sebelum dipakai.

## 7. Asumsi

1. Tim pengembang terdiri atas ± 1 Tech Lead, 3 backend/full-stack, 1 frontend, 1 QA, 1 DevOps
   (paruh waktu), 1 Product Designer, dan Security Lead (paruh waktu/konsultan).
2. Anggaran cloud mencakup DB terkelola, object storage, CDN/WAF, dan layanan email.
3. Akun merchant Midtrans (atau alternatif) dapat diperoleh sebelum Fase 2.
4. Sertifikat penandatangan dokumen (PSrE atau sertifikat organisasi) dapat diperoleh sebelum
   penerbitan sertifikat produksi.
5. Organisasi mitra bersedia menandatangani perjanjian pemrosesan data (DPA) bila STU bertindak
   sebagai prosesor atas data anggota mereka.

## 8. Batasan (Constraints)

- **Regulasi:** UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi (UU PDP), UU ITE beserta
  perubahannya, PP No. 71 Tahun 2019 tentang PSTE, regulasi pendaftaran PSE Lingkup Privat, dan
  ketentuan perpajakan faktur.
- **Keamanan:** memenuhi OWASP ASVS v5.0 **Level 2** untuk seluruh aplikasi, dan kontrol Level 3
  terpilih untuk autentikasi admin, sertifikat, dan pembayaran (lihat
  [`keamanan/README.md`](keamanan/README.md)).
- **Teknologi:** mengikuti [`04-arsitektur-sistem.md`](04-arsitektur-sistem.md).
- **Bahasa UI:** Bahasa Indonesia.
- **Aksesibilitas:** WCAG 2.1 AA.

## 9. Risiko Utama Produk

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Kebocoran soal ujian | Integritas sertifikat turun | Bank soal besar, pengacakan, tidak ada kunci di klien, izin `assessment.view_answer_key` terbatas |
| Pemalsuan sertifikat | Reputasi | Tanda tangan digital + QR kode acak + verifikasi publik |
| Akun admin dibobol | Penerbitan sertifikat palsu, kebocoran massal | MFA wajib, SoD, maker–checker, alert |
| Kebocoran data lintas organisasi | Pelanggaran UU PDP, kehilangan mitra | Isolasi tenant berlapis + uji otomatis |
| Kegagalan pembayaran/rekonsiliasi | Kerugian finansial | Webhook terverifikasi, rekonsiliasi terjadwal |
| Adopsi rendah oleh peserta non-teknis | KPI completion | UX sederhana, dukungan WhatsApp, panduan |

Register risiko lengkap: [`12-rencana-proyek-dan-roadmap.md`](12-rencana-proyek-dan-roadmap.md).

## 10. Kriteria Rilis 1.0 (Go-Live)

1. Seluruh kebutuhan **Must** (lihat dokumen 02) lulus UAT.
2. Checklist keamanan rilis ([`keamanan/17-checklist-keamanan.md`](keamanan/17-checklist-keamanan.md)) terpenuhi 100%.
3. Pentest eksternal selesai; tidak ada temuan Critical/High yang terbuka.
4. NFR performa & ketersediaan tercapai pada uji beban staging.
5. Backup & restore teruji; runbook insiden tersedia; on-call terjadwal.
6. Kebijakan Privasi & Syarat Ketentuan final disetujui legal; DPO ditunjuk.
