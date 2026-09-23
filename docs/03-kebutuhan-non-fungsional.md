# 03 — Kebutuhan Non-Fungsional

> Status: **Disetujui untuk development** · Versi 1.0 · Pemilik: Tech Lead
>
> Setiap NFR harus **terukur** dan diverifikasi (kolom "Verifikasi"). Kebutuhan keamanan rinci
> berada di folder [`keamanan/`](keamanan/README.md); di sini hanya ringkasan tingkat tinggi.

## 1. Kinerja (PERF)

| ID | Kebutuhan | Target | Verifikasi |
|---|---|---|---|
| NFR-PERF-01 | Waktu respons halaman (server, TTFB) | p95 ≤ 500 ms, p99 ≤ 1.500 ms pada beban normal | k6 + APM |
| NFR-PERF-02 | Waktu respons API | p95 ≤ 300 ms (verifikasi sertifikat ≤ 200 ms) | k6 |
| NFR-PERF-03 | Autosave jawaban ujian | p95 ≤ 250 ms pada 1.000 peserta serentak | k6 skenario ujian |
| NFR-PERF-04 | Largest Contentful Paint di 4G (Moto G Power / emulasi) | ≤ 2,5 detik halaman utama peserta | Lighthouse CI |
| NFR-PERF-05 | Ukuran JS awal halaman peserta | ≤ 200 KB gzip | Build report |
| NFR-PERF-06 | Mulai putar video (time-to-first-frame) | ≤ 3 detik di 10 Mbps | Uji manual/RUM |
| NFR-PERF-07 | Pembuatan PDF sertifikat | ≤ 30 detik dari approval hingga tersedia (p95) | Metrik antrian |
| NFR-PERF-08 | Ekspor laporan 50.000 baris | ≤ 5 menit (asinkron) | Uji beban |
| NFR-PERF-09 | Query basis data | Tidak ada query > 200 ms di jalur interaktif; N+1 dicegah (`preventLazyLoading`) | Log slow query, uji |

## 2. Kapasitas & Skalabilitas (SCAL)

| ID | Kebutuhan | Target |
|---|---|---|
| NFR-SCAL-01 | Pengguna terdaftar | 50.000 (tahun 1), arsitektur siap 500.000 tanpa desain ulang |
| NFR-SCAL-02 | Konkurensi | 1.000 peserta mengerjakan ujian serentak; 3.000 sesi aktif |
| NFR-SCAL-03 | Skala horizontal | Node aplikasi stateless; menambah node tanpa downtime |
| NFR-SCAL-04 | Media | 2 TB tahun 1, disajikan via CDN (origin offload ≥ 90%) |
| NFR-SCAL-05 | Antrian | Worker diskalakan per antrian; backlog `notifications` < 5 menit pada puncak |

## 3. Ketersediaan & Keandalan (AVL)

| ID | Kebutuhan | Target |
|---|---|---|
| NFR-AVL-01 | Ketersediaan bulanan layanan inti (login, belajar, ujian, verifikasi) | ≥ 99,5% (rilis 1.0), ≥ 99,9% (tahun 2) di luar jendela pemeliharaan terjadwal |
| NFR-AVL-02 | Halaman verifikasi sertifikat publik | ≥ 99,9% (dapat disajikan dari replika baca/cache) |
| NFR-AVL-03 | Jendela pemeliharaan | Minggu 00.00–04.00 WIB, diumumkan ≥ 72 jam sebelumnya; tidak boleh saat ujian terjadwal |
| NFR-AVL-04 | RPO (kehilangan data maks.) | ≤ 15 menit untuk kerusakan data/kegagalan dalam region (PITR); ≤ 24 jam untuk kompromi akun cloud atau kehilangan region total (batas MVP) — tabel per skenario di [`11-devops-dan-deployment.md`](11-devops-dan-deployment.md) §10.1 |
| NFR-AVL-05 | RTO (waktu pulih maks.) | ≤ 15 menit kegagalan node/AZ; ≤ 4 jam kerusakan data (restore PITR); ≤ 24 jam kompromi akun cloud; ≤ 72 jam kehilangan region total (batas MVP, target diperbaiki di tahun 2) |
| NFR-AVL-06 | Ketahanan integrasi | Kegagalan email/WA/payment gateway tidak menjatuhkan aplikasi; retry dengan backoff + *circuit breaker* |
| NFR-AVL-07 | Degradasi terkendali | Bila layanan PDF mati, approval tetap tercatat dan PDF dibuat saat pulih |
| NFR-AVL-08 | Ujian saat gangguan | Jawaban autosave; bila terjadi gangguan platform > 5 menit, admin dapat memperpanjang deadline attempt terdampak secara massal (tercatat di audit) |

## 4. Keamanan (SEC) — Ringkasan

| ID | Kebutuhan | Rujukan |
|---|---|---|
| NFR-SEC-01 | Memenuhi OWASP ASVS v5.0 Level 2 untuk seluruh aplikasi; kontrol L3 terpilih untuk autentikasi admin, sertifikat, pembayaran | [`keamanan/README.md`](keamanan/README.md) |
| NFR-SEC-02 | Tidak ada kerentanan OWASP Top 10 dengan tingkat Critical/High saat rilis (hasil pentest eksternal + DAST) | [`keamanan/14`](keamanan/14-secure-sdlc-dan-supply-chain.md) |
| NFR-SEC-03 | Seluruh lalu lintas TLS 1.2+ (TLS 1.3 diutamakan), HSTS preload | [`keamanan/13`](keamanan/13-keamanan-infrastruktur.md) |
| NFR-SEC-04 | MFA wajib untuk seluruh peran non-peserta | [`keamanan/02`](keamanan/02-autentikasi-dan-sesi.md) |
| NFR-SEC-05 | Isolasi tenant berlapis (aplikasi + RLS) dengan uji otomatis | [`keamanan/03`](keamanan/03-otorisasi-dan-isolasi-tenant.md) |
| NFR-SEC-06 | Data pribadi terenkripsi saat transit & saat disimpan; kolom sensitif dienkripsi tingkat aplikasi | [`keamanan/06`](keamanan/06-kriptografi-dan-manajemen-kunci.md) |
| NFR-SEC-07 | Jejak audit tamper-evident, retensi ≥ 2 tahun (log keamanan ≥ 1 tahun online) | [`keamanan/11`](keamanan/11-logging-audit-dan-monitoring.md) |
| NFR-SEC-08 | Waktu deteksi insiden kritis (MTTD) ≤ 15 menit; notifikasi kegagalan PDP sesuai UU PDP (≤ 3×24 jam) | [`keamanan/15`](keamanan/15-respons-insiden-dan-kontinuitas.md) |
| NFR-SEC-09 | Patch keamanan kritis diterapkan ≤ 72 jam; tinggi ≤ 7 hari | [`keamanan/14`](keamanan/14-secure-sdlc-dan-supply-chain.md) |

## 5. Privasi & Kepatuhan (CMP)

| ID | Kebutuhan |
|---|---|
| NFR-CMP-01 | Patuh UU No. 27/2022 (PDP): dasar pemrosesan terdokumentasi, hak subjek data, DPO, RoPA (catatan aktivitas pemrosesan), notifikasi kegagalan. |
| NFR-CMP-02 | Patuh PP 71/2019 (PSTE) & pendaftaran PSE Lingkup Privat. |
| NFR-CMP-03 | Pembayaran: lingkup PCI DSS SAQ-A (tidak ada data kartu di sistem). |
| NFR-CMP-04 | Penyimpanan data primer di region Indonesia (preferensi); transfer lintas negara (mis. layanan email/error tracking) dinilai & dicatat sesuai UU PDP Pasal 56. |
| NFR-CMP-05 | Data anak: bila peserta < 18 tahun (mis. SMK), diperlukan persetujuan orang tua/wali (UU PDP Pasal 25) — platform menolak registrasi mandiri di bawah 18 tahun kecuali melalui organisasi dengan persetujuan wali terdokumentasi. |
| NFR-CMP-06 | Penggunaan merek pihak ketiga (AWS, Microsoft, Cisco, PMI, BNSP) pada katalog & sertifikat sesuai izin/pedoman merek. |

## 6. Kegunaan & Aksesibilitas (UX)

| ID | Kebutuhan | Target |
|---|---|---|
| NFR-UX-01 | Aksesibilitas | WCAG 2.1 AA (target 2.2 AA); skor Lighthouse Accessibility ≥ 95; lulus axe-core tanpa pelanggaran serius |
| NFR-UX-02 | Responsif | Dapat dipakai penuh pada lebar 360 px (ponsel) hingga desktop |
| NFR-UX-03 | Browser | 2 versi utama terakhir Chrome, Edge, Firefox, Safari; Chrome Android; Safari iOS 16+ |
| NFR-UX-04 | Bahasa | Bahasa Indonesia; semua teks melalui berkas terjemahan (`lang/id`) — siap i18n |
| NFR-UX-05 | Tugas utama peserta (mulai lesson berikutnya) | ≤ 2 klik dari dashboard |
| NFR-UX-06 | Umpan balik | Setiap aksi menampilkan status (loading/sukses/gagal) ≤ 100 ms |
| NFR-UX-07 | Koneksi lambat | Halaman peserta tetap berfungsi pada 3G lambat (1,6 Mbps); video memilih bitrate adaptif (240p–1080p) |

## 7. Pemeliharaan & Kualitas Kode (MNT)

| ID | Kebutuhan | Target |
|---|---|---|
| NFR-MNT-01 | Cakupan uji | ≥ 80% baris pada modul domain; 100% pada policy otorisasi, penilaian asesmen, pembayaran, penerbitan sertifikat |
| NFR-MNT-02 | Analisis statis | Larastan level ≥ 8, nol error; Pint tanpa pelanggaran |
| NFR-MNT-03 | Arsitektur | Aturan dependensi modul diuji (Pest Arch) |
| NFR-MNT-04 | Dokumentasi | Setiap PR yang mengubah perilaku memperbarui dokumen terkait; ADR untuk keputusan besar |
| NFR-MNT-05 | Dependensi | Tidak ada dependensi dengan CVE High/Critical yang diketahui saat rilis; tidak ada paket *abandoned* |
| NFR-MNT-06 | Waktu build & uji PR | ≤ 15 menit |

## 8. Observabilitas (OBS)

| ID | Kebutuhan |
|---|---|
| NFR-OBS-01 | Log terstruktur JSON dengan `request_id`, `user_id` (pseudonim), `organization_id`, tanpa PII sensitif/rahasia. |
| NFR-OBS-02 | Metrik RED (rate, error, duration) per rute & antrian; dashboard Grafana. |
| NFR-OBS-03 | Alert: error rate > 2% (5 menit), p95 > 1 detik (10 menit), antrian gagal > 10/jam, lonjakan login gagal, webhook gagal verifikasi, rantai audit putus, sertifikat gagal dibuat. |
| NFR-OBS-04 | Uptime monitoring eksternal tiap 1 menit untuk login, verifikasi sertifikat, health check. |

## 9. Portabilitas & Interoperabilitas (INT)

| ID | Kebutuhan |
|---|---|
| NFR-INT-01 | Seluruh layanan berjalan di kontainer; tidak terkunci pada satu vendor cloud (object storage via API S3, DB PostgreSQL standar). |
| NFR-INT-02 | API mengikuti OpenAPI 3.1, JSON, UTF-8, tanggal ISO 8601. |
| NFR-INT-03 | Ekspor data dalam format terbuka (CSV UTF-8 dengan BOM untuk Excel, XLSX, JSON). |

## 10. Retensi Data (RET)

Ringkas — tabel lengkap di [`keamanan/12-privasi-data-dan-kepatuhan-uu-pdp.md`](keamanan/12-privasi-data-dan-kepatuhan-uu-pdp.md).

| Kategori | Retensi |
|---|---|
| Akun & profil | Selama akun aktif; dianonimkan ≤ 30 hari setelah permintaan hapus disetujui |
| Catatan sertifikat (minimal) | Selama masa berlaku + 10 tahun (kebutuhan verifikasi), dengan dasar hukum terdokumentasi |
| Transaksi & invoice | 10 tahun (kewajiban dokumen keuangan/perpajakan) |
| Jawaban ujian & berkas tugas | 2 tahun setelah kelas ditutup |
| Log aplikasi | 90 hari |
| Log keamanan & audit | 2 tahun (1 tahun *hot*, sisanya arsip) |
| Log verifikasi sertifikat | 1 tahun (IP disimpan sebagai hash ber-salt) |
| Berkas ekspor sementara | 24 jam |
