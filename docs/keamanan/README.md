# Keamanan — Indeks & Kebijakan Umum

> Status: **Disetujui untuk development** · Versi 1.0 · Pemilik: Security Lead
>
> Folder ini adalah **spesifikasi keamanan wajib** STU LMS. Setiap kebutuhan memiliki ID
> `SEC-{AREA}-{NN}` yang harus dapat ditelusuri ke kode, uji, dan checklist rilis. Bila ada
> konflik antara dokumen ini dan dokumen lain, **dokumen keamanan yang berlaku** sampai
> diselesaikan melalui ADR.

## 1. Mengapa Keamanan Menjadi Prioritas Utama

STU LMS memproses **data pribadi** puluhan ribu peserta dari banyak organisasi, **menerbitkan
sertifikat** yang dipakai untuk melamar kerja, menjalankan **ujian** yang menentukan kelulusan,
dan memproses **pembayaran**. Kegagalan keamanan berdampak pada:

- **Hukum:** sanksi administratif UU PDP (hingga 2% pendapatan tahunan) dan pidana bagi pihak yang
  melawan hukum memperoleh/mengungkap data pribadi;
- **Integritas bisnis:** sertifikat palsu atau "dibeli" meruntuhkan nilai seluruh sertifikat;
- **Kepercayaan mitra:** kebocoran data lintas organisasi mengakhiri kerja sama;
- **Finansial:** manipulasi harga/kupon, penipuan pembayaran.

## 2. Daftar Dokumen

| # | Dokumen | Area ID | Isi |
|---|---|---|---|
| 01 | [Model Ancaman](01-model-ancaman.md) | TM | Aset, aktor ancaman, batas kepercayaan, STRIDE, abuse case, penilaian risiko |
| 02 | [Autentikasi & Sesi](02-autentikasi-dan-sesi.md) | AUTH | Kata sandi, MFA, OTP, SSO, sesi, cookie, pemulihan akun, anti brute force |
| 03 | [Otorisasi & Isolasi Tenant](03-otorisasi-dan-isolasi-tenant.md) | AUTHZ | RBAC, policy, IDOR, RLS, SoD, maker–checker |
| 04 | [Validasi Input & Output](04-validasi-input-dan-output.md) | INPUT | XSS, SQLi, CSRF, SSRF, mass assignment, rich text, CSV injection, open redirect, header |
| 05 | [Keamanan Berkas & Media](05-keamanan-berkas-dan-media.md) | FILE | Unggahan, pemindaian malware, penyajian media, URL bertanda tangan, video HLS |
| 06 | [Kriptografi & Manajemen Kunci](06-kriptografi-dan-manajemen-kunci.md) | CRYPTO | TLS, hashing, enkripsi at-rest, KMS, rotasi, rahasia |
| 07 | [Integritas Sertifikat](07-integritas-sertifikat.md) | CERT | Penomoran, tanda tangan digital, QR, verifikasi publik, pencabutan |
| 08 | [Integritas Ujian & Penilaian](08-integritas-ujian-dan-penilaian.md) | EXAM | Bank soal, attempt, waktu, anti-curang, nilai |
| 09 | [Keamanan Pembayaran](09-keamanan-pembayaran.md) | PAY | Gateway, webhook, harga, kupon, refund, fraud |
| 10 | [Keamanan API & Integrasi](10-keamanan-api-dan-integrasi.md) | API | API key, scope, rate limit, webhook, SSO/OIDC, pihak ketiga |
| 11 | [Logging, Audit & Monitoring](11-logging-audit-dan-monitoring.md) | LOG | Event wajib, audit tamper-evident, SIEM, alert |
| 12 | [Privasi Data & Kepatuhan UU PDP](12-privasi-data-dan-kepatuhan-uu-pdp.md) | PRIV | Dasar pemrosesan, hak subjek, retensi, DPIA, transfer lintas negara |
| 13 | [Keamanan Infrastruktur](13-keamanan-infrastruktur.md) | INFRA | Jaringan, WAF, header HTTP, kontainer, hardening, backup |
| 14 | [Secure SDLC & Supply Chain](14-secure-sdlc-dan-supply-chain.md) | SDLC | SAST/DAST/SCA, review, dependensi, CI/CD, pentest |
| 15 | [Respons Insiden & Kontinuitas](15-respons-insiden-dan-kontinuitas.md) | IR | Rencana respons insiden, notifikasi PDP, BCP/DR |
| 16 | [Temuan Keamanan Purwarupa](16-temuan-keamanan-purwarupa.md) | PROTO | Analisis celah purwarupa yang **tidak boleh** terbawa ke produksi |
| 17 | [Checklist Keamanan](17-checklist-keamanan.md) | — | Checklist per PR, per fitur, dan go-live |

## 3. Standar Acuan

| Standar | Penggunaan |
|---|---|
| **OWASP ASVS v5.0** | Target verifikasi: **Level 2** seluruh aplikasi; kontrol **Level 3** terpilih (lihat §5) |
| **OWASP Top 10** (edisi terbaru) & **OWASP API Security Top 10 (2023)** | Kategori risiko minimum yang wajib tertangani |
| **OWASP WSTG** | Metodologi pengujian & pentest |
| **OWASP Cheat Sheet Series** | Panduan implementasi |
| **NIST SP 800-63B** | Kebijakan kata sandi & autentikator |
| **CIS Benchmarks** | Hardening OS, Docker, Kubernetes, PostgreSQL, Nginx |
| **ISO/IEC 27001:2022** (Annex A) | Kerangka kontrol organisasi (target sertifikasi jangka menengah) |
| **UU No. 27/2022 (PDP)**, **PP 71/2019 (PSTE)**, UU ITE | Kepatuhan hukum Indonesia |
| **PCI DSS v4.0** (SAQ-A) | Lingkup pembayaran via hosted page |
| **SLSA** (level 2 → 3) | Integritas rantai pasok build |

## 4. Prinsip Keamanan

1. **Defense in depth** — setiap kontrol penting punya lapisan kedua (mis. policy aplikasi + RLS).
2. **Least privilege** — pengguna, peran DB, token, service account, dan pipeline hanya diberi hak minimum.
3. **Deny by default** — rute, izin, CORS, egress jaringan, jenis berkas: semuanya tertutup kecuali dibuka eksplisit.
4. **Never trust the client** — semua masukan (termasuk properti Livewire, header, cookie, webhook) divalidasi di server.
5. **Secure defaults** — konfigurasi aman adalah bawaan; pelonggaran memerlukan ADR.
6. **Fail securely** — error menghasilkan penolakan, bukan akses; tanpa kebocoran detail.
7. **Minimisasi data** — kumpulkan, simpan, dan tampilkan data pribadi sesedikit mungkin.
8. **Auditability** — tindakan berdampak dapat ditelusuri ke aktor, waktu, dan alasan.
9. **Separation of duties** — tindakan berdampak tinggi butuh dua orang.
10. **Security is everyone's job** — setiap PR melewati checklist keamanan; Security Champion per tim.

## 5. Target Level ASVS per Area

| Area | Level | Catatan |
|---|---|---|
| Seluruh aplikasi | L2 | Baseline |
| Autentikasi & sesi admin/trainer | L3 terpilih | MFA wajib, phishing-resistant (WebAuthn) untuk Super Admin, re-auth aksi sensitif |
| Penerbitan & verifikasi sertifikat | L3 terpilih | Tanda tangan digital, kunci di KMS/HSM, audit tamper-evident |
| Pembayaran | L3 terpilih | Verifikasi webhook, idempotensi, rekonsiliasi |
| Manajemen rahasia & kunci | L3 terpilih | KMS, rotasi, tanpa rahasia di kode |
| Logging & audit | L3 terpilih | Rantai hash, penyimpanan eksternal |

## 6. Peran & Tanggung Jawab Keamanan

| Peran | Tanggung jawab |
|---|---|
| **Security Lead** | Pemilik dokumen ini, review desain & PR sensitif, koordinasi pentest, penanganan insiden |
| **DPO (Pejabat PDP)** | Kepatuhan UU PDP, RoPA, DPIA, hak subjek data, notifikasi kegagalan PDP |
| **Tech Lead** | Menegakkan standar di arsitektur & code review |
| **Security Champion** (1 per tim/squad) | Review keamanan harian, threat modeling fitur baru |
| **DevOps Lead** | Hardening infrastruktur, rahasia, backup, monitoring |
| **Setiap developer** | Mengikuti [`09-standar-pengembangan.md`](../09-standar-pengembangan.md) & checklist PR |
| **Product Owner** | Memprioritaskan temuan keamanan sesuai SLA |

## 7. Konvensi ID & Ketertelusuran

- Kebutuhan: `SEC-{AREA}-{NN}`, mis. `SEC-AUTH-07`.
- Setiap kebutuhan dipetakan ke uji (`tests/Security/...` dengan anotasi `@covers SEC-...` atau
  atribut Pest `->group('SEC-AUTH-07')`), dan ke item checklist di dokumen 17.
- Matriks ketertelusuran dibuat otomatis dari anotasi uji dan diterbitkan sebagai artefak CI.

## 8. Pengecualian (Risk Acceptance)

Kebutuhan keamanan yang tidak dapat dipenuhi harus melalui **formulir penerimaan risiko**:
deskripsi, alasan, kontrol kompensasi, pemilik risiko (minimal Tech Lead + Security Lead + PO),
tanggal kedaluwarsa (maks. 90 hari), dan dicatat di register risiko. Tidak ada pengecualian untuk:
MFA admin, isolasi tenant, verifikasi webhook pembayaran, dan enkripsi transport.

## 9. Pelaporan Kerentanan

Kebijakan pengungkapan kerentanan publik: [`/SECURITY.md`](../../SECURITY.md).
