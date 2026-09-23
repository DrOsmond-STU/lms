# 12 — Privasi Data & Kepatuhan UU PDP

> Status: **Disetujui untuk development** · Versi 1.0 · Pemilik: DPO + Security Lead
>
> Dasar hukum utama: **UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi (UU PDP)** beserta
> peraturan pelaksananya, UU ITE beserta perubahannya, PP No. 71 Tahun 2019 (PSTE), dan ketentuan
> pendaftaran PSE Lingkup Privat.
>
> ⚠️ Dokumen ini adalah **spesifikasi teknis-operasional**, bukan nasihat hukum. Rujukan pasal,
> batas waktu, dan kewajiban wajib diverifikasi tim legal/DPO terhadap peraturan pelaksana yang
> berlaku saat go-live, dan dokumen ini diperbarui bila ada perbedaan.

## 1. Peran Para Pihak

| Aktivitas pemrosesan | STU | Organisasi mitra |
|---|---|---|
| Akun & profil peserta mandiri | **Pengendali** | — |
| Penyelenggaraan pelatihan, asesmen, sertifikat | **Pengendali** | — (institusi/korporat menerima laporan) |
| Pendaftaran & pemantauan anggota oleh organisasi (bulk enroll, laporan tim, API HRIS) | **Pengendali bersama** atau **Prosesor** — ditetapkan per kontrak | Pengendali (atas data anggota yang mereka berikan & terima) |
| Verifikasi sertifikat publik | **Pengendali** | — |
| Pembayaran | **Pengendali** | Payment gateway = Pengendali/Prosesor terpisah sesuai perjanjiannya |

| ID | Kebutuhan |
|---|---|
| SEC-PRIV-01 | Setiap organisasi mitra menandatangani **perjanjian pemrosesan data** (DPA / perjanjian pengendali bersama) sebelum mengaktifkan bulk enroll, laporan tim, atau API, yang mengatur: tujuan, jenis data, pembagian tanggung jawab hak subjek data, keamanan, sub-prosesor, notifikasi insiden, dan pengembalian/penghapusan data. |
| SEC-PRIV-02 | STU menunjuk **Pejabat/Petugas Pelindungan Data Pribadi (DPO)** — pemrosesan berskala besar dan pemantauan sistematis kegiatan belajar memenuhi kriteria kewajiban penunjukan; kontak DPO dipublikasikan di Kebijakan Privasi. |

## 2. Catatan Aktivitas Pemrosesan (RoPA) — Ringkas

| # | Aktivitas | Subjek | Data | Dasar pemrosesan | Penerima | Retensi |
|---|---|---|---|---|---|---|
| R1 | Registrasi & akun | Peserta, trainer, admin | Nama, email, HP, kata sandi (hash), organisasi | Perjanjian (S&K) | — | Selama akun aktif |
| R2 | Profil akademik/kepegawaian | Peserta | Nomor induk, prodi/departemen, semester | Perjanjian; kepentingan sah organisasi | Admin organisasi terkait | Selama akun aktif |
| R3 | Pembelajaran & progres | Peserta | Progres, waktu akses, presensi, jawaban, nilai, berkas tugas | Perjanjian | Trainer kelas, admin organisasi (agregat & per anggota) | 2 tahun setelah kelas ditutup |
| R4 | Integritas ujian | Peserta | Indikator fokus tab, IP, perangkat selama ujian | Kepentingan sah (integritas sertifikasi), dengan pemberitahuan | Trainer/admin akademik | 2 tahun |
| R5 | Penerbitan & verifikasi sertifikat | Peserta | Nama, program, nomor, tanggal, status | Perjanjian; kepentingan sah verifikasi pihak ketiga | Publik (data minimal) | Masa berlaku + 10 tahun |
| R6 | Pembayaran & faktur | Peserta, PIC korporat | Nama, email, jumlah, metode, NPWP (opsional) | Perjanjian; kewajiban hukum (perpajakan) | Payment gateway, konsultan pajak | 10 tahun |
| R7 | Notifikasi email/WA | Semua | Email, HP, isi notifikasi | Perjanjian (transaksional); **persetujuan** (WA & pemasaran) | Penyedia email/WA | Log pengiriman 90 hari |
| R8 | Diskusi | Peserta, trainer | Isi posting, nama tampilan | Perjanjian | Anggota kelas | Selama kelas + 2 tahun |
| R9 | Gamifikasi & leaderboard | Peserta | Poin, lencana, nama tampilan | Perjanjian (fitur); **persetujuan** untuk tampil dengan nama lengkap | Anggota kelas/organisasi | Selama akun aktif |
| R10 | Keamanan & audit | Semua | IP, user agent, aktivitas, event keamanan | Kewajiban hukum (PSTE); kepentingan sah keamanan | Tim keamanan | 1–2 tahun (lihat [11](11-logging-audit-dan-monitoring.md)) |
| R11 | Dukungan pelanggan | Semua | Isi tiket, kontak | Perjanjian | Tim dukungan | 2 tahun |
| R12 | Testimoni beranda | Peserta/mitra | Nama, peran, kutipan, foto | **Persetujuan** eksplisit | Publik | Sampai persetujuan ditarik |

RoPA lengkap (termasuk sub-prosesor, lokasi data, dan langkah keamanan per aktivitas) dipelihara
DPO dan ditinjau tiap 6 bulan atau saat ada fitur baru yang memproses data pribadi.

## 3. Prinsip & Privacy by Design

| ID | Kebutuhan |
|---|---|
| SEC-PRIV-03 | **Minimisasi**: hanya kumpulkan field yang diperlukan tujuan; NIK tidak dikumpulkan kecuali diwajibkan skema (lalu K4); tanggal lahir tidak dikumpulkan — cukup konfirmasi usia ≥ 18 (atau tahun lahir untuk validasi). Foto profil opsional. |
| SEC-PRIV-04 | **Default privasi**: leaderboard menampilkan nama depan + inisial; verifikasi publik menampilkan data minimal ([07](07-integritas-sertifikat.md) SEC-CERT-11); email/HP disamarkan di tabel admin; admin organisasi hanya melihat anggota organisasinya; agregat < 5 orang tidak ditampilkan. |
| SEC-PRIV-05 | **Transparansi**: Kebijakan Privasi berbahasa Indonesia yang jelas & berversi; pemberitahuan kontekstual (*just-in-time*) saat mengumpulkan data baru, sebelum ujian (data integritas yang dicatat), saat organisasi mendaftarkan peserta (peserta diberi tahu organisasi dapat melihat progresnya). |
| SEC-PRIV-06 | **Tanpa pelacak pihak ketiga** di halaman terautentikasi (tidak ada Facebook Pixel, Google Analytics dengan ID pengguna, dsb.). Analitik produk memakai solusi self-hosted/berbasis agregat tanpa PII; cookie non-esensial hanya dengan persetujuan (banner cookie dengan pilihan tolak yang setara). |
| SEC-PRIV-07 | **Pseudonimisasi** di analitik & laporan internal (UUID, bukan nama/email); lingkungan non-produksi **tidak** memakai data produksi. |
| SEC-PRIV-08 | Pengambilan keputusan otomatis: skor ujian dihitung otomatis, tetapi **penerbitan sertifikat selalu melalui keputusan manusia** (approval), dan peserta dapat mengajukan keberatan/banding atas hasil (alur tiket ke Admin Akademik). Indikator anti-curang tidak memicu sanksi otomatis ([08](08-integritas-ujian-dan-penilaian.md) SEC-EXAM-15). |

## 4. Persetujuan (Consent)

| ID | Kebutuhan |
|---|---|
| SEC-PRIV-09 | Persetujuan dicatat di `consents` per tujuan dengan versi dokumen, waktu, dan bukti (hash IP, user agent); tidak ada kotak centang yang tercentang otomatis; persetujuan opsional (WA, pemasaran, leaderboard nama lengkap, testimoni) **terpisah** dari S&K dan dapat ditarik kapan saja semudah memberikannya (menu Profil → Privasi). |
| SEC-PRIV-10 | Perubahan material Kebijakan Privasi/S&K → pengguna diminta meninjau & menyetujui ulang saat login berikutnya (FR-CMS-003); riwayat versi tersedia publik. |
| SEC-PRIV-11 | **Data anak** (< 18 tahun): registrasi mandiri ditolak; pendaftaran melalui organisasi (mis. SMK) mensyaratkan persetujuan orang tua/wali terdokumentasi (`consents.purpose = 'guardian'`) yang dikumpulkan organisasi, dengan fitur yang dibatasi (tanpa leaderboard publik, tanpa diskusi lintas organisasi). |

## 5. Hak Subjek Data

| Hak | Implementasi di platform | Target waktu |
|---|---|---|
| Informasi tentang pemrosesan | Kebijakan Privasi, halaman "Data Saya" | Selalu tersedia |
| Akses & salinan data | Ekspor mandiri (JSON + ringkasan PDF) dari Profil; tautan 24 jam, re-auth | ≤ 3×24 jam (umumnya instan/otomatis) |
| Pembetulan | Ubah profil mandiri; field tersinkron organisasi → permintaan ke admin organisasi/STU; nama pada sertifikat → alur reissue | ≤ 3×24 jam setelah verifikasi |
| Penghapusan / pemusnahan | Permintaan hapus akun → verifikasi identitas → anonimisasi (lihat §6) | Konfirmasi ≤ 3×24 jam; eksekusi ≤ 30 hari (kecuali data dengan kewajiban retensi hukum) |
| Penarikan persetujuan | Toggle di Profil → Privasi (efektif segera) | Segera |
| Pembatasan / penundaan pemrosesan | Permintaan ke DPO; akun ditandai `restricted` | ≤ 3×24 jam |
| Keberatan atas keputusan otomatis | Banding hasil asesmen ke Admin Akademik | Sesuai SLA akademik |
| Portabilitas | Ekspor format terstruktur (JSON/CSV) | Sama dengan akses |

| ID | Kebutuhan |
|---|---|
| SEC-PRIV-12 | Setiap permintaan tercatat di `privacy_requests` dengan tenggat (`due_at`) & pengingat otomatis ke DPO/admin H-1; status dapat dipantau pengguna. |
| SEC-PRIV-13 | **Verifikasi identitas** sebelum ekspor/hapus: pengguna login + re-auth (MFA bila aktif); permintaan via email/telepon tanpa login memerlukan verifikasi tambahan terdokumentasi. Hasil ekspor hanya dikirim ke akun terverifikasi (bukan ke email yang diberikan penelepon). |
| SEC-PRIV-14 | Ekspor data pribadi memuat seluruh data pengguna lintas modul (profil, enrollment, progres, nilai, sertifikat, transaksi, notifikasi, diskusi, persetujuan, riwayat login 90 hari) tetapi **tidak** memuat data pribadi orang lain (mis. nama peserta lain di diskusi disamarkan) atau kunci jawaban. |

## 6. Penghapusan & Anonimisasi Akun

Urutan eksekusi job `AnonymizeUser` (idempoten, tercatat):

1. Cabut semua sesi, token, API key terkait; nonaktifkan login (`status = anonymized`).
2. Ganti `name` → `Pengguna Terhapus #{hash pendek}`, `email` → `deleted+{uuid}@invalid.local`, hapus `phone`, foto, `participant_number`, preferensi, device cookie.
3. Hapus berkas tugas & lampiran diskusi milik pengguna (kecuali terikat sengketa/penyelidikan yang terdokumentasi).
4. Isi diskusi: dihapus atau diganti "[dihapus atas permintaan pengguna]".
5. Jawaban ujian: dihapus bila kelas sudah ditutup & tidak ada sengketa; skor agregat dipertahankan tanpa identitas.
6. **Sertifikat**: pengguna memilih (a) sertifikat **dicabut** dengan alasan `holder_request` → catatan minimal (nomor, status dicabut, tanggal) dipertahankan untuk menjawab verifikasi "dicabut"; atau (b) sertifikat tetap aktif → nama pemegang & data minimal sertifikat dipertahankan atas dasar kepentingan sah verifikasi (dijelaskan saat permintaan). Default: (a) bila pengguna tidak memilih.
7. Transaksi & invoice: dipertahankan 10 tahun (kewajiban hukum) dengan akses dibatasi `finance_admin`; setelah retensi → dianonimkan.
8. Audit log: tidak dihapus; referensi aktor/subjek dipseudonimkan setelah retensi berakhir.
9. Backup: data terhapus ikut hilang saat backup kedaluwarsa (maks. 35 hari/12 bulan untuk bulanan); bila restore dilakukan, daftar ID yang sudah dianonimkan (*tombstone list*) diterapkan ulang otomatis.
10. Kirim konfirmasi ke pengguna (ke email lama sebelum dihapus) & catat penyelesaian.

## 7. Retensi Data

| Kategori | Retensi | Tindakan akhir |
|---|---|---|
| Akun tidak aktif (peserta) | 5 tahun tanpa login & tanpa sertifikat aktif | Email pemberitahuan 30 hari sebelumnya → anonimisasi |
| Profil & data akun | Selama akun aktif | Anonimisasi saat dihapus |
| Progres, jawaban ujian, berkas tugas, presensi | 2 tahun setelah kelas ditutup | Hapus (agregat dipertahankan anonim) |
| Indikator integritas ujian | 2 tahun | Hapus |
| Catatan sertifikat minimal & PDF | Masa berlaku + 10 tahun | Hapus PDF; catatan minimal dianonimkan kecuali status dicabut |
| Transaksi, invoice | 10 tahun | Anonimisasi |
| Diskusi | Selama kelas + 2 tahun | Arsip anonim/hapus |
| Notifikasi in-app | 1 tahun | Hapus |
| Log pengiriman email/WA | 90 hari | Hapus |
| Log aplikasi | 90 hari | Hapus |
| Security events | 1 tahun online + 1 tahun arsip | Hapus |
| Audit log | 2 tahun (5 tahun untuk sertifikat & keuangan) | Pseudonimisasi/hapus per partisi |
| Log verifikasi sertifikat | 1 tahun | Hapus |
| Login attempts | 90 hari | Hapus |
| Berkas ekspor/impor sementara | ≤ 24 jam | Hapus (lifecycle) |
| Backup DB | 35 hari harian, 12 bulan bulanan | Kedaluwarsa otomatis |
| Permintaan privasi | 3 tahun setelah selesai (bukti kepatuhan) | Hapus |

| ID | Kebutuhan |
|---|---|
| SEC-PRIV-15 | Retensi dieksekusi otomatis oleh job terjadwal (`data_retention_runs` mencatat jumlah per kategori); kegagalan job → alert; laporan retensi bulanan ke DPO. |

## 8. Transfer Data Lintas Negara

| ID | Kebutuhan |
|---|---|
| SEC-PRIV-16 | Data primer (DB, object storage, backup) disimpan di **region Indonesia**. Layanan yang mungkin memproses data di luar negeri (email transaksional, error tracking, CDN edge, CAPTCHA, HIBP k-anonymity) dinilai: data apa yang dikirim, negara tujuan, tingkat pelindungan, dan mekanisme yang sah (negara setara/lebih tinggi, perlindungan memadai via perjanjian, atau persetujuan) — dicatat di RoPA. Minimisasi: error tracking tanpa PII, CDN tidak meng-cache halaman berisi data pribadi, HIBP hanya menerima 5 karakter prefiks hash. |

## 9. Penilaian Dampak (DPIA)

| ID | Kebutuhan |
|---|---|
| SEC-PRIV-17 | **DPIA wajib sebelum go-live** untuk: (1) platform secara keseluruhan (pemrosesan berskala besar, pemantauan aktivitas belajar); (2) indikator integritas ujian; (3) berbagi data dengan organisasi via laporan/API; (4) verifikasi sertifikat publik. DPIA tambahan **sebelum** fitur: proctoring kamera/biometrik, geolokasi presensi, integrasi AI yang memproses jawaban/esai peserta, data anak. Template DPIA: deskripsi pemrosesan, kebutuhan & proporsionalitas, risiko bagi subjek, mitigasi, risiko residual, persetujuan DPO. |

## 10. Kegagalan Pelindungan Data Pribadi (Data Breach)

| ID | Kebutuhan |
|---|---|
| SEC-PRIV-18 | Bila terjadi kegagalan PDP, STU menyampaikan **pemberitahuan tertulis paling lambat 3×24 jam** kepada subjek data dan lembaga/otoritas PDP yang berwenang, memuat: data pribadi yang terungkap, kapan & bagaimana terungkap, serta upaya penanganan & pemulihan; dan bila mengganggu pelayanan publik/berdampak serius terhadap kepentingan masyarakat, pemberitahuan kepada masyarakat. Organisasi mitra yang terdampak juga diberi tahu sesuai DPA. Prosedur, template, dan alur keputusan di [15](15-respons-insiden-dan-kontinuitas.md). |

## 11. Tata Kelola Internal

| ID | Kebutuhan |
|---|---|
| SEC-PRIV-19 | Semua personel dengan akses data pribadi menandatangani perjanjian kerahasiaan, mengikuti pelatihan PDP & keamanan saat *onboarding* dan tahunan; akses dicabut ≤ 24 jam saat *offboarding*. |
| SEC-PRIV-20 | Akses personel STU ke data pribadi produksi terbatas pada peran yang membutuhkan (need-to-know), tercatat, dan ditinjau bulanan. Dukungan pelanggan melihat data tersamar kecuali pengguna memberikan izin dalam tiket. |
| SEC-PRIV-21 | **Pendaftaran PSE Lingkup Privat** ke kementerian yang berwenang dilakukan sebelum go-live, dan diperbarui bila ada perubahan sistem yang material. |
| SEC-PRIV-22 | Audit kepatuhan PDP internal tahunan oleh DPO; temuan dilacak sampai tuntas. |
