# 04 — Validasi Input, Encoding Output & Injeksi

> Status: **Disetujui untuk development** · Versi 1.0 · Pemilik: Security Lead
>
> Acuan: ASVS v5.0 V1 (Encoding & Sanitization), V2 (Validation & Business Logic), V3 (Web
> Frontend Security), V4 (API & Web Service); OWASP XSS/SQLi/SSRF/CSRF Prevention Cheat Sheets.
> Prinsip: **validasi masukan dengan allowlist, encode keluaran sesuai konteks, jangan pernah
> membangun kode (HTML/SQL/shell) dengan string.**

## 1. Cross-Site Scripting (XSS)

| ID | Kebutuhan |
|---|---|
| SEC-INPUT-01 | Semua keluaran di Blade memakai `{{ }}` (auto-escape HTML). `{!! !!}` **dilarang** kecuali untuk keluaran komponen `<x-safe-html>` yang hanya menerima konten hasil sanitizer (SEC-INPUT-11). Aturan Larastan/Semgrep memblokir `{!!` di luar komponen tersebut. |
| SEC-INPUT-02 | Konteks non-HTML di-encode sesuai konteks: atribut (tetap `{{ }}`, selalu dalam tanda kutip), URL (`e(url(...))` + validasi skema `https`/relatif), JavaScript (data dikirim via `@js()`/`Js::from()` atau atribut `data-*`, **bukan** interpolasi string ke dalam `<script>`), CSS (tidak ada nilai pengguna di CSS; warna branding divalidasi regex `^#[0-9a-fA-F]{6}$`). |
| SEC-INPUT-03 | Alpine.js: `x-html` dilarang untuk data yang berasal dari pengguna; gunakan `x-text`. Tidak ada `innerHTML`, `outerHTML`, `insertAdjacentHTML`, `document.write`, `eval`, `new Function`, `setTimeout(string)` di kode frontend (lint rule `no-unsanitized`, `no-eval`). |
| SEC-INPUT-04 | **Content Security Policy** ketat berbasis nonce (lihat [13](13-keamanan-infrastruktur.md) §3): tanpa `unsafe-inline` untuk skrip, tanpa `unsafe-eval` (atau diisolasi ke rute yang memerlukannya dengan ADR), `object-src 'none'`, `base-uri 'none'`, `frame-ancestors 'none'`. Laporan pelanggaran CSP dikirim ke endpoint `report-to` dan dipantau. |
| SEC-INPUT-05 | Nama pengguna, nama berkas, judul thread/kelas/program, isi diskusi, konten CMS, catatan, feedback, dan semua teks bebas diperlakukan **tidak tepercaya** di semua kanal keluaran: halaman web, email (template Blade ter-escape), notifikasi WA, PDF sertifikat/invoice (di-escape sebelum masuk template HTML→PDF), ekspor CSV/XLSX, dan log. |
| SEC-INPUT-06 | Respons JSON selalu `Content-Type: application/json` dengan `X-Content-Type-Options: nosniff`; tidak ada JSONP; respons error tidak memantulkan input mentah. |

## 2. Rich Text & Konten Terformat

| ID | Kebutuhan |
|---|---|
| SEC-INPUT-11 | Konten kaya (deskripsi program, isi lesson teks, soal & pembahasan, deskripsi tugas) disimpan dalam dua bentuk: sumber (Markdown/JSON editor) dan HTML hasil **sanitasi di server** dengan **HTMLPurifier** (atau `symfony/html-sanitizer`) memakai allowlist: `p, br, strong, em, u, s, ul, ol, li, blockquote, code, pre, h2–h4, a[href|title], img[src|alt|width|height], table, thead, tbody, tr, th, td`. Atribut `style`, `on*`, `class` (kecuali daftar kelas tertentu), `iframe`, `form`, `svg`, `math` **tidak** diizinkan. `a[href]` hanya `https:`/`mailto:`/relatif, ditambah `rel="noopener noreferrer nofollow ugc"` & `target="_blank"`. `img[src]` hanya dari domain media internal. Sanitasi dijalankan ulang saat konfigurasi allowlist berubah (migrasi data). |
| SEC-INPUT-11a | Diskusi memakai **Markdown subset** (tebal, miring, kode, daftar, tautan) dirender dengan parser yang menonaktifkan HTML mentah (`html_input: 'escape'`, `allow_unsafe_links: false` di league/commonmark), lalu disanitasi ulang. |
| SEC-INPUT-11b | CMS beranda **tidak** menerima HTML. Skema konten JSON: teks biasa + penanda sorotan (`{"text":"Internasional","highlight":true}`) yang dirender oleh template. |
| SEC-INPUT-11c | Embed video eksternal (YouTube/Vimeo) hanya melalui komponen yang membangun URL embed dari **ID video** tervalidasi, bukan dari HTML embed yang ditempel pengguna. |

## 3. Validasi Input Umum

| ID | Kebutuhan |
|---|---|
| SEC-INPUT-09 | Semua input divalidasi di server melalui **FormRequest**/validator dengan aturan eksplisit: tipe, panjang min/maks, format (regex *anchored*), rentang, enum (`Rule::enum`), UUID (`uuid`), tanggal, dan **allowlist field** (field tak dikenal diabaikan & dapat dicatat). Batas panjang standar: nama ≤ 120, email ≤ 254, judul ≤ 200, teks bebas ≤ 10.000, isi diskusi ≤ 5.000 karakter. |
| SEC-INPUT-09a | Normalisasi Unicode NFKC untuk nama & kode; tolak karakter kontrol (kecuali baris baru pada teks bebas), karakter *bidi override* (U+202A–U+202E, U+2066–U+2069) pada nama/judul (mencegah spoofing tampilan & nama berkas). Email dinormalisasi lowercase & divalidasi `email:rfc,strict` (tanpa DNS lookup sinkron di jalur request). |
| SEC-INPUT-09b | Angka uang/skor divalidasi sebagai integer/desimal dalam rentang wajar; tidak menerima notasi ilmiah, negatif, atau `NaN`. |
| SEC-INPUT-10 | **Mass assignment**: controller/action hanya memakai `$request->validated()` atau DTO; model mendefinisikan `$fillable` eksplisit (dilarang `$guarded = []`); `Model::preventSilentlyDiscardingAttributes()` aktif di non-produksi. Field sensitif (`role`, `status`, `organization_id`, `user_id`, `score`, `price`, `approved_by`, `is_correct`, `email_verified_at`) **tidak pernah** ada di `$fillable` untuk input pengguna; diisi oleh service. Kehadiran field terlarang di request dicatat sebagai `security_events.suspicious_parameter`. |
| SEC-INPUT-22 | Regex yang diterapkan pada input pengguna harus bebas *catastrophic backtracking* (ReDoS) — ditinjau, panjang input dibatasi sebelum regex. |
| SEC-INPUT-23 | Batas ukuran request: body umum 1 MB, JSON kedalaman ≤ 32, array ≤ 1.000 elemen; unggahan mengikuti [05](05-keamanan-berkas-dan-media.md). Diterapkan di Nginx (`client_max_body_size` per lokasi) dan aplikasi. |

## 4. SQL & Injeksi Lain

| ID | Kebutuhan |
|---|---|
| SEC-INPUT-08 | Semua query melalui Eloquent/Query Builder dengan *parameter binding*. `DB::raw`, `whereRaw`, `orderByRaw`, `selectRaw` hanya dengan binding dan tanpa interpolasi input; nama kolom/arah sort dari input **wajib** melalui allowlist map (`['issued_at' => 'issued_at', ...]`). Semgrep rule memblokir interpolasi variabel ke fungsi `*Raw`. Pencarian `LIKE` meng-escape `%` & `_`. |
| SEC-INPUT-20 | Tidak ada `exec`, `shell_exec`, `system`, `passthru`, `proc_open`, backtick. Pemanggilan proses eksternal (FFmpeg, ClamAV, Chromium) hanya via `Symfony\Process`/`Illuminate\Process` dengan **argumen array** (tanpa shell), path yang dibangkitkan sistem, dan timeout. |
| SEC-INPUT-19 | Tidak ada `unserialize()` pada data yang dapat dipengaruhi pengguna (gunakan JSON). Cookie terenkripsi Laravel tetap; `APP_KEY` dirahasiakan (kebocoran `APP_KEY` = potensi RCE via deserialisasi — lihat [06](06-kriptografi-dan-manajemen-kunci.md)). |
| SEC-INPUT-21 | Pemrosesan XML/DOCX/XLSX memakai pustaka yang menonaktifkan entitas eksternal (XXE) — `libxml` modern default aman; **dilarang** `LIBXML_NOENT`/`LIBXML_DTDLOAD`. Impor XLSX memakai pustaka (mis. OpenSpout/PhpSpreadsheet read-only) dengan batas baris/kolom/ukuran; makro diabaikan. |
| SEC-INPUT-24 | Tidak ada evaluasi template dari input pengguna (mis. `Blade::render($userString)`). Template sertifikat & email yang dapat diubah admin memakai **placeholder terbatas** (`{{holder_name}}`, `{{program_name}}`, …) yang diganti dengan nilai ter-escape — bukan Blade/Twig penuh. |
| SEC-INPUT-14 | **Header/email injection**: nilai yang masuk ke header HTTP, `Subject`, `To`, nama berkas unduhan (`Content-Disposition`) dibersihkan dari CR/LF; nama berkas unduhan dibangkitkan sistem + `filename*` RFC 5987. |
| SEC-INPUT-12 | **Formula/CSV injection**: pada ekspor CSV/XLSX, sel yang diawali `=`, `+`, `-`, `@`, TAB (`0x09`), CR (`0x0D`) diawali tanda kutip tunggal `'`; ekspor dibuat di server dengan pustaka yang meng-escape tanda kutip & pemisah. Berlaku untuk semua ekspor & laporan kesalahan impor. |
| SEC-INPUT-18 | **Path traversal**: tidak ada path berkas dari input pengguna. Akses berkas selalu via ID → `storage_key` dari DB. Nama berkas asli hanya untuk tampilan (disanitasi) dan tidak pernah dipakai untuk menyimpan. |

## 5. CSRF & Redirect

| ID | Kebutuhan |
|---|---|
| SEC-INPUT-07 | Middleware CSRF Laravel aktif untuk semua rute web yang mengubah state (POST/PUT/PATCH/DELETE) & Livewire. Pengecualian **hanya** `/webhooks/*` (diverifikasi tanda tangan) — daftar pengecualian ditinjau. Operasi berubah-state **tidak pernah** via GET. Cookie `SameSite=Lax` sebagai lapisan tambahan; header `Origin`/`Sec-Fetch-Site` diperiksa untuk aksi sensitif (tolak `cross-site`). |
| SEC-INPUT-13 | **Open redirect**: parameter `redirect`/`intended`/`next` hanya menerima path relatif internal yang divalidasi (awalan `/`, bukan `//`, bukan `/\`, tanpa skema) atau nama rute dari allowlist; selain itu redirect ke dashboard. `redirect()->intended()` dibatasi ke host sendiri. |

## 6. Server-Side Request Forgery (SSRF)

| ID | Kebutuhan |
|---|---|
| SEC-INPUT-15 | Server hanya melakukan request keluar ke URL yang dipengaruhi pengguna melalui **`SafeHttpClient`**: hanya `https`, port 443, *resolve* DNS lalu tolak IP privat/loopback/link-local/multicast/CGNAT/`0.0.0.0`/IPv6 ULA & mapped, alamat metadata cloud (`169.254.169.254`, `fd00:ec2::254`), dan hostname internal; koneksi ke **IP yang sudah divalidasi** (mencegah DNS rebinding); tidak mengikuti redirect (atau memvalidasi ulang setiap hop, maks. 3); timeout 5 detik; ukuran respons maks. 1 MB. |
| SEC-INPUT-16 | Fitur yang menyimpan URL pengguna **tanpa** di-*fetch* server (tautan live class, tautan eksternal lesson) tetap divalidasi: skema `https`, domain allowlist per jenis (live class: `zoom.us`, `*.zoom.us`, `meet.google.com`, `teams.microsoft.com`, `teams.live.com`), tanpa kredensial di URL, panjang ≤ 2.048. Server **tidak** membuat pratinjau (unfurl) URL pengguna. |
| SEC-INPUT-17 | Renderer PDF & transcoder berjalan tanpa akses jaringan keluar (lihat [05](05-keamanan-berkas-dan-media.md), [07](07-integritas-sertifikat.md)); egress jaringan app/worker dibatasi allowlist di tingkat infrastruktur ([13](13-keamanan-infrastruktur.md)). |

## 7. Penanganan Error

| ID | Kebutuhan |
|---|---|
| SEC-INPUT-27 | `APP_DEBUG=false` di staging & produksi (aplikasi menolak *boot* bila `true` di produksi). Halaman error kustom (403/404/419/429/500/503) tanpa stack trace, versi, path, atau query SQL; menampilkan `request_id` untuk dukungan. Exception dicatat lengkap di server (tanpa rahasia). |
| SEC-INPUT-28 | Pesan validasi tidak mengungkap data pihak lain (mis. "email sudah dipakai oleh X"). |

## 8. Checklist Cepat untuk Developer

- [ ] Input lewat FormRequest dengan aturan lengkap & `validated()` saja.
- [ ] Tidak ada `{!! !!}`, `x-html`, `innerHTML`, `*Raw` dengan interpolasi.
- [ ] ID relasi dari request dicek dalam scope.
- [ ] URL pengguna lewat `SafeHttpClient` atau divalidasi allowlist.
- [ ] Ekspor lewat `SafeSpreadsheetWriter` (anti formula injection).
- [ ] Teks pengguna yang masuk ke PDF/email/WA di-escape.
- [ ] Redirect hanya internal.
- [ ] Uji XSS/SQLi/CSRF/SSRF untuk fitur baru ditambahkan di `tests/Security`.
