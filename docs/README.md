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

## Status Implementasi — Fase 1 (MVP Inti), inkremen Sprint 1–2

| Komponen | Status | Lokasi kode / uji |
|---|---|---|
| Registrasi mandiri peserta + OTP email (10 menit, maks. 5 percobaan, kirim ulang 3/jam), anti-enumerasi, persetujuan S&K/Privasi berversi | ✅ | `RegistrationService`, `tests/Security/RegistrationTest.php` (FR-AUTH-001, 002) |
| Keanggotaan organisasi saat registrasi: aktif hanya bila domain email terverifikasi, selain itu *pending* | ✅ | FR-AUTH-003 |
| Token sekali pakai ter-hash (`one_time_tokens`) untuk OTP & undangan; payload antrian berisi rahasia dienkripsi | ✅ | `OneTimeTokens` |
| Halaman Keamanan Akun: ubah kata sandi (wajib sandi lama, cabut sesi lain, notifikasi email), buat ulang kode pemulihan (re-auth), riwayat masuk | ✅ | `tests/Security/AccountSecurityTest.php` (FR-AUTH-008, 010 sebagian) |
| Re-autentikasi `reauth` untuk aksi sensitif (kembali ke halaman asal, POST tidak diputar ulang) | ✅ | `RequireRecentAuth` (FR-AUTH-014) |
| Master Organisasi: CRUD, kode immutable, arsip dengan alasan, domain email terverifikasi (domain email publik ditolak) | ✅ | `tests/Security/OrganizationAdminTest.php` (FR-ORG-001) |
| Master Pengguna: daftar (email disamarkan), undangan 72 jam, tetapkan/cabut peran sesuai hierarki, nonaktif/aktifkan (cabut sesi), reset MFA terverifikasi | ✅ | `tests/Security/UserAdministrationTest.php` (FR-USER-001, 002, 003, 006) |
| Penetapan `super_admin` via UI | ⛔ Ditolak sampai alur persetujuan kedua (maker–checker) tersedia | FR-USER-002 |
| Pembersihan registrasi tak terverifikasi (7 hari) & token kedaluwarsa | ✅ terjadwal harian | `stu:prune-unverified` |
| Persetujuan anggota oleh Admin Organisasi, sesi & perangkat, notifikasi login perangkat baru, CAPTCHA adaptif, profil peserta | ⏳ Sprint berikutnya | FR-AUTH-009, 010, FR-USER-005, ORG-02 |
| Katalog program, kelas, konten, asesmen, enrollment, sertifikat | ⏳ Sprint 3–9 | EP-05…EP-10 |

## Hal yang Masih Perlu Ditetapkan (di Fase 0)

- Nama & kontak resmi: DPO, alamat `security@`, domain produksi final.
- Pemilihan PSrE/CA untuk sertifikat penandatangan dokumen.
- Pemilihan penyedia cloud (AWS/GCP/penyedia lokal) & CDN/WAF final.
- Akun merchant payment gateway & persetujuan WhatsApp BSP.
- Tinjauan legal: Kebijakan Privasi, S&K, DPA mitra, penggunaan merek vendor/BNSP pada sertifikat.
- Konfirmasi batas waktu kewajiban UU PDP terhadap peraturan pelaksana yang berlaku.
