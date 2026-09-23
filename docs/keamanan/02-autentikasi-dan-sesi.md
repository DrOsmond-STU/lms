# 02 — Autentikasi, MFA & Manajemen Sesi

> Status: **Disetujui untuk development** · Versi 1.0 · Pemilik: Security Lead
>
> Acuan: OWASP ASVS v5.0 (V6 Authentication, V7 Session Management), NIST SP 800-63B,
> OWASP Authentication / Session Management / Forgot Password / MFA Cheat Sheets.

## 1. Kata Sandi

| ID | Kebutuhan |
|---|---|
| SEC-AUTH-01 | Kata sandi di-*hash* dengan **Argon2id** (`memory_cost ≥ 64 MiB`, `time_cost ≥ 3`, `threads = 1–2`; kalibrasi agar ± 250–500 ms di server produksi). Parameter disimpan di hash sehingga dapat di-*upgrade*; *rehash* otomatis saat login bila parameter berubah (`Hash::needsRehash`). Tidak ada MD5/SHA-1/SHA-256 polos/bcrypt tanpa alasan. |
| SEC-AUTH-02 | Kebijakan (NIST 800-63B): panjang **min. 8** (peserta) / **min. 12** (trainer & semua admin); maks. 128 karakter; semua karakter Unicode & spasi diizinkan (normalisasi NFKC); **tanpa** aturan komposisi paksa; **tanpa** kedaluwarsa berkala (ganti hanya bila ada indikasi kompromi); tolak kata sandi yang sama dengan email/nama, daftar kata umum, dan pola berulang. Indikator kekuatan (zxcvbn) di UI. |
| SEC-AUTH-08 | Pemeriksaan kata sandi bocor via **k-anonymity** (Have I Been Pwned Pwned Passwords range API — hanya 5 karakter prefiks SHA-1 yang dikirim) saat registrasi, ganti, dan reset. Bila layanan tidak tersedia: *fail open* untuk peserta dengan pencatatan, *fail closed* untuk admin (coba ulang). |
| SEC-AUTH-34 | Kata sandi tidak pernah dicatat di log, dikirim lewat email, atau ditampilkan. Admin tidak dapat menetapkan kata sandi pengguna (gunakan undangan). Field kata sandi `autocomplete="new-password"`/`current-password` agar password manager berfungsi; *paste* diizinkan. |

## 2. Perlindungan Brute Force & Enumerasi

| ID | Kebutuhan |
|---|---|
| SEC-AUTH-03 | **Rate limit per akun**: setelah 5 gagal dalam 15 menit → penundaan progresif (2, 4, 8, … hingga 15 menit); setelah 20 gagal dalam 24 jam → akun terkunci sementara 1 jam + email ke pemilik berisi tautan "bukan saya → amankan akun". Penguncian **tidak** mengungkapkan status akun ke penyerang (pesan tetap generik). |
| SEC-AUTH-04 | **Rate limit per IP** dan per `/24` (IPv4) / `/64` (IPv6): 30 percobaan login/menit; pemblokiran otomatis di WAF untuk IP yang melampaui ambang *credential stuffing* (mis. > 100 akun berbeda / 10 menit). |
| SEC-AUTH-05 | Rate limit disimpan di Redis dengan kunci ter-hash (`HMAC(email)`), tidak dapat di-reset oleh klien (bukan cookie). |
| SEC-AUTH-06 | **Anti-enumerasi**: login, registrasi, lupa kata sandi, dan kirim ulang OTP memberikan pesan & kode status **identik** untuk akun ada/tidak ada ("Jika email terdaftar, kami telah mengirimkan …"). Waktu respons diseragamkan (verifikasi *dummy hash* bila akun tidak ada; pengiriman email via antrian). Registrasi dengan email yang sudah terdaftar → kirim email "akun sudah ada" ke pemilik, UI tetap sama. |
| SEC-AUTH-07 | **CAPTCHA adaptif** (Cloudflare Turnstile atau setara yang ramah privasi) pada login, registrasi, lupa kata sandi, dan verifikasi sertifikat publik — aktif otomatis ketika sinyal risiko tinggi (gagal berulang, reputasi IP buruk, volume tinggi). Token CAPTCHA diverifikasi di server. |

## 3. OTP (Verifikasi Email/HP)

| ID | Kebutuhan |
|---|---|
| SEC-AUTH-09 | OTP 6 digit dari **CSPRNG** (`random_int`), disimpan sebagai hash (SHA-256 + pepper), berlaku **10 menit**, **maks. 5 percobaan** per kode (lalu kode hangus), kirim ulang maks. 3/jam per tujuan & 10/jam per IP, jeda kirim ulang 60 detik. OTP terikat pada tujuan & tujuan pemakaian (`purpose`). Kode tidak pernah ditampilkan di UI/log. OTP SMS/WA **bukan** faktor MFA untuk admin (rentan SIM swap) — hanya untuk verifikasi kontak. |

## 4. Autentikasi Multi-Faktor (MFA)

| ID | Kebutuhan |
|---|---|
| SEC-AUTH-10 | MFA **wajib** untuk `super_admin`, `academic_admin`, `finance_admin`, `support_admin`, `org_admin`, `trainer`. Pengguna dengan peran tersebut dipaksa mendaftarkan MFA pada login pertama sebelum dapat mengakses fitur apa pun. MFA opsional (dianjurkan dengan banner) untuk peserta. Kebijakan tidak dapat dilonggarkan lewat pengaturan. |
| SEC-AUTH-11 | **WebAuthn/Passkey** (phishing-resistant) didukung; **wajib** untuk `super_admin` sejak Fase 3 (minimal 2 autentikator terdaftar). `userVerification = required`, `attestation = none`, RP ID = domain produksi. |
| SEC-AUTH-13 | **TOTP** (RFC 6238, SHA-1, 6 digit, 30 detik — kompatibel aplikasi umum): secret 160-bit CSPRNG, disimpan **terenkripsi** (K4); toleransi ±1 langkah; **anti-replay** dengan menyimpan `last_totp_step`; verifikasi dibatasi 5 percobaan/5 menit; pendaftaran memerlukan konfirmasi kode pertama & re-auth kata sandi. QR pendaftaran dibuat di server & tidak di-cache. |
| SEC-AUTH-14 | **Kode pemulihan**: 10 kode × 10 karakter acak, ditampilkan **sekali** (dapat diunduh), disimpan sebagai hash, sekali pakai; pemakaian memicu email notifikasi; regenerasi membatalkan kode lama. |
| SEC-AUTH-35 | Menonaktifkan MFA/menghapus faktor memerlukan re-auth + faktor yang masih ada, memicu email ke pengguna, dan tidak diizinkan bagi peran wajib-MFA (hanya penggantian). |
| SEC-AUTH-36 | Status "MFA terverifikasi" disimpan di sesi server dengan waktu (`mfa_verified_at`); langkah login kedua tidak dapat dilewati dengan membuka URL dashboard langsung (middleware `mfa` memeriksa state). Status `mfa_pending` berumur 5 menit. |

## 5. Sesi

| ID | Kebutuhan |
|---|---|
| SEC-AUTH-15 | Sesi **server-side** (Redis), ID sesi 256-bit acak (bawaan Laravel). Tidak ada token otentikasi di `localStorage`/`sessionStorage`. Tabel `user_sessions` menyimpan **hash** ID sesi untuk fitur "Sesi & Perangkat". |
| SEC-AUTH-16 | Cookie sesi: nama berawalan `__Host-` (mis. `__Host-stu_session`), `Secure`, `HttpOnly`, `SameSite=Lax`, `Path=/`, tanpa atribut `Domain`. Cookie CSRF `XSRF-TOKEN` juga `Secure`, `SameSite=Lax`. Konfigurasi: `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`, `SESSION_ENCRYPT=true`. |
| SEC-AUTH-17 | **Regenerasi ID sesi** setelah: login, verifikasi MFA, perubahan peran/hak, perpindahan workspace, re-auth. Sesi lama diinvalidasi (mencegah *session fixation*). |
| SEC-AUTH-18 | **Timeout**: idle 30 menit (admin/trainer), 2 jam (peserta) — kecuali selama attempt ujian aktif (sesi diperpanjang sampai `deadline_at` + 5 menit); absolut 12 jam (admin 8 jam). Peringatan 2 menit sebelum idle timeout. Pengaturan hanya dapat diperpendek. |
| SEC-AUTH-19 | **Logout** menghapus sesi di server, cookie, dan (bila ada) remember token. Ganti kata sandi, reset kata sandi, nonaktif akun, reset MFA, atau pencabutan peran → **cabut semua sesi lain** dan remember token pengguna itu. |
| SEC-AUTH-20 | Batas sesi bersamaan: admin platform maks. 2 sesi aktif (sesi tertua dicabut + notifikasi); peserta tak dibatasi, kecuali selama ujian (lihat SEC-EXAM-14). |
| SEC-AUTH-28 | **"Ingat saya"**: dinonaktifkan untuk semua peran admin & trainer; untuk peserta maks. 30 hari dengan token yang dirotasi pada setiap pemakaian (deteksi pencurian token → cabut semua). |

## 6. Pemulihan Akun & Perubahan Kredensial

| ID | Kebutuhan |
|---|---|
| SEC-AUTH-21 | Token reset kata sandi: 256-bit CSPRNG, disimpan **hash** (SHA-256), **sekali pakai**, berlaku **60 menit**, hanya token terbaru yang valid, dibatalkan saat login berhasil/ganti kata sandi. Tautan tidak memuat email/ID pengguna dalam bentuk yang dapat ditebak. |
| SEC-AUTH-22 | Tautan di email dibangun dari `APP_URL` (konfigurasi), **tidak** dari header `Host`/`X-Forwarded-Host` (mencegah *password reset poisoning*). Laravel `TrustHosts` dikonfigurasi hanya untuk domain resmi. |
| SEC-AUTH-23 | Setelah reset: tidak ada login otomatis bagi peran wajib-MFA (harus login + MFA); semua sesi dicabut; email notifikasi dikirim. Halaman reset menyetel `Referrer-Policy: no-referrer` agar token tidak bocor ke pihak ketiga. |
| SEC-AUTH-24 | Ganti email: verifikasi ke email baru (token 24 jam) + notifikasi ke email lama dengan tautan pembatalan 72 jam; re-auth diperlukan; email baru belum aktif untuk login sampai terverifikasi. |
| SEC-AUTH-25 | **Re-autentikasi** (kata sandi + MFA bila aktif) bila autentikasi terakhir > 15 menit sebelum: ubah email/kata sandi/MFA, setujui/cabut sertifikat, refund, buat/cabut API key, ubah peran, ubah pengaturan keamanan/integrasi, ekspor data massal, proses permintaan privasi. Menggunakan middleware `password.confirm` yang diperluas untuk MFA. |
| SEC-AUTH-26 | **Reset MFA oleh dukungan** (pengguna kehilangan perangkat & kode pemulihan): tiket resmi, verifikasi identitas (mis. panggilan video + dokumen, atau konfirmasi Admin Organisasi yang terverifikasi), dilakukan oleh `super_admin`/`support_admin` dengan **persetujuan kedua** untuk akun admin, jeda 24 jam untuk akun admin sebelum efektif (dengan notifikasi ke email pengguna), tercatat di audit. |

## 7. SSO (OIDC / SAML)

| ID | Kebutuhan |
|---|---|
| SEC-AUTH-27 | OIDC Authorization Code Flow + **PKCE**; validasi `state` (anti-CSRF) dan `nonce` (anti-replay); validasi `iss`, `aud`, `exp`, `iat`, tanda tangan ID token (JWKS di-cache & dirotasi); hanya terima `email_verified = true`; akun ditautkan hanya bila domain email termasuk domain terverifikasi organisasi yang mengaktifkan SSO **dan** (untuk akun yang sudah ada) pengguna mengonfirmasi penautan dengan login kata sandi sekali. Penautan otomatis semata berdasarkan email **dilarang** (mencegah *account takeover* via IdP lain). SAML: validasi tanda tangan assertion & response, `Audience`, `NotOnOrAfter`, cegah *XML signature wrapping* (pustaka teruji), `InResponseTo` sekali pakai. Admin platform **tidak** boleh login hanya via SSO pihak ketiga tanpa MFA platform (kecuali IdP menjamin MFA dan klaim `amr` diperiksa). |

## 8. Notifikasi & Deteksi

| ID | Kebutuhan |
|---|---|
| SEC-AUTH-12 | Email notifikasi untuk: login dari perangkat/lokasi baru (berdasarkan *device cookie* acak + ASN/negara), kegagalan login beruntun, perubahan kata sandi/email/MFA, pemakaian kode pemulihan, pembuatan API key, penetapan peran admin. Email berisi tautan "Bukan saya" yang mencabut semua sesi & memaksa reset. |
| SEC-AUTH-30 | Event keamanan (login gagal/berhasil, MFA gagal, lockout, reset, perubahan kredensial) dicatat ke `security_events` & SIEM (lihat [11](11-logging-audit-dan-monitoring.md)). Deteksi *impossible travel* dan lonjakan kegagalan memicu alert. |

## 9. Akun Khusus & Siklus Hidup

| ID | Kebutuhan |
|---|---|
| SEC-AUTH-29 | Menonaktifkan akun mencabut sesi, remember token, dan API key yang dibuat akun itu (ditinjau). Akun admin/trainer tidak aktif 180 hari dinonaktifkan otomatis. |
| SEC-AUTH-31 | (Opsional, disarankan) IP allowlist atau akses via VPN/Zero-Trust (Cloudflare Access) untuk area `/admin` & Horizon. Bila diaktifkan, pengecualian darurat dicatat. |
| SEC-AUTH-32 | **Tidak ada akun default/demo** di produksi. Super Admin pertama dibuat oleh perintah artisan sekali jalan (`stu:bootstrap-admin`) dengan email dari secret manager → undangan set kata sandi + MFA wajib; perintah menolak berjalan bila sudah ada Super Admin. |
| SEC-AUTH-33 | Akun layanan/sistem (mis. untuk job) tidak dapat login interaktif. |

## 10. Pesan & UX Keamanan

| Situasi | Pesan ke pengguna |
|---|---|
| Login gagal (email tak ada/kata sandi salah/terkunci) | "Email atau kata sandi salah." (+ tautan lupa kata sandi) |
| Terlalu banyak percobaan | "Terlalu banyak percobaan. Coba lagi dalam beberapa menit." |
| Lupa kata sandi | "Jika email tersebut terdaftar, kami telah mengirimkan tautan untuk mengatur ulang kata sandi." |
| OTP salah | "Kode tidak valid atau sudah kedaluwarsa." |
| MFA gagal | "Kode autentikasi tidak valid." |

## 11. Konfigurasi Laravel Terkait (Acuan)

```php
// config/hashing.php
'driver' => 'argon2id',
'argon' => ['memory' => 65536, 'threads' => 1, 'time' => 3, 'verify' => true],

// config/session.php
'driver' => 'redis', 'lifetime' => 30, 'expire_on_close' => false,
'encrypt' => true, 'cookie' => '__Host-stu_session', 'path' => '/', 'domain' => null,
'secure' => true, 'http_only' => true, 'same_site' => 'lax',
```

Paket yang disarankan (dievaluasi ulang saat kick-off, sesuai kebijakan dependensi): Laravel
Fortify (alur auth headless, 2FA TOTP) atau implementasi sendiri di modul `Identity` di atasnya;
`web-auth/webauthn-lib` atau `laragear/webauthn` untuk WebAuthn; Laravel Socialite untuk OIDC.

## 12. Pengujian Wajib

Semua kebutuhan di atas memiliki uji di `tests/Security/Auth/*` — termasuk: enumerasi (respons &
waktu), lockout, replay TOTP, kode pemulihan sekali pakai, fixation (ID sesi berubah), logout
invalidasi, reset token sekali pakai/kedaluwarsa, host header poisoning, bypass langkah MFA via
URL langsung, cookie flags. Lihat [`../10-strategi-pengujian.md`](../10-strategi-pengujian.md).
