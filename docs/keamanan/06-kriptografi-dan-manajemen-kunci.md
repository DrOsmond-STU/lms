# 06 — Kriptografi, Manajemen Kunci & Rahasia

> Status: **Disetujui untuk development** · Versi 1.0 · Pemilik: Security Lead
>
> Acuan: ASVS v5.0 V11 (Cryptography), V12 (Secure Communication), V13 (Configuration);
> NIST SP 800-57 (manajemen kunci), OWASP Cryptographic Storage & Secrets Management Cheat Sheets.
> **Aturan emas: jangan membuat algoritma/protokol kriptografi sendiri; gunakan pustaka standar
> (libsodium/OpenSSL via API Laravel) dan KMS.**

## 1. Enkripsi Saat Transit

| ID | Kebutuhan |
|---|---|
| SEC-CRYPTO-01 | TLS **1.2 minimum**, TLS 1.3 diutamakan, di edge (CDN) dan origin. Cipher suite TLS 1.2 hanya AEAD dengan forward secrecy (ECDHE + AES-GCM / ChaCha20-Poly1305). SSLv3/TLS 1.0/1.1, RC4, 3DES, CBC-SHA1, kompresi TLS dinonaktifkan. Target nilai **A+** di SSL Labs. |
| SEC-CRYPTO-02 | **HSTS** `max-age=63072000; includeSubDomains; preload` setelah semua subdomain siap HTTPS; domain didaftarkan ke HSTS preload list. |
| SEC-CRYPTO-03 | Koneksi internal terenkripsi: aplikasi → PostgreSQL (`sslmode=verify-full`), → Redis (TLS), → object storage (HTTPS), → KMS (HTTPS). Edge → origin memakai sertifikat origin + *authenticated origin pulls* (mTLS). |
| SEC-CRYPTO-04 | Sertifikat TLS otomatis diperbarui (ACME/penyedia terkelola), dipantau kedaluwarsanya (alert H-21). CAA record DNS membatasi CA yang boleh menerbitkan. DNSSEC diaktifkan bila registrar mendukung. |

## 2. Hashing

| Data | Algoritma | Catatan |
|---|---|---|
| Kata sandi | **Argon2id** (lihat [02](02-autentikasi-dan-sesi.md) SEC-AUTH-01) | Salt unik otomatis |
| Kode pemulihan MFA | Argon2id | Sekali pakai |
| Token sekali pakai (reset, undangan, OTP, verifikasi) | SHA-256 dari token acak ≥ 128 bit (+ pepper untuk OTP 6 digit karena ruang kecil) | Token acak kuat tidak perlu KDF lambat |
| API key | HMAC-SHA256 dengan *pepper* dari KMS/secret manager | Perbandingan `hash_equals` |
| *Blind index* (pencarian kolom terenkripsi: HP, NIK bila ada) | HMAC-SHA256 dengan kunci terpisah per kolom | Mencegah korelasi antar kolom |
| IP untuk statistik verifikasi | HMAC-SHA256 dengan salt yang dirotasi harian | Pseudonimisasi |
| Integritas berkas & PDF sertifikat | SHA-256 | |
| Rantai audit | SHA-256 | Lihat [11](11-logging-audit-dan-monitoring.md) |

| ID | Kebutuhan |
|---|---|
| SEC-CRYPTO-05 | Dilarang: MD5, SHA-1 (kecuali kompatibilitas TOTP RFC 6238 & HIBP k-anonymity), `crypt()` DES, hash tanpa salt untuk kata sandi, `rand()`, `mt_rand()`, `uniqid()`, `lcg_value()`, `Math.random()` untuk keperluan keamanan. |
| SEC-CRYPTO-06 | Semua nilai acak keamanan dari CSPRNG: `random_bytes`, `random_int`, `Str::random()` (berbasis `random_bytes`), `Str::uuid7()`/ULID. |
| SEC-CRYPTO-07 | Perbandingan rahasia/tanda tangan/token memakai `hash_equals()` (waktu konstan). |

## 3. Enkripsi Saat Disimpan (At Rest)

| Lapis | Mekanisme | Cakupan |
|---|---|---|
| L1 — Disk/volume | Enkripsi volume & snapshot oleh penyedia cloud (AES-256) dengan **customer-managed key** (CMK) di KMS | DB, Redis (bila persisten), volume worker, backup |
| L2 — Object storage | SSE-KMS dengan CMK per bucket; bucket sertifikat & backup memakai kunci terpisah | Semua bucket |
| L3 — Tingkat aplikasi (field) | **Envelope encryption**: DEK per kategori data/tenant dienkripsi oleh KEK di KMS; data dienkripsi AES-256-GCM (Laravel `Crypt`/`encrypted` cast menggunakan AES-256-CBC+HMAC atau AES-256-GCM sesuai konfigurasi `cipher`) | Kolom K4: secret TOTP, kredensial integrasi, tautan & kode sandi live class, NPWP/alamat faktur, jawaban isian singkat (kunci), `accepted_answers`, `qr_secret`, NIK (bila ada), nomor HP |

| ID | Kebutuhan |
|---|---|
| SEC-CRYPTO-08 | Kolom K4 dienkripsi tingkat aplikasi (L3). Konfigurasi `cipher = 'aes-256-gcm'` (atau AES-256-CBC + HMAC-SHA256 bawaan Laravel, keduanya ter-autentikasi). |
| SEC-CRYPTO-09 | Kunci enkripsi field **tidak sama** dengan `APP_KEY` untuk kolom K4 bernilai tinggi (kredensial integrasi, secret TOTP): gunakan *encrypter* terpisah yang DEK-nya di-*unwrap* dari KMS saat boot (di-cache di memori proses, tidak pernah ditulis ke disk/log). |
| SEC-CRYPTO-10 | Rotasi: `APP_KEY` dirotasi tahunan atau saat dicurigai bocor memakai `APP_PREVIOUS_KEYS` + job re-enkripsi bertahap; KEK di KMS rotasi otomatis tahunan; DEK dirotasi saat KEK dicurigai/berkala 2 tahun dengan re-enkripsi. |
| SEC-CRYPTO-11 | Backup dienkripsi dengan kunci **berbeda** dari produksi dan disimpan di akun/proyek terpisah (lihat [13](13-keamanan-infrastruktur.md)). |

## 4. Tanda Tangan Digital & HMAC

| Kegunaan | Algoritma | Kunci |
|---|---|---|
| Tanda tangan PDF sertifikat & invoice (PAdES-B-LT/LTA) | RSA-3072/PSS atau ECDSA P-256/P-384 (sesuai sertifikat penandatangan) + SHA-256; *timestamp* RFC 3161 dari TSA tepercaya | Kunci privat di **KMS/HSM non-exportable** (lihat [07](07-integritas-sertifikat.md)) |
| URL bertanda tangan aplikasi | HMAC-SHA256 (Laravel `URL::signedRoute`) | `APP_KEY` |
| Token QR presensi dinamis | HMAC-SHA256 (`session_id|window|nonce`) | `qr_secret` per sesi |
| Webhook keluar | HMAC-SHA256 `timestamp.body` | Secret per endpoint |
| Webhook Midtrans masuk | SHA-512 sesuai spesifikasi gateway | Server key (secret manager) |
| Signed cookie/URL CDN (HLS) | Sesuai CDN (mis. RSA-SHA1 CloudFront legacy / Ed25519/HMAC pada penyedia lain) | Kunci CDN di secret manager |

## 5. Manajemen Rahasia (Secrets)

| ID | Kebutuhan |
|---|---|
| SEC-CRYPTO-12 | Semua rahasia (DB, Redis, `APP_KEY`, kunci Midtrans, SMTP, WA, OIDC client secret, pepper, kunci CDN, token pihak ketiga) disimpan di **secret manager** (AWS Secrets Manager/GCP Secret Manager/Vault) dan disuntikkan saat runtime. **Tidak ada** rahasia di repositori, image kontainer, argumen build, variabel CI biasa, tiket, chat, atau dokumen. |
| SEC-CRYPTO-13 | Akses rahasia per *workload identity* (IAM role/service account) dengan least privilege: web node tidak dapat membaca kunci penandatangan; worker sertifikat tidak dapat membaca server key Midtrans; dsb. Akses manusia ke rahasia produksi hanya break-glass dan terekam. |
| SEC-CRYPTO-14 | `.env` hanya untuk lokal; `.env.example` berisi nilai dummy. **gitleaks** di pre-commit & CI; *push protection* secret scanning GitHub aktif. Rahasia yang pernah terkomit dianggap bocor → rotasi segera (menghapus commit tidak cukup). |
| SEC-CRYPTO-15 | Jadwal rotasi: kredensial DB 90 hari (atau kredensial dinamis Vault), kunci API pihak ketiga 12 bulan atau sesuai kebijakan penyedia, secret webhook 12 bulan, OIDC client secret 12 bulan, kunci CDN 12 bulan, sertifikat penandatangan sesuai masa berlaku (rotasi sebelum kedaluwarsa). Rotasi darurat ≤ 4 jam saat dicurigai bocor (runbook di [15](15-respons-insiden-dan-kontinuitas.md)). |
| SEC-CRYPTO-16 | Kredensial integrasi yang diinput admin via UI (FR-SET-004) disimpan terenkripsi (SEC-CRYPTO-09), **tidak pernah** ditampilkan ulang (hanya 4 karakter terakhir), perubahan memerlukan re-auth & tercatat di audit. |

## 6. Inventaris Kunci

| Kunci | Lokasi | Pemilik | Rotasi | Dampak bila bocor |
|---|---|---|---|---|
| `APP_KEY` | Secret manager | DevOps Lead | 12 bulan | Pemalsuan cookie/URL bertanda tangan, dekripsi field L3 berbasis APP_KEY, potensi RCE (deserialisasi) → **Kritis** |
| KEK field K4 | KMS | Security Lead | 12 bulan (otomatis) | Dekripsi kolom K4 (bila juga ada akses DB) |
| Kunci penandatangan sertifikat | KMS/HSM (non-exportable) | Security Lead | Sesuai sertifikat (≤ 3 tahun) | Pemalsuan sertifikat → **Kritis** |
| Kunci SSE bucket | KMS | DevOps Lead | 12 bulan (otomatis) | — (butuh akses bucket juga) |
| Kunci backup | KMS akun terpisah | DevOps Lead + Security Lead | 12 bulan | Akses backup |
| Midtrans server key | Secret manager | Finance/DevOps | 12 bulan | Pemalsuan status pembayaran/API → **Tinggi** |
| Pepper API key / OTP | Secret manager | Security Lead | 24 bulan (dengan dukungan multi-pepper) | Serangan offline pada hash |
| Kunci CDN signed URL | Secret manager | DevOps Lead | 12 bulan | Akses materi berbayar |
| OIDC client secret | Secret manager | DevOps Lead | 12 bulan | Penyamaran aplikasi di IdP |

Inventaris ini dipelihara di dokumen operasional internal (bukan repositori) dan ditinjau
triwulanan.

## 7. Kriptografi Sisi Klien

- Tidak ada kriptografi keamanan di browser selain yang disediakan browser (TLS, WebAuthn).
- QR sertifikat & PDF **tidak** dibuat di browser (lihat PROTO-05 di [16](16-temuan-keamanan-purwarupa.md)).

## 8. Kesiapan Pasca-Kuantum

Dipantau; ketika penyedia CDN/TLS dan pustaka PHP mendukung hibrida ML-KEM secara stabil,
aktifkan di edge. Untuk tanda tangan sertifikat jangka panjang, gunakan *long-term validation*
(PAdES-LTA dengan timestamp berkala) agar dapat di-*re-timestamp* dengan algoritma baru.
