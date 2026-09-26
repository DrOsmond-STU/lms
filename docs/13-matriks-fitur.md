# 13 — Matriks Fitur LMS (Status Implementasi)

> Pemetaan sepuluh kategori fitur LMS terhadap implementasi di kode, halaman antarmuka, perintah
> terjadwal, dan berkas uji. ✅ = tersedia dan teruji otomatis · ⚙️ = tersedia setelah dikonfigurasi
> admin (Pengaturan Sistem → Integrasi) · ⏳ = belum ada. Diperbarui saat Fase 2 (fitur lengkap).

## 1. Manajemen pengguna

| Fitur | Status | Lokasi |
|---|---|---|
| Registrasi mandiri (OTP email), login, lupa/atur ulang kata sandi, MFA TOTP | ✅ | `app/Modules/Identity`, `RegistrationTest`, `MfaTest` |
| Peran & izin: Super Admin, Admin Akademik/Keuangan/Layanan, Admin Organisasi, **Supervisor** (organisasi, hanya baca), Trainer, Peserta | ✅ | `app/Modules/Access/{RoleCode,Permissions}.php`, `AdministrationTest` |
| Manajemen kelas (batch, kuota, trainer, status) & **kelompok belajar** dengan pembagian massal | ✅ | `ClassAdminController`, `ClassGroupController`, tab *Kelompok* |
| Profil pengguna, sesi & perangkat, persetujuan, privasi (unduh data, hapus akun) | ✅ | `AccountController`, `SecurityOpsTest` |

## 2. Manajemen pembelajaran

| Fitur | Status | Lokasi |
|---|---|---|
| Course/program → kelas → modul → bab → lesson | ✅ | `app/Modules/Catalog`, `app/Modules/Learning` |
| Materi: video, PDF, PPT/Word/Excel (dokumen), audio, teks, tautan, kuis | ✅ | `Lesson::TYPES`, `config/media.php`, `ContentScheduleTest` |
| Learning path (urutan wajib), prasyarat antar lesson, drip content (tanggal/hari ke-N) | ✅ | `LessonAvailability`, tab *Pengaturan* kelas |
| Jadwal: sesi tatap muka & live class (tautan meeting), kalender akademik, menu Jadwal | ✅ | `ClassSessionController`, `AcademicCalendarController`, `ScheduleController` |
| Progress tracking (materi, durasi belajar, heartbeat video/audio) | ✅ | `ProgressService`, `lesson_progress.time_spent_seconds` |

## 3. Quiz & assessment

| Fitur | Status | Lokasi |
|---|---|---|
| Tipe soal: pilihan ganda (tunggal/multi), benar–salah, isian singkat, esai, **menjodohkan** | ✅ | `Question::TYPES`, `AssessmentExtensionsTest` |
| Bank soal, randomisasi soal & opsi, timer server, passing grade, percobaan ulang, auto-grading | ✅ | `AttemptService`, `ExamIntegrityTest` |
| Penilaian manual esai dengan **rubrik** & umpan balik; saran skor AI | ✅ / ⚙️ | `assessments/grade`, `AiAssistant::essayFeedback` |
| Pre-test / post-test (diagnostik, kenaikan skor di laporan) | ✅ | `Assessment::KINDS` |
| Tugas/assignment: unggah berkas/teks, tenggat, keterlambatan, rubrik, revisi | ✅ | `AssignmentService`, tab *Tugas* |

## 4. Kelas interaktif

| Fitur | Status | Lokasi |
|---|---|---|
| Forum diskusi, tanya jawab (jawaban terpilih), komentar per materi | ✅ | `DiscussionController`, `DiscussionTest` |
| Live class (tautan meeting terbuka 30 menit sebelum mulai) & presensi (cek-in kode/manual) | ✅ | `ClassSession`, `AttendanceRecord` |
| Chat kelas (Livewire, polling 10 dtk, moderasi) | ✅ | `App\Livewire\ClassChat` |
| Polling (anonim/multi, tutup, hasil agregat) | ✅ | `PollController` |
| Moderasi & laporan konten; notifikasi aktivitas | ✅ | `discussion/reports`, `Notifier` |

## 5. Sertifikat

| Fitur | Status | Lokasi |
|---|---|---|
| Sertifikat otomatis setelah lulus (approval SoD), template berversi, tanda tangan digital, QR, verifikasi publik + API, pencabutan | ✅ | `app/Modules/Certification`, `CertificationTest` |

## 6. Dashboard & analitik

| Fitur | Status | Lokasi |
|---|---|---|
| Siswa: progres, nilai & riwayat (semua attempt, tugas, durasi), course berjalan, tenggat terdekat | ✅ | `dashboards/participant`, `learning/grades` |
| Guru/admin: jumlah peserta, tingkat penyelesaian, rata-rata nilai, aktivitas mingguan, peserta tidak aktif, hasil kuis per asesmen, durasi belajar, presensi, materi & soal tersulit | ✅ | `ClassReportService`, tab *Laporan*, menu Laporan trainer |
| Ekspor Excel (XLSX) / PDF / CSV: buku nilai, laporan kelas, laporan organisasi | ✅ | `App\Support\Export\TableExport`, `AnalyticsTest` |

## 7. Administrasi

| Fitur | Status | Lokasi |
|---|---|---|
| Kalender akademik, jadwal kelas, enrollment mandiri/admin/organisasi | ✅ | `AcademicCalendarController`, `EnrollmentService` |
| Approval pendaftaran (kelas `requires_approval` → setujui/tolak) | ✅ | `ClassManageController::decideEnrollment` |
| Manajemen batch, kehadiran (rekap & ekspor), buku nilai (gradebook) | ✅ | tab *Sesi & Presensi*, *Nilai* |
| Pengumuman (platform/organisasi/kelas, sematkan, jadwal, notifikasi) | ✅ | `AnnouncementController`, dashboard & `/pengumuman` |

## 8. Notifikasi

| Fitur | Status | Lokasi |
|---|---|---|
| Notifikasi in-app & email; preferensi kanal & kategori per pengguna | ✅ | `Notifier`, `NotificationPreferenceController` |
| Push notification peramban/ponsel (Web Push, VAPID, service worker) | ⚙️ | `WebPush`, `public/sw.js`, Pengaturan → Integrasi |
| WhatsApp (gateway HTTP generik, uji kirim, log pesan) | ⚙️ | `WhatsAppGateway` |
| Reminder deadline tugas/sesi, course baru, nilai keluar, sertifikat terbit, pengingat tidak aktif | ✅ | `stu:reminders` (tiap jam), `NotificationChannelsTest` |

## 9. Fitur AI (Claude)

| Fitur | Status | Lokasi |
|---|---|---|
| Tutor/chatbot materi (riwayat per peserta, tanpa jawaban ujian) | ⚙️ | `App\Livewire\AiTutor` |
| Generate soal otomatis (draf nonaktif bertag `draf-ai`) | ⚙️ | Bank soal → *Buat soal dengan AI* |
| Rangkuman materi, rekomendasi belajar, jalur belajar personal | ⚙️ | halaman materi, kelas, `/peserta/jalur-belajar` |
| Feedback otomatis esai (saran skor & umpan balik) | ⚙️ | halaman penilaian esai |
| Deteksi materi/soal sulit (analitik, tanpa AI) + analisis kelas AI | ✅ / ⚙️ | tab *Laporan* |
| Bantu guru merancang kurikulum (modul → bab → lesson draf) | ⚙️ | tab *Konten* → *Rancang Kurikulum dengan AI* |
| Kunci API terenkripsi, batas harian per pengguna, log pemakaian | ✅ | `ClaudeClient`, `AiAssistantTest` |

## 10. Keamanan

| Fitur | Status | Lokasi |
|---|---|---|
| RBAC + RLS PostgreSQL, MFA, audit log berantai hash, session management, re-autentikasi | ✅ | `docs/keamanan/`, `tests/Security` |
| Backup otomatis harian (pg_dump/fallback, enkripsi libsodium, unduh, masa simpan) | ✅ | `stu:backup`, menu *Backup* |
| Enkripsi data sensitif (HP, kunci isian, rahasia integrasi), HTTPS/HSTS/CSP | ✅ | `config/security.php`, `SystemSettings` tipe `secret` |
| Pembatasan akses materi (URL bertanda tangan terikat pengguna) & proteksi soal (alias per attempt, kunci terenkripsi) | ✅ | `MediaStorage`, `AttemptService` |
| Privasi & retensi: unduh data, anonimisasi atas permintaan, pemangkasan otomatis sesuai masa simpan | ✅ | `PrivacyService`, `stu:retention-prune` |
| Monitoring aktivitas mencurigakan (brute force, akun ditarget, anomali sesi, ekspor massal) | ✅ | `stu:security-scan`, menu *Pemantauan Keamanan* |

## Perintah terjadwal (`routes/console.php`)

| Perintah | Jadwal | Fungsi |
|---|---|---|
| `stu:exams-auto-submit` | tiap menit | Kumpulkan attempt kedaluwarsa |
| `stu:payments-expire` | tiap jam | Kedaluwarsakan tagihan tanpa bukti |
| `stu:reminders` | tiap jam | Pengingat tenggat, sesi, tidak aktif, program baru |
| `stu:security-scan` | tiap jam | Deteksi aktivitas mencurigakan |
| `stu:backup` | 01.30 | Backup basis data (+ media bila diaktifkan) |
| `stu:audit-verify` | 02.30 | Verifikasi rantai hash audit |
| `stu:prune-unverified` | 03.00 | Bersihkan registrasi tak terverifikasi |
| `stu:retention-prune` | 03.30 | Pangkas data sesuai retensi |
| `stu:certificates-expiry-reminders` | 08.00 | Pengingat sertifikat kedaluwarsa |
