# 10 — Keamanan API & Integrasi Pihak Ketiga

> Status: **Disetujui untuk development** · Versi 1.0 · Pemilik: Security Lead
>
> Acuan: OWASP API Security Top 10 (2023), ASVS v5.0 V4 & V10 (OAuth/OIDC). Desain endpoint di
> [`../06-spesifikasi-api.md`](../06-spesifikasi-api.md).

## 1. Pemetaan OWASP API Top 10 (2023)

| Risiko | Kontrol di STU LMS |
|---|---|
| API1 Broken Object Level Authorization | Scope organisasi pada API key + policy per objek + RLS (SEC-API-03, [03](03-otorisasi-dan-isolasi-tenant.md)) |
| API2 Broken Authentication | API key kuat, hash, IP allowlist, kedaluwarsa, header saja (SEC-API-01..02) |
| API3 Broken Object Property Level Authorization | Resource/transformer eksplisit; field PII di balik scope `members:read_pii`; input via FormRequest (SEC-AUTHZ-22, SEC-INPUT-10) |
| API4 Unrestricted Resource Consumption | Rate limit per kunci/IP, paginasi maks. 100, batas body, kuota harian, timeout (SEC-API-05) |
| API5 Broken Function Level Authorization | Scope per endpoint; endpoint admin tidak diekspos via API (SEC-API-04) |
| API6 Unrestricted Access to Sensitive Business Flows | Bulk enrollment dibatasi kuota & kontrak; verifikasi anti-enumerasi (SEC-API-06, SEC-CERT-12) |
| API7 Server Side Request Forgery | `SafeHttpClient` untuk webhook keluar (SEC-INPUT-15) |
| API8 Security Misconfiguration | Header, CORS ketat, error generik, TLS, debug off (SEC-API-08..10) |
| API9 Improper Inventory Management | OpenAPI sebagai sumber kebenaran; inventaris endpoint & versi; endpoint tak terdokumentasi ditolak CI (SEC-API-11) |
| API10 Unsafe Consumption of APIs | Validasi respons pihak ketiga (gateway, IdP, WA, email), timeout, circuit breaker, tidak mempercayai redirect/URL dari pihak ketiga (SEC-API-12) |

## 2. Kebutuhan API Mitra

| ID | Kebutuhan |
|---|---|
| SEC-API-01 | API key = prefiks identifikasi + 256-bit acak; ditampilkan **sekali** saat dibuat (dengan tombol salin & peringatan); disimpan `HMAC-SHA256(pepper, key)`; prefiks `stu_live_`/`stu_test_` memungkinkan **secret scanning** (didaftarkan ke GitHub Secret Scanning partner program/pola kustom) sehingga kebocoran di repositori publik terdeteksi. |
| SEC-API-02 | Kunci diterima **hanya** di header `Authorization: Bearer`; kunci di query string/body ditolak & dicatat. Kunci wajib punya `expires_at` (maks. 12 bulan); notifikasi ke kontak mitra H-30 & H-7; rotasi tanpa downtime (dua kunci aktif bersamaan maks. 14 hari). |
| SEC-API-03 | Setiap kunci terikat ke satu `api_client` → satu organisasi; semua query API memakai konteks tenant organisasi tersebut (global scope + RLS) — mitra tidak pernah dapat mengakses data organisasi lain meskipun menebak ID. |
| SEC-API-04 | **Scope minimal** per kunci (lihat [`../06-spesifikasi-api.md`](../06-spesifikasi-api.md) §3.1); scope tulis mensyaratkan **IP allowlist**; scope `members:read_pii` mensyaratkan DPA terdokumentasi. Tidak ada endpoint API untuk fungsi admin platform (approval sertifikat, refund, peran). |
| SEC-API-05 | Rate limit per kunci & per IP, kuota harian, batas paginasi, batas ukuran body; header `RateLimit-*`; respons 429 dengan `Retry-After`. Lonjakan pemakaian (> 3× baseline) → alert. |
| SEC-API-06 | Operasi bisnis sensitif via API (enrollment massal) dibatasi kontrak organisasi (kuota peserta, program yang ditanggung) dan kuota per jam; idempotensi wajib (`Idempotency-Key`). |
| SEC-API-07 | Pencabutan kunci efektif **seketika** (cache validasi kunci ≤ 60 detik atau invalidasi langsung); pencabutan & pembuatan tercatat di audit; kunci tidak aktif 90 hari ditandai untuk ditinjau. |
| SEC-API-08 | CORS: tidak ada untuk API mitra; `verify` publik `*` tanpa kredensial. Tidak ada cookie pada respons API. `Cache-Control: no-store` untuk data pribadi. |
| SEC-API-09 | Respons error generik (RFC 9457) tanpa detail internal; 401 tidak membedakan "kunci tidak ada" vs "kunci dicabut" vs "kedaluwarsa". |
| SEC-API-10 | Log API mencatat: prefiks kunci (bukan kunci), klien, endpoint, status, latensi, IP, `request_id` — tanpa body berisi data pribadi. |
| SEC-API-11 | **Inventaris**: `docs/api/openapi.yaml` adalah daftar resmi; uji CI membandingkan rute `/api/*` terdaftar dengan OpenAPI — rute tidak terdokumentasi gagal build. Versi lama dimatikan sesuai kebijakan deprecation. |

## 3. Webhook Keluar

| ID | Kebutuhan |
|---|---|
| SEC-API-13 | Payload ditandatangani HMAC-SHA256 dengan secret per endpoint (dibuat sistem, ditampilkan sekali) + timestamp; dokumentasi contoh verifikasi untuk mitra (PHP, Node, Python, Java). |
| SEC-API-14 | URL tujuan divalidasi anti-SSRF saat disimpan **dan** setiap pengiriman (SEC-INPUT-15); tanpa mengikuti redirect; timeout 10 detik; payload minimal (ID & data non-sensitif; mitra mengambil detail via API bila perlu). |
| SEC-API-15 | Retry dengan backoff eksponensial & jitter; *dead letter* setelah 6 gagal; endpoint otomatis dinonaktifkan setelah 3 hari gagal berturut-turut + notifikasi. |

## 4. Integrasi Masuk & Konsumsi API Pihak Ketiga

| ID | Kebutuhan |
|---|---|
| SEC-API-12 | Setiap klien integrasi (Midtrans, email, WA, IdP OIDC, Zoom/Teams kelak, HIBP, CAPTCHA) dibungkus *adapter* dengan: timeout (connect 3 detik, total 10 detik), retry terbatas hanya untuk operasi idempoten, *circuit breaker*, validasi skema respons, verifikasi TLS aktif (tidak pernah `verify => false`), dan logging tanpa rahasia. |
| SEC-API-16 | Webhook masuk dari penyedia lain (status pengiriman email/WA) diverifikasi tanda tangannya sesuai dokumentasi penyedia; bila penyedia tidak mendukung tanda tangan, gunakan URL dengan token rahasia panjang + IP allowlist, dan data webhook hanya dipakai untuk informasi non-kritis. |
| SEC-API-17 | OIDC/SAML: lihat [02](02-autentikasi-dan-sesi.md) SEC-AUTH-27. Redirect URI terdaftar persis (tanpa wildcard); client secret di secret manager. |
| SEC-API-18 | **Penilaian vendor**: setiap penyedia pihak ketiga yang memproses data pribadi dinilai (lokasi data, sertifikasi ISO 27001/SOC 2, DPA, sub-prosesor, notifikasi insiden) dan dicatat di RoPA ([12](12-privasi-data-dan-kepatuhan-uu-pdp.md)). Data yang dikirim ke vendor diminimalkan (mis. WA hanya nomor & isi template). |
| SEC-API-19 | Template WhatsApp & email hanya berisi informasi minimal dan tautan ke aplikasi (login wajib) — tidak berisi token login/"magic link" sekali klik ke aksi sensitif, skor detail, atau data pribadi pihak lain. |

## 5. Integrasi HRIS / Sistem Akademik (Sinkronisasi)

- Sinkronisasi data anggota dari sistem organisasi (Fase 3+) memakai **pull** oleh mitra via API atau impor berkas terjadwal; STU tidak menyimpan kredensial ke sistem internal mitra kecuali dengan perjanjian & enkripsi K4.
- Field hasil sinkronisasi bersifat baca-saja di UI (FR-USER-005) dan setiap perubahan tercatat (sumber, waktu).
- Penghapusan anggota di sistem mitra → status `removed` di organisasi (bukan penghapusan akun peserta; akun & sertifikat tetap milik peserta sesuai UU PDP).
