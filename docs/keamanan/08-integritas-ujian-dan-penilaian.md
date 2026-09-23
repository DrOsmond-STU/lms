# 08 — Integritas Ujian, Penilaian & Progres Belajar

> Status: **Disetujui untuk development** · Versi 1.0 · Pemilik: Security Lead + Admin Akademik
>
> Kelulusan menentukan sertifikat. Karena itu seluruh logika asesmen berada di server, dan setiap
> sinyal dari klien (jawaban, waktu tonton, fokus tab) diperlakukan sebagai **masukan tidak
> tepercaya** yang divalidasi.

## 1. Kerahasiaan Soal & Kunci Jawaban

| ID | Kebutuhan |
|---|---|
| SEC-EXAM-01 | Payload soal ke peserta hanya berisi: `question_id` (UUID per attempt — lihat SEC-EXAM-02), teks soal, opsi (ID opsi teracak per attempt + teks), tipe, poin. **Tidak** berisi `is_correct`, `accepted_answers`, pembahasan, tingkat kesulitan, tag, atau jumlah jawaban benar (untuk multi-jawaban hanya "pilih semua yang benar"). Diuji otomatis dengan memeriksa respons/Livewire snapshot. |
| SEC-EXAM-02 | ID opsi yang dikirim ke klien adalah **alias per attempt** (mis. HMAC(attempt_id, option_id) dipotong) sehingga ID tidak dapat dikorelasikan antar peserta/attempt untuk membangun "kunci jawaban bersama". |
| SEC-EXAM-03 | Pembahasan & jawaban benar hanya ditampilkan sesuai `review_policy` (default ujian akhir: **setelah jendela ujian ditutup untuk semua peserta**, bukan setelah submit), sehingga peserta pertama tidak dapat membocorkan kunci ke peserta berikutnya. |
| SEC-EXAM-10 | Izin `assessment.view_answer_key` hanya untuk trainer pengampu program terkait & Admin Akademik; setiap tampilan/ekspor bank soal tercatat di audit (`question_bank.viewed`, `question_bank.exported`). Ekspor bank soal memerlukan re-auth & diberi watermark nama pengekspor. |
| SEC-EXAM-11 | Bank soal ujian akhir disarankan ≥ 3× jumlah soal per attempt, dengan pemilihan acak per tag/kesulitan (`selection_rules`) sehingga tiap peserta mendapat kombinasi berbeda; statistik paparan soal (berapa kali muncul) dipantau untuk rotasi. |
| SEC-EXAM-12 | Soal & opsi dirender sebagai teks ter-escape (atau HTML tersanitasi); penyalinan teks dapat dipersulit di UI (opsional) — **bukan** kontrol keamanan utama. |
| SEC-EXAM-13 | Deteksi kebocoran: pencarian berkala frasa unik soal di mesin pencari/forum (manual/semi-otomatis); soal yang bocor dinonaktifkan & diganti (versi soal tercatat pada attempt). |

## 2. Siklus Attempt

| ID | Kebutuhan |
|---|---|
| SEC-EXAM-04 | Skor dihitung **hanya di server** saat submit (atau auto-submit) dari `attempt_answers` terhadap versi soal yang di-*snapshot* di attempt. Endpoint submit tidak menerima skor, status, atau jumlah benar. Hasil disimpan sekali; perubahan skor sesudahnya hanya lewat *regrade* terotorisasi (mis. soal dibatalkan) yang tercatat di audit & memberi notifikasi. |
| SEC-EXAM-05 | Waktu ujian ditegakkan server: `deadline_at = min(started_at + duration, closes_at)`; jawaban setelah `deadline_at + grace` (grace 30 detik untuk latensi) ditolak **409**; job terjadwal (tiap menit) meng-*auto-submit* attempt yang lewat tenggat. Jam klien hanya untuk tampilan (disinkronkan dari server). |
| SEC-EXAM-06 | Maksimal **satu attempt aktif** per (asesmen, enrollment) — indeks unik parsial di DB. Membuka ujian di tab/perangkat lain melanjutkan attempt yang sama (tidak membuat baru, timer tidak di-reset). |
| SEC-EXAM-07 | Syarat memulai attempt diperiksa server secara atomik: enrollment aktif & milik pengguna, prasyarat modul terpenuhi, dalam jendela buka–tutup, sisa kesempatan > 0, jeda (*cooldown*) terpenuhi. Nomor attempt diambil dalam transaksi dengan `lockForUpdate` pada enrollment. |
| SEC-EXAM-08 | Autosave jawaban idempoten per `(attempt, question)`; validasi bahwa `question_id` & `option_id` benar-benar bagian dari attempt tersebut; rate limit 60/menit per attempt. |
| SEC-EXAM-09 | Reset/penambahan kesempatan hanya oleh trainer pengampu/Admin Akademik dengan alasan wajib; attempt dapat di-*void* (mis. terbukti curang) dengan alasan — keduanya tercatat & memicu notifikasi ke peserta. |

## 3. Anti-Kecurangan (Proporsional & Menghormati Privasi)

| ID | Kebutuhan |
|---|---|
| SEC-EXAM-14 | Selama attempt ujian akhir aktif, akun hanya boleh punya **satu sesi aktif yang terkait attempt**; login dari perangkat lain menampilkan peringatan dan mencatat indikator `concurrent_session` (tidak otomatis menggagalkan). |
| SEC-EXAM-15 | Indikator integritas (disimpan di `exam_attempts.integrity_flags`): jumlah & durasi kehilangan fokus/pindah tab, keluar dari mode layar penuh (bila diaktifkan), salin/tempel, perubahan IP/ASN/perangkat di tengah attempt, kecepatan jawab tidak wajar (mis. < 3 detik/soal untuk seluruh soal), pola jawaban identik antar peserta dalam kelas yang sama. Indikator **tidak** otomatis menjatuhkan sanksi — ditampilkan kepada trainer/Admin Akademik saat review & approval (SEC-CERT-15). |
| SEC-EXAM-16 | Peserta diberi tahu secara transparan (sebelum mulai ujian) data apa yang dicatat selama ujian & tujuannya (UU PDP: transparansi). **Tidak** ada perekaman kamera/mikrofon/layar di rilis 1.0; bila kelak ditambahkan → DPIA + persetujuan eksplisit (data biometrik = data pribadi spesifik). |

## 4. Progres Belajar

| ID | Kebutuhan |
|---|---|
| SEC-EXAM-17 | Laporan progres video dari player (*heartbeat* tiap 15–30 detik berisi posisi) divalidasi server: posisi maju tidak boleh melebihi waktu nyata berlalu × kecepatan putar maksimum (2×) + toleransi; lompatan besar tidak dihitung sebagai "ditonton". Status "selesai" = akumulasi detik unik tertonton ≥ 90% durasi. Rate limit heartbeat. Progres **bukan** satu-satunya syarat kelulusan (ujian akhir wajib), sehingga manipulasi progres berdampak terbatas. |
| SEC-EXAM-18 | "Tandai selesai" untuk PDF/teks mencatat waktu buka pertama; waktu baca tidak wajar (mis. 50 halaman < 30 detik) dicatat sebagai indikator, bukan penolakan. |

## 5. Penilaian Manual (Tugas & Esai)

| ID | Kebutuhan |
|---|---|
| SEC-EXAM-19 | Hanya trainer pengampu kelas (atau Admin Akademik untuk override) yang dapat menilai; nilai 0–100 divalidasi; setiap perubahan nilai menyimpan riwayat (`submission_reviews`) dengan nilai lama/baru & alasan; perubahan nilai setelah sertifikat terbit memerlukan alur pencabutan/penerbitan ulang. |
| SEC-EXAM-20 | Trainer tidak dapat menilai pengumpulan miliknya sendiri (bila trainer juga peserta program lain) — policy memeriksa `submission.user_id ≠ reviewer`. |
| SEC-EXAM-21 | Berkas tugas yang dinilai tetap tersimpan tidak berubah (hash) sebagai bukti; revisi disimpan sebagai berkas baru. |

## 6. Presensi

| ID | Kebutuhan |
|---|---|
| SEC-EXAM-22 | Token QR presensi = `base64url(session_id | window | HMAC(qr_secret, session_id|window))` dengan `window` = slot 30 detik; server menerima slot saat ini & sebelumnya (maks. 60 detik); token hanya valid dalam `checkin_opens_at`–`checkin_closes_at`; satu check-in per peserta per sesi (unik); peserta harus login & ter-enroll. Karena QR berganti cepat, *screenshot* yang dibagikan cepat kedaluwarsa. Opsional: kode PIN tambahan yang diucapkan trainer di kelas. |
| SEC-EXAM-23 | Perubahan presensi manual setelah sesi ditutup memerlukan alasan & tercatat di audit; peserta dapat melihat riwayat presensinya. |

## 7. Uji Wajib

Lihat [`../10-strategi-pengujian.md`](../10-strategi-pengujian.md): payload tanpa kunci, skor dari
server, submit setelah deadline, attempt paralel (race), manipulasi `option_id` dari attempt lain,
auto-submit, review policy, validasi heartbeat video, replay token QR presensi, SoD penilaian.
