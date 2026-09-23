# 16 — Temuan Keamanan pada Purwarupa (Gap Analysis)

> Status: **Disetujui untuk development** · Versi 1.0 · Pemilik: Security Lead
>
> Purwarupa (`*.html`, `assets/js/*`) sengaja dibuat sebagai demo statis tanpa backend. Dokumen
> ini mendaftar pola di purwarupa yang **aman untuk demo tetapi berbahaya bila disalin ke
> produksi**. Setiap temuan punya kontrol pengganti yang wajib (kolom "Kontrol Produksi").
> Tim **dilarang** memporting kode JavaScript `store.js`, `certificate.js`, atau logika
> `<script>` inline halaman ke produksi; yang boleh dipakai ulang hanya **markup & gaya**.

## Ringkasan

| Tingkat | Jumlah |
|---|---|
| Kritis | 9 |
| Tinggi | 10 |
| Sedang | 7 |
| Rendah | 4 |

Tingkat mengacu pada dampak **jika pola yang sama diterapkan di produksi** (CVSS-like: kritis =
kompromi total/pemalsuan/kebocoran massal).

## Daftar Temuan

### Kritis

| ID | Temuan (lokasi) | Dampak bila terbawa | Kontrol Produksi |
|---|---|---|---|
| PROTO-01 | **Autentikasi & peran ditentukan klien.** `login.html` menampilkan tab peran (Peserta/Trainer/Admin) dan `Store.login(role)` langsung membuat sesi untuk akun demo; kata sandi tidak diperiksa. `?peran=admin` memilih peran lewat URL. | Siapa pun menjadi admin. | Peran ditentukan server dari akun (FR-AUTH-004). Login dengan hash Argon2id, MFA, rate limit. Hapus pemilih peran. → [02](02-autentikasi-dan-sesi.md) |
| PROTO-02 | **Sesi disimpan di `localStorage`** (`stu_lms_v2.session` di `store.js`) dan dibaca oleh `layout.js` sebagai *auth guard* sisi klien. | Sesi dapat dipalsukan/diubah di DevTools; dicuri XSS. | Sesi server-side (Redis), cookie `__Host-` `HttpOnly; Secure; SameSite=Lax`. Guard di middleware server. |
| PROTO-03 | **Kunci jawaban kuis dikirim ke browser** (`data.js` → `kelas[].kuis[].jawaban`) dan skor dihitung di klien (`peserta/kelas-detail.html` baris ±242: `if (parseInt(dipilih.value) === q.jawaban) benar++`). | Peserta membaca kunci jawaban / mengirim skor 100. Integritas sertifikat hancur. | Soal dikirim tanpa kunci; penilaian di server; attempt bertenggat server. → [08](08-integritas-ujian-dan-penilaian.md) |
| PROTO-04 | **Status kelulusan diubah klien.** `Store.submitKuis(pendaftaranId, skor, skorMinimal)` menerima skor & ambang dari pemanggil. | Lulus tanpa ujian. | Transisi status hanya via `EnrollmentStateMachine` di server berdasarkan data server. |
| PROTO-05 | **Sertifikat PDF dibuat di browser** dengan jsPDF (`certificate.js: unduhSertifikatPDF`), tanpa tanda tangan digital. | Siapa pun dapat membuat PDF "sertifikat" identik dengan nama dan nomor bebas. | PDF dibuat & ditandatangani (PAdES) di server; hash disimpan; verifikasi publik. → [07](07-integritas-sertifikat.md) |
| PROTO-06 | **Approval sertifikat & penerbitan tanpa otorisasi server** (`Store.setujuiSertifikat` dapat dipanggil dari konsol mana pun). Nomor urut dihitung dari jumlah sertifikat di klien (`urut = ... .length + 1`) → rawan duplikasi. | Penerbitan sertifikat liar; nomor ganda. | Izin `certificate.approve` + SoD + re-auth; nomor dari sequence DB atomik + `UNIQUE`. |
| PROTO-07 | **Status pembayaran ditetapkan klien.** `Store.buatTransaksi` langsung membuat transaksi berstatus `"lunas"`; harga & diskon kupon dihitung di klien; kupon divalidasi di klien (`validasiKupon`). | Akses program berbayar gratis; manipulasi harga/kupon. | Harga dari DB; kupon atomik; status hanya dari webhook terverifikasi + konfirmasi API. → [09](09-keamanan-pembayaran.md) |
| PROTO-08 | **Stored XSS pada diskusi.** Komentar (`k.isi`) dan judul thread (`t.judul`) dirangkai ke `innerHTML` tanpa *escaping* (`peserta/kelas-detail.html` ±384–391, `trainer/diskusi.html` ±61–68). | Pembajakan sesi trainer/admin yang membaca diskusi; aksi atas nama korban. | Blade `{{ }}` (auto-escape), Markdown subset + sanitizer, CSP ketat dengan nonce. → [04](04-validasi-input-dan-output.md) |
| PROTO-09 | **CMS menerima HTML mentah** — `heroJudul` ("Judul (boleh HTML sederhana)") dirender dengan `innerHTML` di `index.html` (±323). | Stored XSS di beranda publik → menyerang semua pengunjung & admin; *defacement*. | Konten CMS berupa teks + markup terbatas tervalidasi skema; tanpa HTML mentah (FR-CMS-001). |

### Tinggi

| ID | Temuan (lokasi) | Dampak bila terbawa | Kontrol Produksi |
|---|---|---|---|
| PROTO-10 | **OTP tetap `123456`** dan ditampilkan di UI (`login.html` ±99, ±190). | Verifikasi akun tak bermakna; akun palsu massal. | OTP acak CSPRNG, hash di DB, 10 menit, 5 percobaan, rate limit. |
| PROTO-11 | **Tidak ada perlindungan brute force / enumerasi** pada login, lupa kata sandi, cek sertifikat. | Credential stuffing, enumerasi pengguna & sertifikat. | Rate limit berlapis, respons generik, CAPTCHA adaptif. |
| PROTO-12 | **Nomor sertifikat berurutan & mudah ditebak** (`INTL/AWS-CCP/USTU/2026/00001`), halaman `cek-sertifikat.html` menampilkan nama lengkap + organisasi, dan bahkan menampilkan **contoh nomor nyata** ("Coba nomor contoh"). | *Harvesting* nama peserta & afiliasi (data pribadi) dengan iterasi nomor. | `verification_code` acak di QR; lookup nomor → nama tersamar; rate limit; hapus contoh nomor nyata. |
| PROTO-13 | **Kunci API dibuat dengan `Math.random()`** dan ditampilkan sebagai string (`Store.tambahApiKey`). ID dibuat dengan `Date.now()+Math.random()` (`genId`). | Kunci/ID dapat diprediksi. | `random_bytes`/CSPRNG, kunci ditampilkan sekali lalu disimpan sebagai hash; UUIDv7. |
| PROTO-14 | **Semua data (termasuk data pribadi seluruh peserta, transaksi, audit log) dimuat ke setiap browser** melalui `data.js`. | Kebocoran massal data pribadi. | Server hanya mengirim data sesuai izin & scope; paginasi; minimisasi. |
| PROTO-15 | **Tidak ada isolasi organisasi** — trainer & admin melihat seluruh data; filter hanya di UI. | Kebocoran lintas tenant. | Scope tenant + RLS + policy. → [03](03-otorisasi-dan-isolasi-tenant.md) |
| PROTO-16 | **Jejak audit ditulis & dibaca dari klien** (`catatAudit`, `auditLogBaru` di `localStorage`), aktor di-hardcode (`"Dewi Anggraini"`). | Audit dapat dipalsukan/dihapus; non-repudiation hilang. | Audit server-side append-only berantai hash, aktor dari sesi. → [11](11-logging-audit-dan-monitoring.md) |
| PROTO-17 | **Unggah tugas hanya nama berkas** (input teks `fileNama`), tanpa validasi jenis/ukuran/malware; nama berkas dirender tanpa escape (±303). | Bila diterapkan nyata: unggahan berbahaya, XSS via nama berkas. | Pipeline unggahan aman (magic bytes, ukuran, AV scan, nama acak). → [05](05-keamanan-berkas-dan-media.md) |
| PROTO-18 | **Tautan live class & rekaman disajikan ke semua pengguna** (`data.js: liveClass[].link`). | *Zoombombing*, rekaman bocor. | Tautan terenkripsi, hanya untuk peserta terdaftar, muncul 30 menit sebelum sesi. |
| PROTO-19 | **Hapus data master tanpa konfirmasi otorisasi** (`Store.hapus(id)` menambahkan ke `deletedIds`), termasuk pengguna. | Penghapusan liar; hilangnya data historis sertifikat. | Izin, soft delete, maker–checker untuk objek berdampak, audit. |

### Sedang

| ID | Temuan (lokasi) | Dampak bila terbawa | Kontrol Produksi |
|---|---|---|---|
| PROTO-20 | **Script pihak ketiga dari CDN tanpa SRI**: `cdn.tailwindcss.com` (38 halaman), Chart.js, jsPDF, qrcodejs dari cdnjs; font Google Fonts. | Kompromi CDN = XSS di seluruh aplikasi; CSP tidak dapat diketatkan; kebocoran IP pengguna ke pihak ketiga. | Semua aset di-*bundle* via Vite & disajikan dari origin sendiri; font di-*self-host*; SRI bila terpaksa eksternal. |
| PROTO-21 | **Banyak `<script>` inline & pembangunan HTML via string** (84 titik `innerHTML`/`insertAdjacentHTML`). | Menghalangi CSP ketat; rawan XSS. | Blade components + Alpine/Livewire; CSP nonce; larangan `x-html` dengan data pengguna. |
| PROTO-22 | **Ekspor CSV tanpa perlindungan *formula injection*** (`admin/laporan-user.html` ±99–106) — hanya escape tanda kutip. | Nilai diawali `=`, `+`, `-`, `@` dieksekusi Excel (DDE/phishing). | Prefiks `'` untuk sel berawalan karakter berbahaya; ekspor server-side. |
| PROTO-23 | **Validasi input hanya di klien** (atribut `required`, `maxlength`), mis. registrasi, master data. | Bypass validasi. | FormRequest server-side untuk semua input. |
| PROTO-24 | **Pencabutan sertifikat memakai "hapus lalu salin"** (`cabutSertifikat` menambah ID ke `deletedIds` lalu mendorong salinan). | Riwayat tidak utuh, status tak konsisten. | Tabel `certificate_revocations` + status; tidak ada hapus. |
| PROTO-25 | **Persetujuan S&K hanya checkbox klien**, tanpa versi/bukti. | Tidak ada bukti consent (UU PDP). | Tabel `consents` berversi dengan bukti waktu & versi dokumen. |
| PROTO-26 | **Pengaturan keamanan hanya toggle UI** (`admin/pengaturan.html` tab Keamanan: 2FA, rate limit, CAPTCHA, enkripsi) — tidak berpengaruh. Beberapa kontrol (enkripsi at-rest) tidak boleh dapat dimatikan. | Rasa aman palsu; admin bisa menonaktifkan kontrol wajib. | Kontrol wajib di-*hardcode* aktif; pengaturan hanya dapat **memperketat** di atas baseline. |

### Rendah

| ID | Temuan (lokasi) | Kontrol Produksi |
|---|---|---|
| PROTO-27 | Pesan error spesifik ("Kode OTP salah. Gunakan 123456") & *toast* yang mengungkap detail. | Pesan generik; detail hanya di log server. |
| PROTO-28 | `?no=` pada `cek-sertifikat.html` dirender dengan filter `replace(/</g,'')` saja. | Escape kontekstual oleh template engine. |
| PROTO-29 | Email pribadi & nama nyata pada data demo (`@stu.ac.id`, dll.) dan email admin. | Seeder sintetis; tidak ada akun demo di produksi. |
| PROTO-30 | Tidak ada header keamanan (CSP, HSTS, X-Frame-Options, dll.) — situs statis. | Header keamanan di Nginx/middleware. → [13](13-keamanan-infrastruktur.md) |

## Aturan Transisi Purwarupa → Produksi

1. **Boleh dipakai ulang:** struktur HTML, kelas Tailwind, ikon SVG, copywriting, alur halaman.
2. **Wajib ditulis ulang di server:** semua fungsi `Store.*`, `Certificate.*`, perhitungan skor,
   progres, harga, nomor, status, audit.
3. **Wajib dihapus:** `assets/js/data.js` dari bundel produksi, akun demo, pemilih peran,
   kode OTP tetap, contoh nomor sertifikat nyata, script CDN.
4. Purwarupa tetap di repositori sebagai referensi (direktori `prototype/` pada saat
   scaffolding Laravel dibuat) dan **tidak di-deploy** ke domain produksi. Bila perlu demo publik,
   deploy di subdomain terpisah tanpa cookie domain produksi, dengan banner "Demo".
5. Setiap temuan di atas memiliki uji regresi di `tests/Security/PrototypeRegressionTest.php`.
