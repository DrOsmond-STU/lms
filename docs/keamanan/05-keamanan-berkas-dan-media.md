# 05 — Keamanan Berkas, Unggahan & Media

> Status: **Disetujui untuk development** · Versi 1.0 · Pemilik: Security Lead
>
> Acuan: ASVS v5.0 V5 (File Handling), OWASP File Upload Cheat Sheet.
> Berlaku untuk: materi (video/PDF/gambar), lampiran tugas & contoh, berkas pengumpulan tugas,
> lampiran diskusi, logo organisasi, gambar CMS, impor CSV/XLSX, PDF sertifikat & invoice, ekspor.

## 1. Kebijakan Jenis & Ukuran

| Konteks | Ekstensi & MIME diizinkan | Ukuran maks. | Pemrosesan |
|---|---|---|---|
| Video materi | `mp4` (video/mp4), `mov` (video/quicktime), `webm` | 2 GB | Transcode → HLS; asli dihapus setelah 30 hari |
| PDF materi / contoh tugas | `pdf` (application/pdf) | 50 MB | Validasi struktur; (opsional) *flatten*/sanitasi JavaScript PDF |
| Gambar (CMS, logo, avatar, diskusi) | `jpg`, `png`, `webp` — **SVG tidak diizinkan** | 5 MB | *Re-encode* (hapus EXIF/metadata, termasuk GPS) |
| Pengumpulan tugas | Per tugas dari allowlist global: `pdf`, `docx`, `xlsx`, `pptx`, `zip`, `jpg`, `png`, `txt` | Per tugas, ≤ 100 MB/berkas, ≤ 5 berkas | AV scan; `zip` diperiksa (lihat §3) |
| Impor data | `csv`, `xlsx` | 10 MB, ≤ 5.000 baris | Parser read-only, tanpa makro |
| Verifikasi PDF sertifikat | `pdf` | 5 MB | Hanya validasi tanda tangan; tidak disimpan > 1 jam |

Format berisiko **selalu ditolak**: `html`, `htm`, `svg`, `xml`, `js`, `php*`, `phtml`, `phar`,
`exe`, `dll`, `bat`, `cmd`, `sh`, `ps1`, `jar`, `apk`, `msi`, `docm`, `xlsm`, `pptm`, `hta`, `lnk`,
`iso`, dan berkas tanpa ekstensi.

## 2. Kebutuhan Unggahan

| ID | Kebutuhan |
|---|---|
| SEC-FILE-01 | Validasi **jenis sebenarnya** dengan *magic bytes* (`finfo`/`mime_content_type`) **dan** ekstensi **dan** allowlist konteks; ketiganya harus konsisten. MIME dari klien (`Content-Type`) diabaikan. |
| SEC-FILE-02 | Batas ukuran ditegakkan di edge (WAF), Nginx (`client_max_body_size` per lokasi), dan aplikasi. Unggahan besar (video) memakai **pre-signed multipart upload** langsung ke object storage dengan batas `content-length-range` & kedaluwarsa 15 menit, lalu server memverifikasi objek (ukuran, hash, jenis) sebelum menerimanya. |
| SEC-FILE-03 | Berkas disimpan dengan **nama acak** (UUIDv7) di bucket privat — nama asli hanya di metadata (disanitasi: hapus path, karakter kontrol, bidi override; panjang ≤ 200). |
| SEC-FILE-04 | Berkas **tidak pernah** disimpan di direktori yang dapat dieksekusi/di-serve web server (`public/`). Tidak ada `storage:link` untuk berkas pengguna. |
| SEC-FILE-05 | **Pemindaian malware** (ClamAV dengan definisi diperbarui ≥ 2×/hari, atau layanan pemindai terkelola) sebelum berkas tersedia bagi pengguna lain. Status `pending` → berkas tidak dapat diunduh; `infected` → dikarantina (bucket karantina, akses Security Lead saja), pengunggah & Security Lead dinotifikasi, tercatat di `security_events`. Kegagalan pemindai → `error` → tidak disajikan (fail closed) + alert. |
| SEC-FILE-06 | Gambar di-*re-encode* server-side (mis. via `intervention/image` + libvips/GD terbaru) untuk membuang payload polyglot & metadata; dimensi maks. 8.000×8.000 px dan batas piksel total (mencegah *decompression bomb*). |
| SEC-FILE-07 | Hash **SHA-256** setiap berkas dicatat (integritas, deduplikasi, indikator plagiarisme, bukti forensik). |
| SEC-FILE-08 | Kuota penyimpanan per pengguna/kelas/organisasi dan rate limit unggahan (mis. 30 unggahan/jam/pengguna) untuk mencegah penyalahgunaan penyimpanan. |
| SEC-FILE-09 | Unggahan hanya oleh pengguna berizin pada konteks yang tepat (mis. submit tugas hanya oleh peserta enrollment aktif sebelum batas kebijakan). |
| SEC-FILE-10 | Tidak ada fitur "unggah dari URL" pada rilis 1.0 (menghindari SSRF). Bila kelak diperlukan: wajib `SafeHttpClient` ([04](04-validasi-input-dan-output.md) SEC-INPUT-15). |

## 3. Arsip ZIP & Dokumen Office

| ID | Kebutuhan |
|---|---|
| SEC-FILE-11 | ZIP **tidak diekstrak** di server kecuali perlu; bila diperiksa: batas jumlah entri (≤ 1.000), total ukuran terekstrak (≤ 10× dan ≤ 500 MB), rasio kompresi (≤ 100:1) untuk mencegah *zip bomb*; tolak entri dengan path absolut/`..` (*zip slip*), symlink, dan arsip terenkripsi (tidak dapat dipindai) kecuali kebijakan tugas mengizinkan dengan peringatan. |
| SEC-FILE-12 | Dokumen Office dengan makro ditolak (deteksi `vbaProject.bin`); pemindai AV mendeteksi eksploit dokumen. |

## 4. Pemrosesan Media Tak Tepercaya (Sandbox)

| ID | Kebutuhan |
|---|---|
| SEC-FILE-13 | FFmpeg, pemindai, pemroses gambar, dan renderer PDF berjalan di **kontainer terpisah**: user non-root, root filesystem read-only, `no-new-privileges`, seccomp/AppArmor default, *capabilities* di-*drop*, **tanpa jaringan** (kecuali akses object storage via pre-signed URL spesifik bila perlu), batas CPU/memori/waktu (mis. transcoding maks. 2× durasi video), image diperbarui mingguan. FFmpeg dijalankan dengan `-protocol_whitelist file,pipe` (mencegah SSRF/LFI via playlist HLS/concat berbahaya) dan input berasal dari berkas lokal hasil unduhan terkontrol. |

## 5. Penyajian Berkas

| ID | Kebutuhan |
|---|---|
| SEC-FILE-14 | Semua bucket **privat** dengan *Block Public Access* aktif, kecuali `stu-public-assets` (logo/gambar CMS terbit) yang hanya dapat ditulis oleh pipeline aplikasi. Kebijakan bucket menolak request non-TLS. |
| SEC-FILE-15 | Unduhan melalui endpoint aplikasi yang memeriksa policy lalu menerbitkan **URL bertanda tangan** berumur pendek: sertifikat/invoice/berkas tugas 5 menit, ekspor 15 menit, ekspor data pribadi 24 jam (sekali pakai via token aplikasi). URL tidak di-log lengkap (query signature disamarkan). |
| SEC-FILE-16 | Header respons unduhan: `Content-Disposition: attachment; filename*=UTF-8''...` untuk semua berkas pengguna kecuali tipe yang aman ditampilkan inline di viewer (PDF materi via viewer, gambar re-encoded); `X-Content-Type-Options: nosniff`; `Content-Type` dari hasil deteksi server; `Cache-Control: private, no-store` untuk berkas pribadi. |
| SEC-FILE-17 | Berkas pengguna disajikan dari **domain terpisah** tanpa cookie (mis. `files.stu-lms-usercontent.com` via CDN) dengan CSP `default-src 'none'; sandbox` — sehingga berkas berbahaya yang lolos tidak dapat mengakses cookie/origin aplikasi. |

## 6. Video & Materi Berbayar

| ID | Kebutuhan |
|---|---|
| SEC-FILE-18 | Video disajikan sebagai **HLS dengan enkripsi AES-128** per video; kunci diambil dari endpoint aplikasi yang memeriksa enrollment aktif (bukan dari storage publik). Manifest & segmen diakses via **signed cookie/URL CDN** berumur ≤ 10 menit (diperbarui oleh player). |
| SEC-FILE-19 | **Watermark dinamis** (FR-CNT-007): overlay nama + ID tersamar peserta pada player & watermark pada PDF yang dilihat/diunduh, untuk pelacakan kebocoran. |
| SEC-FILE-20 | Deteksi penyalahgunaan: permintaan kunci/segmen dari banyak IP untuk satu sesi dalam waktu singkat, atau volume unduhan tidak wajar → alert & pembatasan sementara. |

## 7. Siklus Hidup & Penghapusan

- Berkas sementara (unggahan belum selesai, ekspor, impor, verifikasi PDF) dihapus otomatis oleh lifecycle rule (≤ 24 jam).
- Berkas tugas dihapus sesuai retensi (2 tahun setelah kelas ditutup) — lihat [12](12-privasi-data-dan-kepatuhan-uu-pdp.md).
- Penghapusan objek memakai *versioning*; versi lama kedaluwarsa setelah 30 hari (kecuali bucket sertifikat dengan object lock).
- Permintaan penghapusan data pribadi mencakup berkas milik pengguna (kecuali yang wajib dipertahankan dengan dasar hukum).
