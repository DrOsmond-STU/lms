# 07 — Peran, Hak Akses (RBAC) & Multi-Tenant

> Status: **Disetujui untuk development** · Versi 1.0 · Pemilik: Tech Lead + Security Lead
>
> Implementasi teknis & kontrol keamanan otorisasi dirinci di
> [`keamanan/03-otorisasi-dan-isolasi-tenant.md`](keamanan/03-otorisasi-dan-isolasi-tenant.md).

## 1. Model Otorisasi

Otorisasi memakai tiga lapis yang **semuanya** harus lolos:

1. **RBAC (izin)** — apakah peran pengguna memiliki izin `resource.action`?
2. **Scope tenant** — apakah objek berada di organisasi yang boleh diakses pengguna?
3. **Policy kepemilikan/kontekstual (ABAC)** — apakah pengguna terkait langsung dengan objek
   (mis. peserta pemilik enrollment, trainer pengampu kelas), dan apakah kondisi bisnis
   terpenuhi (mis. kelas belum ditutup)?

```
izin_diberikan = punya_permission(user, aksi)
              AND objek.organization_id ∈ org_scope(user)      -- dikecualikan untuk peran platform
              AND policy(user, objek, konteks) == allow
```

Penolakan default (**deny by default**): tidak ada izin = ditolak. Respons untuk objek di luar
scope adalah **404** (bukan 403) agar keberadaan objek tidak bocor.

## 2. Daftar Peran

| Kode peran | Nama | Lingkup | Cara mendapatkan | MFA |
|---|---|---|---|---|
| `super_admin` | Super Admin | Seluruh platform | Hanya oleh Super Admin lain, dengan persetujuan 2 orang (*four-eyes*). Maks. 3 akun. | **Wajib** (WebAuthn/Passkey disarankan) |
| `academic_admin` | Admin Akademik | Seluruh platform (data akademik) | Ditetapkan Super Admin | **Wajib** |
| `finance_admin` | Admin Keuangan | Seluruh platform (data transaksi) | Ditetapkan Super Admin | **Wajib** |
| `support_admin` | Admin Layanan (opsional) | Baca-saja data pengguna untuk bantuan; reset MFA dengan verifikasi | Ditetapkan Super Admin | **Wajib** |
| `org_admin` | Admin Organisasi | Satu/lebih organisasi tertentu | Ditetapkan Super Admin / Admin Akademik | **Wajib** |
| `supervisor` | Supervisor | Satu/lebih organisasi tertentu, **hanya baca** (progres, nilai, presensi, laporan, pengumuman) | Ditetapkan Super Admin / Admin Akademik | **Wajib** |
| `trainer` | Trainer | Kelas yang diampu | Ditetapkan Admin Akademik | **Wajib** |
| `participant` | Peserta | Data diri sendiri & kelas yang diikuti | Registrasi mandiri (verifikasi email/HP) atau didaftarkan organisasi | Opsional (dianjurkan) |
| — | Publik (anonim) | Halaman publik, katalog, verifikasi sertifikat | — | — |
| — | Klien API (mitra) | Scope API key (mis. `certificates:verify`, `enrollments:read` organisasi X) | Dibuat Super Admin untuk organisasi | N/A (kunci + IP allowlist) |

Catatan:

- Satu pengguna dapat memiliki lebih dari satu peran (mis. dosen yang juga peserta program lain),
  tetapi **peran admin platform tidak boleh digabung dengan peran `participant` pada akun yang
  sama** — gunakan akun terpisah.
- Penugasan peran bersifat per organisasi (`role_user.organization_id`) untuk `org_admin`,
  `trainer`, dan `participant`; `NULL` untuk peran platform. `org_admin` **wajib** terikat
  organisasi; `trainer` (trainer internal STU) dan `participant` (peserta umum hasil registrasi
  mandiri, atau yang keanggotaannya masih *pending*) boleh tanpa organisasi — datanya tetap
  hanya terlihat oleh dirinya sendiri & staf platform.
- Setiap perubahan peran tercatat di audit log dan memicu notifikasi ke pengguna & Super Admin.

## 3. Pemisahan Tugas (Segregation of Duties)

| Aturan SoD | Alasan |
|---|---|
| Trainer **tidak boleh** menyetujui penerbitan sertifikat, termasuk untuk kelasnya sendiri. | Mencegah konflik kepentingan / sertifikat "titipan". |
| Admin Akademik yang juga tercatat sebagai trainer suatu kelas **tidak boleh** menyetujui sertifikat kelas tersebut. | Idem. |
| Admin Keuangan tidak dapat mengubah status kelulusan; Admin Akademik tidak dapat menandai transaksi lunas/refund. | Pemisahan fungsi finansial & akademik. |
| Refund > Rp 1.000.000, penandaan lunas manual (FR-PAY-003), atau pencabutan sertifikat memerlukan **persetujuan kedua** (maker–checker). Daftar lengkap aksi maker–checker: [`keamanan/03`](keamanan/03-otorisasi-dan-isolasi-tenant.md) SEC-AUTHZ-15. | Tindakan berdampak tinggi. |
| Pembuatan/penghapusan akun `super_admin` & pembuatan API key memerlukan persetujuan Super Admin kedua. | Mencegah eskalasi oleh satu akun yang dibobol. |
| Tidak ada yang dapat mengubah/menghapus audit log (termasuk Super Admin). | Integritas forensik. |

## 4. Katalog Izin (Permission)

Konvensi: `resource.action`. Aksi standar: `view_any` (daftar), `view`, `create`, `update`,
`delete`, plus aksi khusus.

| Resource | Izin |
|---|---|
| `user` | `view_any`, `view`, `create`, `update`, `deactivate`, `impersonate` (**tidak diaktifkan** — lihat catatan), `reset_mfa`, `assign_role`, `export` |
| `organization` | `view_any`, `view`, `create`, `update`, `archive`, `manage_members`, `access_review` |
| `program` | `view_any`, `view`, `create`, `update`, `submit_review`, `publish`, `archive` |
| `course_class` | `view_any`, `view`, `create`, `update`, `archive`, `assign_trainer` |
| `content` (modul/bab/lesson/media) | `view`, `create`, `update`, `delete`, `publish` |
| `assessment` (kuis/ujian/bank soal) | `view`, `create`, `update`, `delete`, `view_answer_key`, `grade_manual`, `reset_attempt`, `attempt` (peserta mengerjakan) |
| `enrollment` | `view_any`, `view`, `create`, `bulk_create`, `cancel`, `override_status` |
| `assignment` | `view`, `create`, `update`, `delete` |
| `submission` | `view_any`, `view`, `create` (peserta), `review` |
| `attendance` | `view_any`, `manage_session`, `record_manual`, `check_in` (peserta) |
| `live_session` | `view`, `create`, `update`, `delete` |
| `certificate` | `view_any`, `view`, `approve`, `reject`, `revoke`, `reissue`, `download` |
| `certificate_template` | `view_any`, `create`, `update`, `activate` |
| `payment` | `view_any`, `view`, `refund_request`, `refund_approve`, `mark_paid_manual`, `reconcile`, `export` |
| `coupon` | `view_any`, `create`, `update`, `deactivate` |
| `discussion` | `view`, `post`, `moderate`, `report` |
| `gamification` | `view_leaderboard`, `manage` (aturan poin & lencana, aktif/nonaktif per organisasi) |
| `notification_setting` | `view`, `update` |
| `report` | `view_platform`, `view_organization`, `view_class`, `export` |
| `cms` | `view`, `update`, `publish` |
| `privacy_request` | `view_any`, `process`, `create` (peserta untuk dirinya) |
| `api_key` | `view_any`, `create`, `revoke` |
| `integration` | `view`, `update` |
| `system_setting` | `view`, `update` |
| `audit_log` | `view`, `export` |

> **Impersonasi ("login sebagai")** tidak disediakan pada rilis awal. Bila kelak dibutuhkan untuk
> dukungan: hanya `support_admin`, hanya baca, wajib alasan + tiket, banner merah permanen, sesi
> maks. 15 menit, dan tercatat di audit log serta dinotifikasikan ke pengguna.

## 5. Matriks Peran × Izin

Legenda: ✅ = semua data dalam lingkup · 🏢 = hanya organisasi yang dikelola · 📚 = hanya kelas
yang diampu · 👤 = hanya milik sendiri · ⚠️ = butuh persetujuan kedua · — = tidak ada akses.

| Kemampuan | Super Admin | Admin Akademik | Admin Keuangan | Admin Organisasi | Trainer | Peserta |
|---|---|---|---|---|---|---|
| Kelola pengguna (CRUD) | ✅ | ✅ (peserta & trainer) | — | 🏢 (peserta) | — | 👤 (profil terbatas) |
| Tetapkan peran | ✅ (⚠️ untuk super_admin) | ✅ (trainer, org_admin) | — | — | — | — |
| Reset MFA pengguna lain | ✅ (verifikasi identitas; ⚠️ untuk akun admin) | — | — | — | — | — |
| Kelola organisasi | ✅ | ✅ | — | 🏢 (profil & unit) | — | — |
| Program pelatihan | ✅ | ✅ | lihat harga | lihat | lihat | lihat (published) |
| Publikasikan program | ✅ | ✅ (setelah review) | — | — | — | — |
| Kelas & jadwal | ✅ | ✅ | — | 🏢 lihat | 📚 lihat & ubah jadwal | 👤 lihat kelas diikuti |
| Konten materi | ✅ | ✅ | — | — | 📚 | 👤 akses bila terdaftar |
| Bank soal & kunci jawaban | ✅ | ✅ | — | — | 📚 | — |
| Kerjakan kuis/ujian (`assessment.attempt`) | — | — | — | — | — | 👤 |
| Enrollment manual / massal | ✅ | ✅ | — | 🏢 (bulk untuk karyawan/anggota) | — | 👤 daftar mandiri |
| Nilai tugas | — | ✅ (override) | — | — | 📚 | — |
| Presensi | ✅ | ✅ | — | 🏢 lihat | 📚 kelola | 👤 check-in |
| Live class | ✅ | ✅ | — | — | 📚 | 👤 gabung |
| Setujui sertifikat | ✅ | ✅ (SoD) | — | — | — | — |
| Cabut sertifikat | ✅ (⚠️) | ✅ (⚠️) | — | — | — | — |
| Unduh sertifikat | ✅ | ✅ | — | 🏢 | — | 👤 |
| Template sertifikat | ✅ | ✅ | — | — | — | — |
| Transaksi & rekonsiliasi | ✅ | lihat | ✅ | 🏢 lihat (invoice korporat) | — | 👤 riwayat & invoice |
| Refund | ✅ (⚠️) | — | ✅ (⚠️ > Rp1 jt) | — | — | 👤 ajukan |
| Kupon | ✅ | — | ✅ | — | — | 👤 pakai |
| Moderasi diskusi | ✅ | ✅ | — | — | 📚 | 👤 lapor |
| Gamifikasi: lihat leaderboard / kelola aturan | ✅ kelola | ✅ kelola | — | 🏢 lihat, aktif/nonaktif untuk organisasinya | 📚 lihat | 👤 lihat (kelas/organisasi sendiri) |
| Access review anggota (`organization.access_review`) | ✅ | ✅ | — | 🏢 | — | — |
| Laporan | ✅ platform | ✅ platform | ✅ keuangan | 🏢 | 📚 | 👤 progres sendiri |
| Ekspor data (CSV/XLSX) | ✅ | ✅ | ✅ | 🏢 | 📚 | 👤 ekspor data pribadi |
| CMS beranda | ✅ | ✅ | — | — | — | — |
| Permintaan privasi | ✅ proses | ✅ proses | — | — | — | 👤 ajukan |
| API key & integrasi | ✅ (⚠️ buat key) | — | — | — | — | — |
| Pengaturan sistem & keamanan | ✅ | — | — | — | — | — |
| Lihat audit log | ✅ | ✅ (akademik) | ✅ (keuangan) | 🏢 (aksi di organisasinya) | — | — |

**Admin Layanan (`support_admin`, opsional)** tidak ditampilkan sebagai kolom: hanya `user.view_any`, `user.view` (data tersamar kecuali ada izin pengguna di tiket), `enrollment.view`, `payment.view`, dan `user.reset_mfa` dengan verifikasi identitas + persetujuan kedua (SEC-AUTH-26). Tidak memiliki akses tulis lain.

## 6. Multi-Tenant

### 6.1 Definisi

- **Tenant = Organisasi.** Setiap peserta memiliki `primary_organization_id`; enrollment,
  submission, attempt, presensi, sertifikat, transaksi membawa `organization_id` (didenormalisasi
  saat dibuat, tidak dapat diubah) untuk penegakan scope dan RLS.
- **Data global** (tidak ber-tenant): program, template sertifikat, lencana, konten CMS,
  pengaturan sistem.
- **Kelas lintas organisasi** diperbolehkan (peserta dari beberapa organisasi dalam satu batch).
  Admin Organisasi hanya melihat **baris peserta dari organisasinya** dalam kelas tersebut;
  trainer melihat semua peserta kelas yang diampu.

### 6.2 Penegakan

| Lapis | Mekanisme |
|---|---|
| Aplikasi | Middleware `ResolveTenantScope` menghitung `org_scope(user)`; *global scope* Eloquent `OrganizationScope` pada model ber-tenant; Policy memverifikasi ulang. |
| Basis data | PostgreSQL RLS: `USING (organization_id = ANY (current_setting('app.org_ids')::uuid[]) OR current_setting('app.is_platform_staff') = 'on')`. Variabel disetel per transaksi via `SET LOCAL`. |
| Worker/antrian | Job membawa `actor_id` & `org_scope` eksplisit; tidak mewarisi konteks request secara implisit. |
| Laporan & ekspor | Query laporan wajib melalui repositori laporan yang menerapkan scope; diuji dengan uji kebocoran lintas tenant. |
| Cache | Kunci cache wajib memuat `organization_id`/`user_id` (mis. `org:{id}:report:...`). |
| Berkas | Path object storage: `org/{organization_id}/...`; akses hanya lewat URL bertanda tangan berumur pendek yang diterbitkan setelah policy lolos. |

### 6.3 Uji wajib

- Untuk **setiap** endpoint yang menerima ID objek: uji bahwa pengguna organisasi A menerima
  **404** saat mengakses objek organisasi B (lihat `10-strategi-pengujian.md`).
- Uji RLS langsung di level SQL memakai peran DB aplikasi.

## 7. Siklus Hidup Akun

| Kejadian | Tindakan sistem |
|---|---|
| Registrasi mandiri peserta | Status `pending_verification` → aktif setelah OTP email/HP terverifikasi. Organisasi dipilih memerlukan verifikasi domain email atau persetujuan Admin Organisasi (mencegah orang mengaku anggota organisasi). |
| Dibuat oleh admin / bulk import | Email undangan dengan tautan set kata sandi (token sekali pakai, 72 jam). |
| Pindah organisasi | Hanya oleh admin; riwayat enrollment tetap di organisasi lama (data historis tidak berpindah). |
| Nonaktif (deactivate) | Semua sesi & token dicabut seketika; login ditolak; data tetap untuk kebutuhan sertifikat. |
| Trainer/admin keluar dari organisasi | Offboarding checklist: cabut peran, cabut sesi, alihkan kepemilikan kelas, tinjau API key. |
| Tidak aktif 180 hari (peran admin/trainer) | Otomatis dinonaktifkan; aktivasi ulang oleh Super Admin. |
| Tidak aktif 90 hari (admin) | Pengingat peninjauan akses. |
| Permintaan hapus akun (UU PDP) | Lihat [`keamanan/12-privasi-data-dan-kepatuhan-uu-pdp.md`](keamanan/12-privasi-data-dan-kepatuhan-uu-pdp.md). |

## 8. Tinjauan Akses Berkala

- **Bulanan:** daftar akun `super_admin`, `academic_admin`, `finance_admin` ditinjau Security Lead.
- **Triwulanan:** Admin Organisasi meninjau daftar anggota & peran di organisasinya (fitur
  "Access Review" di dashboard admin organisasi).
- Hasil tinjauan dicatat di audit log.
