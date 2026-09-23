# 09 — Keamanan Pembayaran & Anti-Fraud

> Status: **Disetujui untuk development** · Versi 1.0 · Pemilik: Security Lead + Admin Keuangan
>
> Gateway: Midtrans Snap (ADR-007). Lingkup PCI DSS: **SAQ-A** — tidak ada data kartu yang
> menyentuh, diproses, atau disimpan server STU. Alur teknis: [`../04-arsitektur-sistem.md`](../04-arsitektur-sistem.md)
> §8.2 dan [`../06-spesifikasi-api.md`](../06-spesifikasi-api.md) §4.3.

## 1. Integritas Harga & Checkout

| ID | Kebutuhan |
|---|---|
| SEC-PAY-01 | Harga, diskon, dan total dihitung **server** dari `programs.price` / `program_organization_prices` / kupon di DB saat checkout. Request checkout hanya berisi `course_class_id` dan `coupon_code` opsional — field harga/jumlah dari klien diabaikan & dicatat sebagai `suspicious_parameter`. |
| SEC-PAY-02 | `gross_amount` yang dikirim ke gateway disimpan di `payment_transactions`; notifikasi/status gateway harus memiliki `gross_amount` **identik** — selisih → transaksi tetap `pending` dengan penanda `needs_review`, tidak memberi akses, alert ke Admin Keuangan. |
| SEC-PAY-03 | `order_id` = `STU-{ULID}` (tidak dapat ditebak, unik), satu transaksi aktif per (pengguna, kelas); checkout ulang ketika masih `pending` mengembalikan transaksi yang sama (idempotensi via `Idempotency-Key` + unique partial index). Batas waktu pembayaran sesuai metode (VA 24 jam, QRIS 15 menit, e-wallet 15 menit). |
| SEC-PAY-16 | Kredensial gateway: **server key** hanya di secret manager & worker/web yang membutuhkan; **client key** (publik) boleh di frontend. Lingkungan sandbox & produksi memakai kunci & akun berbeda; aplikasi menolak *boot* bila produksi memakai kunci sandbox (prefiks `SB-`) atau sebaliknya. |
| SEC-PAY-17 | Halaman pembayaran gateway dibuka via redirect/Snap popup resmi; tidak ada form kartu buatan sendiri. CSP `frame-src`/`script-src` hanya mengizinkan domain Snap resmi pada halaman checkout saja. |

## 2. Notifikasi (Webhook) Pembayaran

| ID | Kebutuhan |
|---|---|
| SEC-PAY-04 | Verifikasi `signature_key = SHA512(order_id + status_code + gross_amount + server_key)` dengan perbandingan waktu-konstan. Gagal → 401, `security_events.webhook_signature_invalid`, alert bila > 5 kejadian/jam. |
| SEC-PAY-05 | Setelah tanda tangan valid, **konfirmasi status** ke API gateway (`GET /v2/{order_id}/status`) — status dari API yang dipakai untuk transisi, bukan hanya dari body webhook. |
| SEC-PAY-06 | **Idempotensi**: setiap notifikasi dicatat di `payment_events` dengan `dedup_key` unik; pemrosesan dalam transaksi DB dengan `lockForUpdate` pada transaksi; notifikasi berulang tidak membuat enrollment/kupon ganda. |
| SEC-PAY-07 | **Mesin status** ketat ([`../02-kebutuhan-fungsional.md`](../02-kebutuhan-fungsional.md) §22.3): transisi mundur (`settled → pending`) atau tidak sah ditolak & dicatat. `fraud_status = challenge` → tahan (`pending`) sampai keputusan gateway. |
| SEC-PAY-08 | Endpoint webhook: tanpa sesi/CSRF, hanya `POST application/json`, body ≤ 64 KB, rate limit, IP gateway di-allowlist sebagai lapisan tambahan (bila penyedia mempublikasikan rentang IP), respons cepat (pekerjaan lanjutan via antrian), tidak mengembalikan detail error. |

## 3. Kupon & Promosi

| ID | Kebutuhan |
|---|---|
| SEC-PAY-09 | Validasi & reservasi kupon **atomik** dalam transaksi: `SELECT ... FOR UPDATE` pada baris kupon, cek aktif, periode, program/organisasi, minimal transaksi, `used_count + reserved_count < total_quota`, batas per pengguna (hitung `coupon_redemptions` status `reserved`/`consumed`); constraint DB `used_count <= total_quota` sebagai jaring pengaman. |
| SEC-PAY-10 | Satu kupon per transaksi (tidak dapat ditumpuk); diskon tidak boleh membuat total < 0; kupon 100% (gratis) tetap membuat transaksi `settled` bernilai 0 dengan jejak audit & tanpa panggilan gateway. |
| SEC-PAY-11 | Kode kupon ≥ 8 karakter acak untuk kupon bernilai tinggi/terbatas (kupon kampanye publik boleh kata bermakna); rate limit percobaan kode kupon (10/jam per pengguna) untuk mencegah *brute force*; kode tidak valid → pesan generik. |

## 4. Rekonsiliasi, Refund & Kontrol Internal

| ID | Kebutuhan |
|---|---|
| SEC-PAY-12 | `payment_events` **append-only** menyimpan jejak lengkap (tanpa data sensitif pelanggan melebihi yang dibutuhkan); invoice bernomor unik berurutan tidak dapat dihapus/diubah (koreksi via nota kredit). |
| SEC-PAY-13 | **Rekonsiliasi**: job 15 menit menarik status transaksi `pending` > 15 menit; laporan harian transaksi vs laporan settlement gateway; selisih (ada di gateway tidak di LMS, jumlah beda, status beda) → tiket ke Admin Keuangan & alert. |
| SEC-PAY-14 | **Refund** hanya oleh `finance_admin` dengan alasan; > Rp1.000.000 atau refund untuk transaksi berumur > 30 hari → maker–checker; jumlah refund ≤ jumlah dibayar dikurangi refund sebelumnya (constraint); eksekusi via API refund gateway (idempoten dengan `refund_key`) atau transfer manual dengan bukti terunggah; enrollment terkait dibatalkan dan akses materi dicabut. |
| SEC-PAY-15 | Penandaan "lunas manual" (mis. transfer bank korporat) hanya oleh `finance_admin` + maker–checker + bukti transfer, tercatat di audit; tidak tersedia untuk transaksi Snap (harus via rekonsiliasi gateway). |

## 5. Anti-Fraud

| ID | Kebutuhan |
|---|---|
| SEC-PAY-18 | Batas checkout 10/jam per pengguna & 30/jam per IP; akun baru (< 24 jam) dengan banyak transaksi gagal → tinjau. |
| SEC-PAY-19 | Fitur fraud detection gateway diaktifkan (mis. Midtrans FDS) untuk metode yang mendukung; 3DS wajib bila kartu diaktifkan kelak. |
| SEC-PAY-20 | Pemantauan: rasio gagal/berhasil per metode, lonjakan refund, pemakaian kupon abnormal, transaksi 0 rupiah — dashboard Admin Keuangan + alert. |

## 6. Data & Privasi Pembayaran

- Tidak menyimpan PAN/CVV/data kartu (tidak pernah diterima).
- Menyimpan: order ID, jumlah, metode (mis. "VA BCA"), 4 digit terakhir nomor VA/rekening bila diperlukan untuk dukungan, status, waktu.
- Data faktur korporat (NPWP, alamat) dienkripsi (K4) dan hanya dapat dilihat `finance_admin` & pemilik.
- Retensi dokumen keuangan 10 tahun ([12](12-privasi-data-dan-kepatuhan-uu-pdp.md)).
- Payload webhook disaring sebelum disimpan: buang field yang tidak dibutuhkan (mis. data pelanggan tambahan dari gateway).

## 7. Uji Wajib

Harga dimanipulasi, `gross_amount` beda, webhook tanpa/dengan tanda tangan salah, replay webhook
sah (idempoten), urutan notifikasi terbalik (settlement lalu pending), kupon paralel (tepat N
berhasil), kupon kedaluwarsa/di luar program, refund melebihi jumlah, maker = checker, kunci
sandbox di produksi → gagal boot. Lihat [`../10-strategi-pengujian.md`](../10-strategi-pengujian.md).
