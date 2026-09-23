# 03 — Otorisasi & Isolasi Tenant

> Status: **Disetujui untuk development** · Versi 1.0 · Pemilik: Security Lead
>
> Model peran & matriks izin ada di [`../07-rbac-dan-multi-tenant.md`](../07-rbac-dan-multi-tenant.md).
> Dokumen ini berisi **kontrol teknis wajib** untuk menegakkannya. Acuan: ASVS v5.0 V8
> (Authorization), OWASP API Security Top 10 (API1 BOLA, API3 BOPLA, API5 BFLA).

## 1. Penegakan di Tingkat Rute & Fungsi

| ID | Kebutuhan |
|---|---|
| SEC-AUTHZ-01 | **Deny by default**: setiap grup rute memiliki middleware `auth` + peran; rute tanpa middleware otorisasi harus dideklarasikan di allowlist `routes/public.php` dan ditinjau Security Champion. *Arch test* memastikan setiap controller/Livewire action memanggil `authorize()`/Policy (atau diberi atribut `#[PublicAction]` yang tercatat). |
| SEC-AUTHZ-02 | **Policy per model** (Laravel Policy) untuk setiap model ber-tenant atau milik pengguna; pemeriksaan dilakukan di controller/Livewire **dan** di service/action untuk operasi yang dapat dipanggil dari beberapa jalur (API, job, konsol). |
| SEC-AUTHZ-03 | Objek di luar scope dikembalikan sebagai **404** (bukan 403) untuk mencegah *existence oracle*. 403 hanya untuk objek dalam scope tetapi aksi tidak diizinkan (mis. peserta melihat kelasnya tetapi tidak boleh mengedit). |
| SEC-AUTHZ-04 | Pemeriksaan izin memakai **kode izin** (`certificate.approve`), bukan nama peran, di kode bisnis. Peta peran→izin di DB (di-*seed*), di-cache per pengguna dengan invalidasi saat peran berubah (`SEC-AUTHZ-18`). |
| SEC-AUTHZ-19 | `Gate::before()` **tidak boleh** mengembalikan `true` untuk `super_admin` secara global — aturan SoD & maker–checker tetap berlaku bagi Super Admin. Hak Super Admin didefinisikan eksplisit per izin. |

## 2. Otorisasi Tingkat Objek (Anti-IDOR/BOLA)

| ID | Kebutuhan |
|---|---|
| SEC-AUTHZ-05 | **Route model binding ber-scope**: model diambil melalui *query* yang sudah dibatasi scope pengguna (mis. `auth()->user()->enrollments()->findOrFail($id)` atau binding kustom yang menerapkan `OrganizationScope`), bukan `Model::find($id)` lalu dicek belakangan. Rute bersarang memakai `->scopeBindings()`. |
| SEC-AUTHZ-06 | **Kepemilikan eksplisit**: peserta hanya mengakses enrollment/attempt/submission/sertifikat/transaksi/notifikasi/permintaan privasi miliknya; trainer hanya kelas pada `class_trainers`; admin organisasi hanya baris dengan `organization_id` yang dikelolanya. |
| SEC-AUTHZ-07 | ID yang diterima dari request (body, query, header, Livewire) untuk objek relasi (mis. `course_class_id` saat enroll, `assignment_id` saat submit) **divalidasi keberadaan dalam scope** (`Rule::exists(...)->where('organization_id', ...)` atau lookup ber-scope). Aturan `exists`/`unique` default Laravel **tidak** menerapkan global scope — wajib dibatasi manual. |
| SEC-AUTHZ-08 | Uji otomatis **matriks otorisasi**: untuk setiap rute × setiap peran × (objek milik sendiri, objek tenant sama milik orang lain, objek tenant lain) → hasil yang diharapkan sesuai dokumen 07. Kegagalan uji memblokir merge. |
| SEC-AUTHZ-22 | **Otorisasi tingkat field (BOPLA)**: resource/transformer menentukan field yang boleh dilihat per peran (mis. email lengkap hanya untuk peran berizin `user.view`; skor detail attempt hanya untuk trainer kelas & pemilik; `is_correct` tidak pernah untuk peserta sebelum kebijakan review terpenuhi). Tidak ada serialisasi model mentah (`toArray()`/`toJson()` model langsung ke respons). |

## 3. Livewire & Komponen Interaktif

| ID | Kebutuhan |
|---|---|
| SEC-AUTHZ-09 | Properti publik komponen Livewire dapat dimanipulasi klien. Maka: (a) properti berisi ID/objek sensitif diberi atribut `#[Locked]`; (b) setiap *action method* memanggil ulang `authorize()` terhadap objek yang dimuat ulang dari DB dengan scope; (c) jangan menyimpan status otorisasi (mis. `$isAdmin = true`) di properti publik; (d) model yang di-*bind* ke properti tidak boleh membuka field sensitif ke klien (gunakan DTO/array terpilih). |

## 4. Isolasi Tenant

| ID | Kebutuhan |
|---|---|
| SEC-AUTHZ-10 | **Global scope** `OrganizationScope` pada semua model ber-tenant (daftar di [`../05-desain-database.md`](../05-desain-database.md) §5), aktif otomatis dari middleware `ResolveTenantScope`. Penonaktifan scope (`withoutGlobalScope`) hanya diizinkan di kelas yang di-*allowlist* (mis. laporan platform) dan ditandai `// @tenant-bypass: alasan` — dicek oleh arch test & code review. |
| SEC-AUTHZ-11 | **PostgreSQL RLS** (`ENABLE` + `FORCE`) pada tabel ber-tenant sebagai lapisan kedua. Variabel sesi (`app.user_id`, `app.org_ids`, `app.trainer_class_ids`, `app.is_platform_staff`) disetel dengan `SET LOCAL` di awal transaksi per request/job; koneksi yang tidak menyetel variabel **tidak melihat baris apa pun**. Peran DB aplikasi tidak memiliki `BYPASSRLS` dan bukan owner tabel. Koneksi *pooler* (PgBouncer) memakai mode transaksi → wajib `SET LOCAL` (bukan `SET`). |
| SEC-AUTHZ-12 | **Cache** yang berisi data ber-tenant/pribadi memakai kunci yang memuat `organization_id`/`user_id`; *response cache* halaman terautentikasi dilarang; header `Cache-Control: private, no-store` untuk halaman berisi data pribadi (mencegah cache CDN/shared proxy). |
| SEC-AUTHZ-13 | **Laporan, pencarian, ekspor, statistik, autocomplete** melewati repositori yang menerapkan scope; agregat untuk admin organisasi tidak boleh menyertakan baris organisasi lain (termasuk *leaderboard* dan "rata-rata kelas" pada kelas lintas organisasi — tampilkan agregat hanya bila ≥ 5 peserta untuk mencegah inferensi individu). |
| SEC-AUTHZ-20 | **Job antrian** menerima `actor_id` & `org_scope` eksplisit di payload, memuat model berdasarkan ID di dalam job (bukan model ter-serialisasi lengkap), dan menyetel konteks tenant/RLS sebelum query. Job sistem (tanpa aktor) memakai konteks `system` dengan scope tercatat. |
| SEC-AUTHZ-21 | **Berkas** ber-tenant disimpan di prefix `org/{organization_id}/...`; URL unduhan bertanda tangan diterbitkan hanya setelah policy lolos; nama objek storage tidak dapat ditebak (UUID). |

## 5. Pemisahan Tugas & Maker–Checker

| ID | Kebutuhan |
|---|---|
| SEC-AUTHZ-14 | Aturan SoD di [`../07-rbac-dan-multi-tenant.md`](../07-rbac-dan-multi-tenant.md) §3 ditegakkan di Policy **dan** di constraint DB bila memungkinkan (mis. `CHECK (approved_by <> user_id)`, `CHECK (decided_by <> requested_by)`). Pengecekan "trainer kelas tersebut" dilakukan terhadap `class_trainers` historis (termasuk yang sudah dilepas dari kelas). |
| SEC-AUTHZ-15 | **Maker–checker** (`approval_requests`) untuk: pencabutan sertifikat, refund > Rp1.000.000, penandaan lunas manual, pembuatan API key, penetapan/pencabutan `super_admin`, reset MFA akun admin, perubahan pengaturan keamanan yang melonggarkan (dalam batas baseline), penghapusan permanen data (hasil permintaan privasi). Permintaan kedaluwarsa 72 jam; checker melihat ringkasan dampak; checker ≠ maker (constraint DB). |
| SEC-AUTHZ-16 | **Deteksi anomali** mingguan (laporan otomatis ke Security Lead): admin dengan volume approval tinggi di luar jam kerja, approval dengan skor tepat di ambang, kelulusan tanpa aktivitas belajar wajar (mis. semua lesson "selesai" dalam < 10% durasi), penerbitan massal. |
| SEC-AUTHZ-17 | Setiap keputusan otorisasi berdampak tinggi (approve/reject/revoke/refund/role) tercatat di audit dengan aktor, alasan, dan snapshot data yang dilihat saat memutuskan. |

## 6. Pengelolaan Izin

| ID | Kebutuhan |
|---|---|
| SEC-AUTHZ-18 | Perubahan peran berlaku **seketika**: cache izin di-*flush*, sesi pengguna diregenerasi, dan (untuk pencabutan peran admin) semua sesi dicabut. |
| SEC-AUTHZ-23 | Peran & izin sistem didefinisikan di kode (seeder idempoten) dan tidak dapat diubah dari UI; UI hanya menetapkan peran ke pengguna. Penambahan izin baru melalui PR yang ditinjau. |
| SEC-AUTHZ-24 | **Tinjauan akses** berkala sesuai [`../07-rbac-dan-multi-tenant.md`](../07-rbac-dan-multi-tenant.md) §8; hasilnya tercatat. Akun dengan peran admin tanpa MFA terverifikasi tidak dapat mengakses apa pun (fail closed). |

## 7. Contoh Implementasi (Acuan)

```php
// Policy: hanya pemilik atau pihak berizin dalam scope
final class CertificatePolicy
{
    public function download(User $user, Certificate $certificate): Response
    {
        if ($certificate->user_id === $user->id) {
            return Response::allow();
        }
        if ($user->can('certificate.download')
            && app(TenantScope::class)->includes($user, $certificate->organization_id)) {
            return Response::allow();
        }
        return Response::denyAsNotFound();   // 404, bukan 403
    }

    public function approve(User $user, Enrollment $enrollment): Response
    {
        if (! $user->hasPermission('certificate.approve')) {
            return Response::denyAsNotFound();
        }
        // SoD: tidak boleh pernah menjadi trainer kelas ini, dan bukan pemilik enrollment
        if ($enrollment->user_id === $user->id
            || ClassTrainerHistory::wasTrainer($user->id, $enrollment->course_class_id)) {
            return Response::deny('Konflik kepentingan: Anda terkait dengan kelas ini.');
        }
        return $enrollment->status === EnrollmentStatus::PendingApproval
            ? Response::allow()
            : Response::deny('Status enrollment tidak valid untuk disetujui.');
    }
}
```

```php
// Livewire: ID dikunci & otorisasi ulang di setiap aksi
final class SubmissionReview extends Component
{
    #[Locked]
    public string $submissionId;

    public function approve(ReviewSubmission $action, int $score, string $feedback): void
    {
        $submission = Submission::query()->visibleTo(auth()->user())->findOrFail($this->submissionId);
        $this->authorize('review', $submission);
        $action->handle($submission, auth()->user(), $score, $feedback);
    }
}
```

## 8. Pengujian Wajib

- Uji matriks otorisasi & lintas tenant otomatis (SEC-AUTHZ-08).
- Uji RLS SQL langsung untuk setiap tabel ber-tenant (koneksi `stu_app` tanpa variabel → 0 baris; dengan org A → hanya baris org A; `INSERT` baris org B → ditolak).
- Uji manipulasi Livewire (ubah snapshot/`updates` properti terkunci → exception).
- Uji SoD & maker–checker (maker = checker → ditolak, termasuk bila dilakukan Super Admin).
- Uji pencabutan peran → sesi dicabut & akses langsung ditolak.
