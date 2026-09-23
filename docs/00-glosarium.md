# 00 — Glosarium & Konvensi Istilah

> Status: **Disetujui untuk development** · Versi 1.0 · Pemilik: Product Owner + Tech Lead
>
> Semua dokumen di folder `docs/` memakai istilah di bawah ini. Bila ada istilah baru, tambahkan
> di sini terlebih dahulu sebelum dipakai di kode, basis data, atau UI.

## 1. Istilah Domain

| Istilah (UI, Bahasa Indonesia) | Istilah di kode & basis data | Definisi |
|---|---|---|
| Platform / STU LMS | `platform` | Sistem Learning Management System milik Semesta Teknologi Utama secara keseluruhan. |
| Organisasi | `organization` | Entitas pemilik peserta: **institusi** (universitas, politeknik, sekolah) atau **korporat** (perusahaan). Menjadi batas *tenant* data. |
| Tipe Organisasi | `organization.type` | `institution` atau `corporate`. |
| Peserta | `participant` (pengguna dengan peran `participant`) | Orang yang mengikuti pelatihan. Dulu disebut "mahasiswa" pada purwarupa awal. |
| Trainer / Instruktur | `trainer` | Pengajar yang mengelola materi, tugas, presensi, live class, dan menilai peserta pada kelas yang diampu. |
| Admin Organisasi | `org_admin` | Pengelola di sisi organisasi (mis. PIC HR korporat, admin kampus) yang hanya dapat melihat/mengelola data organisasinya sendiri. |
| Admin Akademik | `academic_admin` | Operator platform yang mengelola program pelatihan, kelas, enrollment, dan menyetujui penerbitan sertifikat. |
| Admin Keuangan | `finance_admin` | Operator platform yang mengelola transaksi, kupon, refund, dan rekonsiliasi pembayaran. |
| Admin Layanan | `support_admin` | Operator dukungan (opsional): baca data pengguna tersamar & reset MFA dengan verifikasi identitas + persetujuan kedua. |
| Super Admin | `super_admin` | Pemegang kendali penuh platform (pengaturan sistem, peran, API key, integrasi). Jumlahnya dibatasi (maks. 3 akun). |
| Program Pelatihan / Skema | `program` | Katalog pelatihan sertifikasi (mis. *AWS Certified Cloud Practitioner*, *Junior Web Developer BNSP*). Punya kategori, level, skor minimal, harga. |
| Kategori Program | `program.category` | `international` (sertifikasi internasional) atau `bnsp` (skema kompetensi BNSP/LSP). |
| Kelas / Batch | `course_class` | Penyelenggaraan konkret sebuah program pada periode tertentu, punya trainer, kuota, jadwal, mode. |
| Mode Kelas | `course_class.mode` | `online`, `offline`, `hybrid`. |
| Modul | `module` | Kelompok materi level 1 di dalam kelas. |
| Bab | `chapter` | Kelompok materi level 2 di dalam modul. |
| Materi / Lesson | `lesson` | Unit belajar terkecil: `video`, `pdf`, `text`, `quiz`, `link`. |
| Kuis | `quiz` | Evaluasi formatif per modul/bab. |
| Ujian Akhir | `final_exam` | Evaluasi sumatif penentu kelulusan kelas. |
| Bank Soal | `question_bank` | Kumpulan soal yang bisa dipakai ulang lintas kuis/ujian. |
| Percobaan Ujian | `exam_attempt` | Satu sesi pengerjaan kuis/ujian oleh peserta. |
| Pendaftaran / Enrollment | `enrollment` | Relasi peserta ↔ kelas beserta status & progresnya. |
| Tugas | `assignment` | Penugasan dengan tenggat dan unggahan berkas. |
| Pengumpulan Tugas | `submission` | Berkas/jawaban tugas yang dikirim peserta. |
| Sesi Presensi | `attendance_session` | Pertemuan (offline/hybrid/live) yang dicatat kehadirannya. |
| Presensi | `attendance` | Catatan kehadiran peserta pada satu sesi. |
| Live Class | `live_session` | Sesi daring sinkron via Zoom / Google Meet / MS Teams. |
| Sertifikat | `certificate` | Dokumen kelulusan resmi yang diterbitkan platform, dapat diverifikasi publik. |
| Nomor Sertifikat | `certificate.number` | Nomor terbaca manusia, format `{KAT}/{KODE}/{ORG}/{TAHUN}/{URUT}`. |
| Kode Verifikasi | `certificate.verification_code` | Kode acak 12 karakter (Crockford Base32) yang tertanam di QR, **tidak dapat ditebak**. |
| Template Sertifikat | `certificate_template` | Desain tata letak sertifikat berversi. |
| Pencabutan | `revocation` | Tindakan membatalkan keabsahan sertifikat beserta alasannya. |
| Transaksi | `payment_transaction` | Tagihan & pembayaran untuk program berbayar. |
| Kupon | `coupon` | Kode diskon persentase atau nominal dengan kuota & masa berlaku. |
| Lencana | `badge` | Penghargaan gamifikasi. |
| Poin | `point_ledger` | Buku besar poin gamifikasi (append-only). |
| Diskusi | `discussion_thread` / `discussion_comment` | Forum per kelas. |
| Notifikasi | `notification` | Pesan in-app / email / WhatsApp kepada pengguna. |
| Permintaan Privasi | `privacy_request` | Permintaan subjek data (akses/ekspor, koreksi, penghapusan) sesuai UU PDP. |
| Jejak Audit | `audit_log` | Catatan tidak dapat diubah atas aksi penting. |
| Kunci API | `api_key` | Kredensial mitra untuk memanggil API (mis. HRIS korporat). |
| Konten CMS | `cms_content` | Konten halaman beranda yang dapat diubah admin. |

## 2. Istilah Keamanan & Teknis

| Istilah | Definisi |
|---|---|
| **Tenant** | Batas isolasi data = satu Organisasi. Data peserta satu organisasi tidak boleh terlihat oleh admin organisasi lain. |
| **RBAC** | *Role-Based Access Control* — hak akses berdasarkan peran + izin granular (permission). |
| **ABAC / Policy** | Pengecekan kepemilikan/kontekstual (mis. "trainer hanya pada kelas yang diampu"). |
| **IDOR** | *Insecure Direct Object Reference* — akses objek milik orang lain dengan mengganti ID. |
| **MFA / 2FA** | Autentikasi multi-faktor. Di platform ini: TOTP (aplikasi authenticator) dan/atau WebAuthn/Passkey. |
| **TOTP** | *Time-based One-Time Password* (RFC 6238). |
| **OTP** | Kode sekali pakai untuk verifikasi email/nomor HP. |
| **CSRF** | *Cross-Site Request Forgery*. |
| **XSS** | *Cross-Site Scripting*. |
| **CSP** | *Content Security Policy* (header HTTP). |
| **HSTS** | *HTTP Strict Transport Security*. |
| **PII / Data Pribadi** | Data yang mengidentifikasi orang (nama, email, NIK, nomor HP, nomor induk, dsb.). |
| **Data Pribadi Spesifik** | Kategori khusus menurut UU PDP Pasal 4 ayat (2) (mis. data kesehatan, biometrik, keuangan pribadi, data anak). |
| **Pengendali Data** | Pihak yang menentukan tujuan & kendali pemrosesan data pribadi (UU PDP). |
| **Prosesor Data** | Pihak yang memproses data atas nama pengendali. |
| **KMS** | *Key Management Service*. |
| **DEK / KEK** | *Data Encryption Key* / *Key Encryption Key* (envelope encryption). |
| **HMAC** | *Hash-based Message Authentication Code*. |
| **PAdES** | Standar tanda tangan digital pada PDF (ETSI EN 319 142). |
| **SAST / DAST / SCA** | Analisis statis kode / uji dinamis aplikasi berjalan / analisis komposisi dependensi. |
| **SBOM** | *Software Bill of Materials*. |
| **WAF** | *Web Application Firewall*. |
| **SIEM** | *Security Information and Event Management*. |
| **RPO / RTO** | *Recovery Point Objective* (maks. data hilang) / *Recovery Time Objective* (maks. waktu pulih). |
| **ASVS** | OWASP *Application Security Verification Standard*. |
| **ULID / UUIDv7** | Pengenal unik berurutan waktu yang tidak dapat ditebak secara berurutan seperti auto-increment. |

## 3. Konvensi Penamaan

| Objek | Konvensi | Contoh |
|---|---|---|
| Tabel basis data | `snake_case`, jamak, Bahasa Inggris | `enrollments`, `exam_attempts` |
| Kolom | `snake_case` | `organization_id`, `issued_at` |
| Primary key | `id` bertipe UUIDv7 | `0191f7c2-...` |
| Foreign key | `{entitas}_id` | `course_class_id` |
| Timestamp | `*_at`, `timestamptz` UTC | `created_at`, `revoked_at` |
| Flag boolean | `is_*` / `has_*` | `is_active` |
| Enum status | huruf kecil `snake_case` | `pending_approval` |
| Endpoint API | `kebab-case`, jamak | `/api/v1/course-classes/{id}/enrollments` |
| Izin (permission) pengguna | `resource.action` | `certificate.approve`, `enrollment.view_any` |
| Scope API key mitra | `resource:action` (konvensi OAuth, terpisah dari izin pengguna) | `certificates:verify`, `enrollments:write` |
| Event domain | `PastTense` | `CertificateIssued`, `PaymentSettled` |
| Nama berkas dokumen | `NN-kebab-case.md` | `05-desain-database.md` |
| Kode kebutuhan | `FR-{MODUL}-{NNN}`, `NFR-{KAT}-{NN}`, `SEC-{AREA}-{NN}` | `FR-CERT-004`, `SEC-AUTH-07` |

## 4. Pemetaan Status (UI ↔ Kode)

| Konteks | UI (purwarupa) | Kode |
|---|---|---|
| Enrollment | Menunggu Pembayaran | `awaiting_payment` |
| | Terdaftar | `enrolled` |
| | Berjalan | `in_progress` |
| | Menunggu Approval | `pending_approval` |
| | Lulus | `passed` |
| | Tidak Lulus | `failed` |
| | Dibatalkan | `cancelled` |
| Sertifikat | Sedang Dibuat | `generating` (dan `generation_failed`) |
| | Aktif | `active` |
| | Kedaluwarsa | `expired` (turunan: `valid_until < hari ini`, tidak disimpan) |
| | Dicabut | `revoked` |
| | Digantikan | `superseded` (diterbitkan ulang) |
| Pengumpulan tugas | Dikumpulkan / Revisi / Disetujui / Ditolak | `submitted` / `revision_requested` / `approved` / `rejected` |
| Presensi | Hadir / Terlambat / Tidak Hadir / Izin | `present` / `late` / `absent` / `excused` |
| Transaksi | Menunggu / Lunas / Gagal / Kedaluwarsa / Refund Diproses / Refund | `pending` / `settled` / `failed` / `expired` / `refund_pending` / `refunded` |
| Permintaan privasi | Menunggu / Diproses / Selesai / Ditolak | `pending` / `in_progress` / `completed` / `rejected` |
| Program | Draft / Review / Published / Archived | `draft` / `in_review` / `published` / `archived` |

## 5. Istilah Manajemen Proyek

| Istilah | Definisi |
|---|---|
| Epic (`EP-NN`) | Kumpulan fitur besar (≥ 1 sprint) yang dipecah menjadi *user story*. |
| Story | Unit kerja yang selesai dalam satu sprint; selalu merujuk ≥ 1 `FR-*`/`NFR-*`/`SEC-*`. |
| M*n* | Minggu ke-*n* sejak kick-off proyek (dipakai di roadmap). |
| MS-*n* | Milestone bernomor. |
| ow (orang-minggu) | Satuan estimasi: kerja bersih 1 developer selama 1 minggu. |
| DoR / DoD | *Definition of Ready* / *Definition of Done*. |
| Security gate | Syarat keamanan otomatis/manual yang wajib lolos sebelum merge, rilis, atau penutupan fase. |
| UAT | *User Acceptance Testing* oleh perwakilan pengguna bisnis. |
| Go/No-Go | Rapat keputusan rilis produksi berdasarkan checklist. |
| Cutover | Rangkaian langkah terjadwal memindahkan operasi ke sistem baru. |
| Hypercare | Periode dukungan intensif setelah go-live. |
| CCB | *Change Control Board* — forum pemutus *Change Request*. |
| RACI | *Responsible, Accountable, Consulted, Informed*. |
| RAG | Status Merah/Kuning/Hijau dalam laporan. |
