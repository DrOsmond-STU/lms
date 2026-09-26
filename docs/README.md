# Dokumentasi Pra-Development — STU LMS

> Status paket dokumen: **Versi 1.0 — siap ditinjau & disetujui sebelum development dimulai**
> Tanggal: September 2026

Folder ini berisi seluruh dokumen yang wajib disepakati **sebelum baris kode produksi pertama
ditulis**: apa yang dibangun, bagaimana arsitekturnya, bagaimana data disimpan, siapa boleh
melakukan apa, dan — dengan porsi terbesar — **bagaimana sistem diamankan**.

Purwarupa UI statis di root repositori (`index.html`, `peserta/`, `trainer/`, `admin/`,
`assets/`) adalah **acuan visual & alur saja**. Logika di dalamnya **tidak boleh** dipindahkan ke
produksi — lihat [`keamanan/16-temuan-keamanan-purwarupa.md`](keamanan/16-temuan-keamanan-purwarupa.md).

## Ringkasan Keputusan Utama

| Aspek | Keputusan |
|---|---|
| Arsitektur | Modular monolith **Laravel 13** (PHP 8.4), Blade + **Livewire 4** + Alpine + Tailwind 4 (build Vite) |
| Data | **PostgreSQL 16** + Row-Level Security untuk isolasi organisasi; Redis; object storage S3-compatible |
| Hosting | Cloud VM/kontainer di **region Jakarta**, CDN + WAF di depan; bukan shared hosting |
| Peran | Super Admin, Admin Akademik, Admin Keuangan, Admin Layanan (opsional), Admin Organisasi, Trainer, Peserta |
| Keamanan | Target **OWASP ASVS v5.0 Level 2** (+ L3 terpilih untuk auth admin, sertifikat, pembayaran); MFA wajib non-peserta; SoD & maker–checker |
| Sertifikat | PDF dibuat & **ditandatangani digital (PAdES) di server**, kunci di KMS/HSM, QR berisi kode verifikasi acak |
| Pembayaran | Midtrans Snap (hosted) — lingkup PCI DSS SAQ-A; webhook terverifikasi + konfirmasi status |
| Privasi | Kepatuhan **UU No. 27/2022 (PDP)**: DPO, RoPA, DPIA, hak subjek data, notifikasi ≤ 3×24 jam |
| Skala | ± 5.000 pengguna tahun 1 (uji beban 2× puncak); arsitektur siap tumbuh hingga 50.000 |
| Jadwal | ± 12 bulan ke go-live (baseline), skenario percepatan tersedia — lihat dokumen 12 |

## Daftar Dokumen

### A. Produk & Kebutuhan

| # | Dokumen | Isi | Pembaca utama |
|---|---|---|---|
| 00 | [Glosarium & Konvensi](00-glosarium.md) | Istilah domain & teknis, konvensi penamaan, pemetaan status UI ↔ kode | Semua |
| 01 | [Visi & Ruang Lingkup](01-visi-dan-ruang-lingkup.md) | Latar belakang, tujuan & KPI, persona, scope in/out, asumsi, kriteria go-live | Manajemen, PO |
| 02 | [Kebutuhan Fungsional](02-kebutuhan-fungsional.md) | 150 kebutuhan `FR-*` per modul (MoSCoW & fase), mesin status, aturan bisnis, format nomor | PO, Dev, QA |
| 03 | [Kebutuhan Non-Fungsional](03-kebutuhan-non-fungsional.md) | Performa, kapasitas, ketersediaan (RPO/RTO), keamanan ringkas, kepatuhan, UX, retensi | Tech Lead, DevOps |

### B. Desain Teknis

| # | Dokumen | Isi | Pembaca utama |
|---|---|---|---|
| 04 | [Arsitektur Sistem](04-arsitektur-sistem.md) | Tech stack, diagram C4, modul domain, alur sequence, job asinkron, ADR-001..007 | Dev, DevOps |
| 05 | [Desain Basis Data](05-desain-database.md) | Klasifikasi data K1–K4, ERD, definisi tabel, RLS, peran DB, indeks, pemetaan dari purwarupa | Dev |
| 06 | [Spesifikasi API](06-spesifikasi-api.md) | Konvensi, format error, autentikasi API key, endpoint verifikasi & mitra, webhook, rate limit | Dev, Mitra |
| 07 | [RBAC & Multi-Tenant](07-rbac-dan-multi-tenant.md) | Peran, katalog izin, matriks peran × izin, SoD, isolasi tenant, siklus hidup akun | Dev, Security, PO |
| 08 | [Spesifikasi UI & Pemetaan Halaman](08-spesifikasi-ui-dan-pemetaan-halaman.md) | Design system, pemetaan setiap halaman purwarupa → rute/izin, halaman baru, alur UX, pola UI keamanan, aksesibilitas | Designer, Dev |

### C. Proses Rekayasa

| # | Dokumen | Isi | Pembaca utama |
|---|---|---|---|
| 09 | [Standar Pengembangan](09-standar-pengembangan.md) | Struktur modul, layering, standar PHP, **aturan secure coding Laravel**, frontend, migrasi, Git, review, DoR/DoD | Dev |
| 10 | [Strategi Pengujian](10-strategi-pengujian.md) | Piramida uji, alat, data uji, skenario per modul, **pengujian keamanan mendalam**, performa, UAT, gerbang CI | QA, Dev |
| 11 | [DevOps & Deployment](11-devops-dan-deployment.md) | Topologi, IaC, image, CI/CD, rilis & rollback, rahasia, Nginx, observabilitas, backup/DR, runbook | DevOps |
| 12 | [Rencana Proyek & Roadmap](12-rencana-proyek-dan-roadmap.md) | Tim & RACI, fase & milestone, epic, kapasitas, dependensi eksternal, register risiko, go-live & hypercare | PM, PO, Manajemen |

### D. Keamanan (Spesifikasi Wajib)

Indeks & kebijakan umum: **[`keamanan/README.md`](keamanan/README.md)**

| # | Dokumen | Fokus |
|---|---|---|
| 01 | [Model Ancaman](keamanan/01-model-ancaman.md) | Aset, aktor, trust boundary, STRIDE per komponen, 18 abuse case wajib uji |
| 02 | [Autentikasi & Sesi](keamanan/02-autentikasi-dan-sesi.md) | Argon2id, anti brute-force & enumerasi, OTP, MFA (TOTP/WebAuthn), sesi & cookie, reset, SSO |
| 03 | [Otorisasi & Isolasi Tenant](keamanan/03-otorisasi-dan-isolasi-tenant.md) | Deny-by-default, anti-IDOR, Livewire, RLS, SoD, maker–checker |
| 04 | [Validasi Input & Output](keamanan/04-validasi-input-dan-output.md) | XSS/CSP, rich text, mass assignment, SQLi, CSRF, SSRF, CSV injection, open redirect |
| 05 | [Berkas & Media](keamanan/05-keamanan-berkas-dan-media.md) | Allowlist jenis, AV scan, sandbox FFmpeg/PDF, URL bertanda tangan, HLS terenkripsi |
| 06 | [Kriptografi & Kunci](keamanan/06-kriptografi-dan-manajemen-kunci.md) | TLS, hashing, enkripsi field (envelope/KMS), rahasia & rotasi, inventaris kunci |
| 07 | [Integritas Sertifikat](keamanan/07-integritas-sertifikat.md) | Penerbitan aman, PAdES + KMS/HSM, penomoran atomik, verifikasi publik minim-data, pencabutan |
| 08 | [Integritas Ujian](keamanan/08-integritas-ujian-dan-penilaian.md) | Kunci jawaban tak pernah ke klien, waktu server, attempt tunggal, anti-curang proporsional, presensi QR |
| 09 | [Pembayaran](keamanan/09-keamanan-pembayaran.md) | Harga server-side, verifikasi webhook, idempotensi, kupon atomik, refund & rekonsiliasi |
| 10 | [API & Integrasi](keamanan/10-keamanan-api-dan-integrasi.md) | OWASP API Top 10, API key ber-scope, webhook keluar, vendor |
| 11 | [Logging, Audit & Monitoring](keamanan/11-logging-audit-dan-monitoring.md) | Log terstruktur & redaksi, security events, audit berantai hash + WORM, 16 aturan deteksi |
| 12 | [Privasi & UU PDP](keamanan/12-privasi-data-dan-kepatuhan-uu-pdp.md) | Peran pengendali/prosesor, RoPA, consent, hak subjek, anonimisasi, retensi, DPIA, breach |
| 13 | [Infrastruktur](keamanan/13-keamanan-infrastruktur.md) | Akun cloud, jaringan & egress, WAF, header/CSP, kontainer, PHP/DB/Redis, backup, DNS/email |
| 14 | [Secure SDLC & Supply Chain](keamanan/14-secure-sdlc-dan-supply-chain.md) | Gerbang CI keamanan, code review, dependensi, SLSA, pentest, SLA perbaikan |
| 15 | [Respons Insiden & Kontinuitas](keamanan/15-respons-insiden-dan-kontinuitas.md) | Severity, tim, notifikasi PDP 3×24 jam, 13 playbook, BCP/DR & latihan |
| 16 | [Temuan Keamanan Purwarupa](keamanan/16-temuan-keamanan-purwarupa.md) | 30 celah purwarupa yang tidak boleh terbawa ke produksi |
| 17 | [Checklist Keamanan](keamanan/17-checklist-keamanan.md) | Checklist PR, desain fitur, go-live (wajib 100%), operasional berkala |

### D2. Matriks Fitur

| Dokumen | Isi |
|---|---|
| [`13-matriks-fitur.md`](13-matriks-fitur.md) | Pemetaan 10 kategori fitur LMS → implementasi, halaman, perintah terjadwal, uji (Fase 2) |

### E. Berkas Pendukung di Root

| Berkas | Isi |
|---|---|
| [`/SECURITY.md`](../SECURITY.md) | Kebijakan pelaporan kerentanan (VDP) & safe harbor |
| [`/CONTRIBUTING.md`](../CONTRIBUTING.md) | Panduan kontribusi singkat |

## Urutan Membaca yang Disarankan

1. **Semua anggota tim:** 00 → 01 → `keamanan/README` → `keamanan/16`
2. **Developer:** 04 → 07 → 05 → 06 → 09 → seluruh `keamanan/02–10` → 10
3. **QA:** 02 → 07 → 10 → `keamanan/01` → `keamanan/17`
4. **DevOps:** 04 → 03 → 11 → `keamanan/06`, `11`, `13`, `14`, `15`
5. **PO/PM/Manajemen:** 01 → 02 → 12 → `keamanan/12`, `15`
6. **DPO/Legal:** `keamanan/12` → `keamanan/15` → 01 §6 → 05 §2

## Ketertelusuran Kebutuhan

```
FR-* / NFR-* (dok. 02, 03)
   └── SEC-* (keamanan/02–15)  ←  TM-* & AB-* (keamanan/01)
          └── Uji: tests/Security/... (dok. 10) → matriks ketertelusuran di CI
                 └── Checklist rilis (keamanan/17)
```

## Tata Kelola Dokumen

- Dokumen diperlakukan sebagai **kode** (*docs-as-code*): perubahan lewat Pull Request, ditinjau
  pemilik dokumen yang tercantum di kepala setiap berkas.
- Perubahan keputusan arsitektur → ADR baru di dokumen 04 §10.
- Perubahan pada folder `keamanan/` wajib disetujui Security Lead; perubahan terkait data pribadi
  wajib disetujui DPO.
- Setiap PR yang mengubah perilaku sistem memperbarui dokumen terkait di PR yang sama.
- Tinjauan menyeluruh: setiap akhir fase dan tahunan.

## Persetujuan Sebelum Development Dimulai

| Peran | Nama | Tanggal | Tanda tangan |
|---|---|---|---|
| Sponsor / Manajemen | | | |
| Product Owner | | | |
| Tech Lead | | | |
| Security Lead | | | |
| DPO | | | |
| DevOps Lead | | | |
| QA Lead | | | |

## Status Implementasi — Fase 0 (Fondasi)

| Komponen | Status | Lokasi kode / uji |
|---|---|---|
| Kerangka Laravel 13 modular (`app/Modules/*`, `app/Support/*`) | ✅ | `app/` |
| Purwarupa dipindah sebagai referensi (tidak di-deploy) | ✅ | `prototype/` |
| Peran DB terpisah `stu_migrator` / `stu_app` (tanpa BYPASSRLS) + RLS contoh (`organization_members`) | ✅ | `docker/postgres/init-roles.sql`, migrasi, `tests/Security/TenantIsolationTest.php` |
| Katalog izin & peran dari dok. 07 (seed otomatis, tanpa bypass Super Admin) | ✅ | `app/Modules/Access`, `tests/Unit/PermissionsTest.php` |
| Login server-side: Argon2id, rate limit berlapis, pesan generik, anti-fixation | ✅ | `app/Modules/Identity`, `tests/Security/AuthenticationTest.php` |
| MFA TOTP wajib non-peserta + kode pemulihan + anti-replay | ✅ | `tests/Security/MfaTest.php`, `tests/Unit/TotpTest.php` (vektor RFC 6238) |
| Sesi Redis, cookie `__Host-`, timeout idle/absolut, cabut semua sesi (session version) | ✅ | `tests/Security/SessionTest.php` |
| Lupa/atur ulang kata sandi (URL dari APP_URL, token sekali pakai, tanpa auto-login) | ✅ | `tests/Security/PasswordResetTest.php` |
| Re-autentikasi aksi sensitif (`password.confirm` + TOTP) | ✅ | `ConfirmAccessController` |
| Header keamanan & CSP ber-nonce, Livewire `csp_safe`, aset self-hosted | ✅ | `tests/Security/HttpHeadersTest.php` |
| Jejak audit berantai hash + append-only + `stu:audit-verify`; security events | ✅ | `app/Modules/Audit`, `tests/Security/AuditTrailTest.php` |
| Shell UI dari purwarupa, dashboard per area, halaman error | ✅ | `resources/views` |
| Docker (image non-root, read-only), Compose lokal, Nginx | ✅ (config tervalidasi; build image diuji di CI) | `Dockerfile`, `docker/`, `docker-compose.yml` |
| CI: Pint, Larastan L8, Pest (PostgreSQL+Redis nyata), composer/npm audit, gitleaks, Semgrep | ✅ | `.github/workflows/ci.yml`, `.semgrep.yml` |
| Staging UAT di shared hosting cPanel (`lms.semestateknologiutama.com`) | ✅ (deviasi dari topologi produksi tercatat) | `deploy/shared-hosting/` |
| IaC (OpenTofu), staging cloud, deploy CD, image signing | ⏳ Fase 0 lanjutan | dok. 11 |
| WebAuthn/Passkey | ⏳ Fase 3 | FR-AUTH-006 |

## Status Implementasi — Fase 1 (MVP Inti)

Seluruh alur inti berjalan end-to-end di staging: **program → kelas → materi → kuis/ujian →
enrollment → kelulusan → approval → sertifikat PDF bertanda tangan → verifikasi publik**.

| Epic | Komponen | Status | Uji |
|---|---|---|---|
| EP-02 | Registrasi + OTP email, login, MFA TOTP + kode pemulihan, lupa/ubah kata sandi, re-auth, **sesi & perangkat** (cabut satu/semua), **notifikasi login perangkat baru** | ✅ | `RegistrationTest`, `AccountSecurityTest`, `PlatformFeaturesTest` |
| EP-03 | Master pengguna: undangan 72 jam, peran sesuai hierarki, nonaktif, reset MFA terverifikasi, **Super Admin via persetujuan kedua (maks. 3)**, profil peserta | ✅ | `UserAdministrationTest`, `PlatformFeaturesTest` |
| EP-04 | Master organisasi + domain terverifikasi; **portal Admin Organisasi** (setujui anggota, progres anggota, audit organisasi) | ✅ | `OrganizationAdminTest`, `PlatformFeaturesTest` |
| EP-05 | Program: CRUD, deskripsi Markdown tersanitasi, tag, **review oleh admin berbeda** (dijaga DB), arsip; katalog publik & peserta dengan filter | ✅ | `CatalogAndClassTest` |
| EP-06 | Kelas/batch: periode, jendela daftar, **kuota atomik** (UPDATE bersyarat + CHECK DB), trainer utama/asisten, kelas khusus organisasi, syarat kelulusan, status | ✅ | `CatalogAndClassTest`, `EnrollmentAndLearningTest` |
| EP-07 | Modul → Bab → Lesson (video, PDF, teks, tautan allowlist, kuis), urutan naik/turun; unggahan **magic bytes**, hash SHA-256, disk privat, **URL bertanda tangan 10 menit terikat pengguna**; progres video tervalidasi kewajaran (SEC-EXAM-17) | ✅ (lihat deviasi) | `EnrollmentAndLearningTest` |
| EP-08 | Bank soal (5 tipe, kunci isian terenkripsi, versi soal, akses diaudit); kuis & ujian akhir; **alias ID per attempt**, timer server + grace 30 dtk, auto-submit terjadwal, satu attempt aktif (indeks unik parsial), penilaian server, esai manual, kesempatan tambahan beralasan, void attempt, indikator integritas | ✅ | `ExamIntegrityTest` |
| EP-09 | Enrollment mandiri (gratis) & oleh admin, mesin status + riwayat, satu enrollment aktif per program, evaluasi kelulusan otomatis → antrean approval, "Pembelajaran Saya" | ✅ | `EnrollmentAndLearningTest`, `ExamIntegrityTest` |
| EP-10 | Template berversi (immutable setelah dipakai, aktivasi maker–checker), approval dengan **SoD** (trainer kelas tidak boleh menyetujui), nomor `{KAT}/{KODE}/{ORG}/{TAHUN}/{URUT5}` atomik, kode verifikasi Crockford 12 karakter, **PDF ditandatangani digital (PKCS#7 tertanam) + QR**, hash disimpan, unduhan URL 5 menit, verifikasi publik web + `GET /api/v1/certificates/verify/{code}` (nama tersamar via nomor, rate limit 10/menit & 100/hari, log IP ter-hash), pencabutan maker–checker, basis data + ekspor CSV, pengingat kedaluwarsa | ✅ (lihat deviasi) | `CertificationTest` |
| EP-11 | Pusat notifikasi in-app + email minimal (tautan internal saja) | ✅ | `PlatformFeaturesTest` |
| EP-12 | Dashboard peserta, trainer, Admin Organisasi, admin platform | ✅ | `PlatformFeaturesTest` |
| EP-13 | Tampilan jejak audit berfilter sesuai lingkup + ekspor diaudit; pengaturan sistem dalam batas aman (diaudit) | ✅ | `PlatformFeaturesTest` |
| EP-14 | Persetujuan berversi, persetujuan ulang saat versi berubah, persetujuan opsional dapat ditarik | ✅ (teks hukum final dari tim legal) | `PlatformFeaturesTest` |

**Uji otomatis:** 169 uji (Pest, PostgreSQL nyata dengan RLS), PHPStan level 8 bersih, Pint,
Semgrep kustom, gitleaks; uji browser end-to-end Playwright tanpa pelanggaran CSP.

### Deviasi staging yang tercatat (bukan target produksi)

| Kebutuhan | Staging (shared hosting) | Produksi (rencana) |
|---|---|---|
| Transcoding video HLS (FR-CNT-002) | Video disajikan langsung (MP4/WebM) lewat URL bertanda tangan + Range | Pipeline transcoding HLS + kunci AES di worker media |
| Pemindaian malware (FR-CNT-003) | `MEDIA_SCANNER=none` — berkas ditandai "belum dipindai antivirus" di UI | ClamAV (`clamd`) wajib; ProductionGuard menolak `none` |
| Tanda tangan PDF PAdES B-LT + timestamp RFC 3161 (FR-CERT-005) | PKCS#7 tertanam dengan **sertifikat uji** self-signed (`stu:signing-key`) | Sertifikat PSrE/AATL di KMS/HSM + TSA |
| Unggahan video ≤ 2 GB | Dibatasi `upload_max_filesize` hosting (512 MB) | Unggahan langsung ke object storage |
| Worker antrian & penjadwal | Cron berjeda ≥ 6 menit: email mendesak (OTP, atur ulang sandi) dikirim `deferred` setelah respons; email lain tertunda ≤ ±9 menit; auto-submit ujian ≤ 8 menit (attempt kedaluwarsa tetap ditolak/dikumpulkan saat dibuka) | Worker permanen (Supervisor/Horizon) + scheduler tiap menit |
| Pembayaran program berbayar | **Transfer manual tercatat** (tahap A): tagihan + bukti transfer + verifikasi Admin Keuangan (maker–checker di atas ambang) + invoice PDF; belum ada gateway online | Fase 2 (EP-15, Midtrans Snap) |

### Tema antarmuka

Gaya visual mengikuti purwarupa KG SafeGuard (khongguan.semestateknologiutama.com): sidebar &
hero biru gradasi, kartu & tombol **melayang berbayang** (tekan = turun), ubin KPI berangka mono,
grafik SVG sisi server, dan tema **terang/gelap/ikuti sistem**. Semua warna lewat token CSS di
`resources/css/app.css` (palet utilitas Tailwind dipetakan ke token), sehingga tema gelap berlaku
di seluruh halaman. Preferensi tema disimpan di cookie `stu_theme` (allowlist `light|dark`) dan
dirender server — tanpa skrip inline (CSP tetap ketat). Font di-host sendiri (Plus Jakarta Sans,
IBM Plex Sans/Mono). Judul halaman memakai slot hero di `x-layouts.app`
(`heading`, `subtitle`, `back`, `meta`, `actions`, `aside`).

### Beranda & konten beranda (CMS ringan)

Beranda publik (`/`): slide bergambar di bawah lapisan biru gradasi (3 slide bawaan bila belum
ada slide aktif), daftar pelatihan yang dapat difilter (jenis kompetensi Internasional/BNSP,
topik, harga, waktu mulai, durasi — di server & instan di klien) dengan tombol **Ikut
Pelatihan** (tamu → masuk/daftar → kembali ke halaman program), alur bergabung, testimoni,
logo mitra berjalan, dan informasi pemilik situs. Dikelola di **Pengaturan Sistem → Beranda**
(izin `cms.view`/`cms.update`): teks & judul, slide, testimoni, mitra; profil pemilik di tab
**Profil Pemilik**; semua perubahan diaudit.
Gambar diperiksa magic bytes lalu **digambar ulang** (GD → WebP, metadata dibuang, SVG ditolak),
disimpan di disk privat dan disajikan lewat rute publik. Testimoni hanya bisa terbit bila
persetujuan publikasi dikonfirmasi (dijaga juga CHECK basis data). `stu:demo-landing` mengisi
program, testimoni berlabel **Contoh**, dan mitra fiktif untuk UAT (ditolak di produksi).

### Pengaturan Sistem (tanpa nilai tertanam di kode)

Semua teks, identitas, dan parameter operasional diatur di **Admin → Pengaturan Sistem**
(`/admin/pengaturan`), per tab; setiap simpan diaudit dan berlaku seketika:

| Tab | Isi | Izin |
|---|---|---|
| Umum | nama aplikasi & nama singkat, tagline, zona waktu tampilan (WIB/WITA/WIT), teks panel halaman masuk | `system_setting.*` + re-autentikasi |
| Profil Pemilik | nama perusahaan, tentang, alamat, email, telepon, WhatsApp, situs, media sosial | `cms.*` |
| Beranda | judul/sub-judul pembuka, keunggulan, alur bergabung, judul bagian, ajakan penutup; sub-menu Slide/Testimoni/Mitra | `cms.*` |
| Pendaftaran & Akun | registrasi mandiri, masa berlaku OTP & undangan, pembersihan akun tak terverifikasi | `system_setting.*` + re-autentikasi |
| Pembelajaran & Ujian | ambang tonton video, masa tenggang ujian, nilai lulus & masa berlaku sertifikat bawaan program baru | `system_setting.*` + re-autentikasi |
| Sertifikat | kode penerbit pada nomor sertifikat, hari pengingat kedaluwarsa, catatan kaki PDF, catatan halaman verifikasi | `system_setting.*` + re-autentikasi |
| Dokumen Hukum | versi & isi (Markdown, disanitasi) Syarat & Ketentuan dan Kebijakan Privasi | `system_setting.*` + re-autentikasi |
| Keamanan | sesi, kebijakan kata sandi | `system_setting.*` + re-autentikasi |

Nilai divalidasi (wajib/pola/rentang/pilihan); tanpa nilai tersimpan dipakai bawaan dari
`config/lms.php` dan `config/*.php`. Pesan validasi bawaan berbahasa Indonesia (`lang/id`).

### CRUD & aturan perlindungan data yang dipakai

Setiap entitas admin dapat dibuat, diubah, dan dihapus/diarsipkan dari UI. Penghapusan ditolak
bila data sudah dipakai, agar riwayat nilai & sertifikat tetap utuh: bank soal yang dipakai
asesmen atau sudah dijawab, soal yang sudah muncul di attempt, asesmen yang sudah dikerjakan atau
ditautkan ke materi kuis, template sertifikat yang aktif/terpakai. Peserta dapat membatalkan
pendaftarannya sendiri (sebelum lulus); admin dapat membatalkan dengan alasan (diaudit, kursi kembali).
Uji `PageCrawlTest` membuka setiap halaman yang terjangkau Super Admin, peserta, dan tamu dan
memastikan tidak ada yang error.

### Simulasi perjalanan peserta

`php artisan stu:simulate` (ditolak di produksi) menjalankan layanan aplikasi yang sama dengan UI
untuk 9 peserta fiktif (`@simulasi.test`) di kelas demo: daftar kelas → buka & selesaikan materi →
kuis → ujian akhir → approval sertifikat → PDF bertanda tangan → verifikasi publik (halaman & API).
Skenario mencakup lulus bersertifikat (5), menunggu approval, gagal ujian, masih belajar, dan
dibatalkan. Idempoten; mencetak tabel status beserta URL verifikasi dan keluar dengan kode gagal
bila ada pemeriksaan yang tidak sesuai. Di staging dijalankan otomatis oleh `lms-setup.sh`.

### Pembayaran transfer manual tercatat (FR-PAY tahap A)

Peserta memilih **Daftar & Bayar** pada program berbayar → kursi dipesan, enrollment
`awaiting_payment`, tagihan `pending` (`STU-{ULID}`) dengan batas waktu (Pengaturan → Pembayaran,
bawaan 72 jam). Halaman tagihan menampilkan rekening tujuan (dari Pengaturan Sistem), jumlah, dan
formulir unggah **bukti transfer** (JPG/PNG/PDF ≤ 5 MB; diperiksa magic bytes, dipindai, disimpan di
disk privat, hanya bisa dilihat pemilik & Admin Keuangan) beserta data invoice opsional (NPWP &
alamat terenkripsi). Admin Keuangan (`payment.mark_paid_manual`) memverifikasi di **Admin →
Pembayaran**: *Konfirmasi lunas* (dengan re-autentikasi) → nomor invoice `INV/TAHUN/BULAN/URUT5`
atomik, enrollment `enrolled`, notifikasi + email ke peserta, invoice PDF (TCPDF, dirender saat
diminta); di atas ambang (bawaan Rp1.000.000) konfirmasi menjadi **permintaan persetujuan kedua**
yang harus diputus admin lain (`payment.settle_manual`, pengaju tidak boleh memutus). *Tolak bukti*
meminta unggah ulang; *Batalkan* menutup tagihan dan melepas kursi. `stu:payments-expire` (tiap
jam) mengedaluwarsakan tagihan tanpa bukti yang melewati batas waktu; peserta yang membatalkan
pendaftarannya menutup tagihan otomatis. Semua langkah tercatat di `payment_events` (append-only,
dijaga trigger) dan `audit_logs`. Skema `payment_transactions` mengikuti docs/05 §4.8 sehingga
gateway online (Midtrans) tinggal ditambahkan; tabel `invoices`/`payment_events` gateway, kupon, dan
refund belum dibuat.

### Program referral & laporan

**Referral.** Setiap peserta memiliki kode 8 karakter (tanpa huruf/angka ambigu) dan tautan
`/daftar?ref=KODE` di menu **Referral**; tautan halaman mana pun dengan `?ref=` juga berlaku
(kode disimpan di cookie terenkripsi 30 hari dan terisi otomatis di formulir pendaftaran, atau
diketik manual). Akun baru dikaitkan **sekali saat registrasi** (first-touch, tidak dapat dipindah;
referral diri sendiri/email sama ditolak). Komisi (persentase dari Pengaturan → Pembayaran →
Program referral, dengan batas per transaksi dan masa berlaku sejak registrasi) lahir **hanya saat
pembayaran akun tersebut dikonfirmasi lunas**, satu komisi per transaksi (unik di basis data).
Peserta melihat kunjungan tautan, akun terdaftar, komisi tertunda/dibayar, dan mengisi rekening
pencairan (nomor terenkripsi). Admin Keuangan (`referral.pay`) mencairkan semua komisi tertunda
seorang referrer sebagai satu **payout** dengan referensi transfer (re-autentikasi; pengaju ≠
penerima, dijaga CHECK basis data) atau membatalkan komisi dengan alasan; semuanya diaudit dan
diberitahukan ke referrer. RLS: referrer hanya melihat komisinya sendiri.

**Laporan (Admin → Laporan).** *Per Organisasi*: anggota aktif, enrollment (aktif/lulus/tidak
lulus/batal), tingkat kelulusan, sertifikat terbit, pembayaran lunas & pendapatan untuk periode
yang dipilih, dengan halaman detail enrollment per organisasi. *Referral*: kunjungan, akun
terdaftar, transaksi berkomisi, komisi tertunda/dibayar/dibatalkan per referrer, detail dengan
riwayat pencairan. Keduanya dapat diekspor CSV (`report.export`; nilai diamankan dari injeksi
formula; ekspor diaudit).

## Status Implementasi — Fase 2 (fitur lengkap LMS)

Delapan gelombang fitur di atas MVP: konten & jadwal (audio/dokumen, drip & prasyarat, sesi/live
class, presensi, kalender), asesmen (menjodohkan, rubrik, pre/post-test, tugas), kelas interaktif
(forum, tanya jawab, komentar, polling, chat), administrasi (pengumuman, kelompok, buku nilai,
approval pendaftaran, peran supervisor), analitik (laporan kelas, nilai peserta, ekspor
XLSX/PDF/CSV), notifikasi (preferensi, Web Push, WhatsApp gateway, pengingat), AI (tutor,
generator soal, rangkuman, rekomendasi, jalur belajar, saran esai, analisis kelas, kurikulum),
dan keamanan operasional (backup, pemantauan, retensi, hak subjek data). Rincian per fitur:
[`13-matriks-fitur.md`](13-matriks-fitur.md). Uji otomatis: 240 uji Pest, PHPStan level 8 bersih.

### Belum termasuk (sesuai roadmap)

Fase 2: gateway pembayaran online (Midtrans) & kupon & refund, tugas & pengumpulan, presensi QR, live class, diskusi,
notifikasi WhatsApp, laporan & ekspor lanjutan, hak subjek data (UU PDP) mandiri, CMS beranda,
impor massal, ubah email terverifikasi. Fase 3: API key mitra, SSO OIDC, WebAuthn, gamifikasi.

## Hal yang Masih Perlu Ditetapkan (di Fase 0)

- Nama & kontak resmi: DPO, alamat `security@`, domain produksi final.
- Pemilihan PSrE/CA untuk sertifikat penandatangan dokumen.
- Pemilihan penyedia cloud (AWS/GCP/penyedia lokal) & CDN/WAF final.
- Akun merchant payment gateway & persetujuan WhatsApp BSP.
- Tinjauan legal: Kebijakan Privasi, S&K, DPA mitra, penggunaan merek vendor/BNSP pada sertifikat.
- Konfirmasi batas waktu kewajiban UU PDP terhadap peraturan pelaksana yang berlaku.
