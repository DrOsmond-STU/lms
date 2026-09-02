# STU LMS — Purwarupa UI/UX

Purwarupa (prototype) antarmuka Learning Management System untuk mahasiswa universitas yang
mengambil sertifikasi **Internasional** (AWS, Microsoft Azure, Cisco CCNA, PMI CAPM) dan
**BNSP** (Junior Web Developer, Digital Marketing, Ahli K3 Umum, Junior Network Administrator).

Ini adalah **purwarupa statis** (HTML/CSS/JS + Tailwind CDN) dengan **data dummy**. Tidak ada
backend/database sungguhan — interaksi (daftar sertifikasi, isi kuis, approval admin, dsb.)
disimpan sementara di `localStorage` browser agar terasa hidup selama sesi demo.

## Menjalankan secara lokal

Karena ini situs statis murni, cukup jalankan server statis apa pun dari root folder ini, misalnya:

```bash
python3 -m http.server 8000
# buka http://localhost:8000/index.html
```

Atau langsung upload seluruh isi folder ini ke document root hosting Anda (Apache/Nginx apa pun).

## Akun demo

Buka **Masuk** dari halaman utama, pilih peran, lalu klik tombol Masuk (kata sandi bebas —
demo ini tidak melakukan validasi kata sandi sungguhan):

| Peran | Nama Demo | Keterangan |
|---|---|---|
| Mahasiswa | Raka Prasetya | Universitas Semesta Teknologi Utama — punya 1 sertifikat terbit, 1 pembelajaran berjalan, 1 terdaftar |
| Instruktur | Dr. Andi Wijaya | Mengampu kelas AWS Cloud Practitioner & Azure Fundamentals |
| Admin | Dewi Anggraini | Super Admin — akses lintas universitas mitra |

## Fitur utama

- **Cek Sertifikat** (`cek-sertifikat.html`) — validasi publik nomor sertifikat, tanpa perlu masuk.
- **Sertifikat Saya** — unduh sertifikat asli sebagai **PDF ber-QR** (dibuat di sisi klien dengan
  jsPDF + qrcodejs), QR mengarah balik ke halaman validasi.
- **Pilih Sertifikasi** — katalog skema Internasional & BNSP, gratis (ditanggung kampus).
- **Pembelajaran** — modul video/PDF, kuis pilihan ganda dengan skor, progres, dan syarat
  kelulusan sebelum sertifikat diterbitkan.
- **Menu Instruktur** — kelola konten kelas, tambah materi, lihat peserta & beri nilai.
- **Menu Admin** — approval & penerbitan sertifikat, master data (universitas, skema, kelas,
  pengguna), dan **laporan multi-universitas** (pengguna per universitas, skema sertifikasi
  per universitas) lengkap dengan grafik dan ekspor CSV.

## Struktur folder

```
index.html                  Landing page publik
login.html                  Login (demo, 3 peran)
cek-sertifikat.html         Validasi sertifikat publik

mahasiswa/                  Dashboard, katalog sertifikasi, pembelajaran, kuis, sertifikat, profil
instruktur/                 Dashboard, kelas, kelola materi, peserta & nilai
admin/                      Dashboard analitik, approval sertifikat, master data, laporan

assets/js/data.js           Data dummy (universitas, skema, kelas, mahasiswa, sertifikat, dst.)
assets/js/store.js          Lapisan "database" di atas localStorage (aksi daftar, kuis, approval, CRUD)
assets/js/layout.js         Topbar & sidebar per peran + auth guard sederhana
assets/js/certificate.js    Validasi sertifikat & pembuatan PDF+QR
assets/js/ui.js             Ikon, format tanggal, badge, toast
assets/css/style.css        Gaya tambahan di luar utility Tailwind
```

## Catatan

Purwarupa ini fokus pada UI/UX dan alur pengguna dengan data dummy. Untuk versi produksi,
langkah selanjutnya yang disarankan: backend + database sungguhan, autentikasi & otorisasi
nyata (SSO/SIA kampus), penyimpanan file materi (video/PDF) di storage, serta integrasi
resmi dengan skema BNSP/lembaga sertifikasi internasional untuk penerbitan sertifikat.
