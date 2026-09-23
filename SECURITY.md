# Kebijakan Keamanan & Pelaporan Kerentanan — STU LMS

Kami menghargai peneliti keamanan dan pengguna yang membantu menjaga STU LMS tetap aman.
Dokumen ini menjelaskan cara melaporkan kerentanan secara bertanggung jawab.

## Status Repositori

Repositori ini saat ini berisi **purwarupa UI statis** dan **dokumentasi pra-development**.
Purwarupa memakai data dummy di `localStorage` dan **sengaja tidak memiliki keamanan sisi server** —
temuan pada purwarupa sudah terdokumentasi di
[`docs/keamanan/16-temuan-keamanan-purwarupa.md`](docs/keamanan/16-temuan-keamanan-purwarupa.md)
dan tidak perlu dilaporkan ulang.

## Versi yang Didukung

| Versi | Didukung |
|---|---|
| Aplikasi produksi (rilis terbaru) | ✅ |
| Rilis sebelumnya | ❌ (selalu perbarui ke rilis terbaru) |
| Purwarupa statis | ❌ (bukan sistem produksi) |

## Cara Melaporkan

- **Email:** `security@semestateknologiutama.com` *(alamat final ditetapkan sebelum go-live; hingga
  saat itu gunakan fitur **Private vulnerability reporting / Security Advisories** GitHub pada
  repositori ini)*.
- Mohon sertakan: deskripsi kerentanan, langkah reproduksi, dampak, URL/endpoint, bukti (tangkapan
  layar/permintaan HTTP), serta kontak Anda.
- Jangan sertakan data pribadi pengguna lain yang mungkin Anda lihat; cukup bukti minimal.

## Komitmen Kami

| Tahap | Target waktu |
|---|---|
| Konfirmasi penerimaan laporan | ≤ 2 hari kerja |
| Validasi & klasifikasi awal | ≤ 5 hari kerja |
| Perbaikan Critical / High | ≤ 72 jam / ≤ 7 hari setelah dikonfirmasi |
| Perbaikan Medium / Low | ≤ 30 hari / ≤ 90 hari |
| Pemberitahuan status berkala | Setidaknya tiap 14 hari sampai selesai |

Kami akan memberi kredit kepada pelapor (bila diinginkan) setelah perbaikan dirilis.

## Safe Harbor

Kami tidak akan menempuh upaya hukum terhadap penelitian yang dilakukan dengan itikad baik dan
mematuhi aturan berikut:

- Hanya menguji akun milik Anda sendiri atau akun uji yang kami sediakan.
- Tidak mengakses, mengubah, menyimpan, atau menghapus data milik pengguna lain lebih dari yang
  minimal diperlukan untuk membuktikan kerentanan; segera hentikan dan laporkan bila menemukan data
  pribadi.
- Tidak melakukan serangan penolakan layanan (DoS/DDoS), spam, rekayasa sosial terhadap staf/
  pengguna, atau serangan fisik.
- Tidak menggunakan pemindai otomatis dengan volume tinggi yang mengganggu layanan.
- Memberi kami waktu wajar untuk memperbaiki sebelum pengungkapan publik (pengungkapan
  terkoordinasi, umumnya 90 hari).

## Di Luar Cakupan

- Laporan tanpa dampak keamanan nyata (mis. header yang hilang tanpa skenario eksploitasi,
  *clickjacking* pada halaman tanpa aksi sensitif, *self-XSS*).
- Kerentanan pada layanan pihak ketiga (laporkan ke vendor terkait).
- Hasil pemindai otomatis tanpa verifikasi.
- Serangan yang memerlukan perangkat korban yang sudah dikompromi.

Kebijakan keamanan internal lengkap: [`docs/keamanan/README.md`](docs/keamanan/README.md).
