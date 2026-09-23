# 06 — Spesifikasi API

> Status: **Disetujui untuk development** · Versi 1.0 · Pemilik: Tech Lead
>
> Kontrol keamanan API dirinci di
> [`keamanan/10-keamanan-api-dan-integrasi.md`](keamanan/10-keamanan-api-dan-integrasi.md).
> Kontrak final ditulis sebagai **OpenAPI 3.1** di `docs/api/openapi.yaml` (dibuat pada Fase 1 dan
> divalidasi di CI); dokumen ini adalah desain acuannya.

## 1. Ruang Lingkup API

| Kelompok | Prefiks | Konsumen | Autentikasi |
|---|---|---|---|
| **Verifikasi publik** | `/api/v1/certificates/verify` | Siapa pun, sistem rekrutmen | Tanpa kunci (kuota rendah) **atau** API key (kuota tinggi) |
| **API Mitra** | `/api/v1/partner/...` | HRIS korporat, sistem akademik institusi | API key ber-scope + IP allowlist |
| **Webhook masuk** | `/webhooks/{provider}` | Midtrans, penyedia WA/email (status pengiriman) | Verifikasi tanda tangan provider |
| **Webhook keluar** | URL milik mitra | Sistem mitra | HMAC-SHA256 (kita menandatangani) |
| **Internal (UI)** | Rute web + Livewire | Browser pengguna | Sesi cookie + CSRF |
| **Mobile (masa depan)** | `/api/v1/me/...` | Aplikasi resmi | OAuth2 Authorization Code + PKCE (belum rilis 1.0) |

UI web **tidak** memakai API JSON publik; UI memakai rute web/Livewire yang dilindungi sesi & CSRF
(ADR-002). Dengan demikian permukaan API publik tetap kecil.

## 2. Konvensi Umum

| Aspek | Ketentuan |
|---|---|
| Protokol | HTTPS saja (TLS 1.2+); HTTP ditolak di edge (bukan redirect untuk `/api`) |
| Format | `application/json; charset=utf-8`; request body maks. 1 MB (kecuali unggahan) |
| Penamaan | Path `kebab-case` jamak; field JSON `snake_case` |
| Waktu | ISO 8601 UTC, mis. `2026-09-23T03:15:00Z`; tanggal `YYYY-MM-DD` |
| ID | UUIDv7 string; nomor bisnis (sertifikat/invoice) terpisah |
| Versi | Di path (`/v1`). Perubahan *breaking* → `/v2`; `v1` didukung ≥ 12 bulan setelah `v2` rilis dengan header `Deprecation` & `Sunset` |
| Paginasi | Berbasis kursor: `?limit=50&cursor=...` (maks. 100); respons memuat `next_cursor` |
| Filter | `?filter[status]=passed&filter[updated_since]=2026-09-01T00:00:00Z` (field filter di-*allowlist*) |
| Sort | `?sort=-issued_at` (field di-*allowlist*) |
| Idempotensi | Semua `POST` yang membuat sumber daya menerima header `Idempotency-Key` (UUID); disimpan 24 jam; request ulang dengan kunci sama mengembalikan respons awal |
| Korelasi | Respons menyertakan `X-Request-Id`; klien boleh mengirim `X-Request-Id` sendiri (divalidasi format) |
| Kompresi | gzip/br |
| CORS | Ditolak secara default. `verify` mengizinkan `GET` dari origin mana pun **tanpa kredensial** (`Access-Control-Allow-Origin: *`, tanpa `Allow-Credentials`). API mitra: tanpa CORS (server-to-server) |
| Cache | `Cache-Control: no-store` untuk semua respons berisi data pribadi; `verify` boleh `private, max-age=60` |

### 2.1 Format Error (RFC 9457 Problem Details)

```json
HTTP/1.1 422 Unprocessable Content
Content-Type: application/problem+json

{
  "type": "https://lms.semestateknologiutama.com/errors/validation",
  "title": "Data tidak valid",
  "status": 422,
  "code": "VALIDATION_FAILED",
  "detail": "Satu atau lebih field tidak valid.",
  "errors": { "course_class_id": ["Kelas tidak ditemukan atau tidak tersedia."] },
  "request_id": "01J8Z3K6T4..."
}
```

| HTTP | `code` | Kapan |
|---|---|---|
| 400 | `BAD_REQUEST` | Sintaks JSON/parameter salah |
| 401 | `UNAUTHENTICATED` | Kunci tidak ada/tidak valid/kedaluwarsa (pesan generik) |
| 403 | `FORBIDDEN` / `SCOPE_MISSING` / `IP_NOT_ALLOWED` | Kunci valid tetapi tidak berhak |
| 404 | `NOT_FOUND` | Tidak ada **atau** di luar scope (tidak dibedakan) |
| 409 | `CONFLICT` / `IDEMPOTENCY_CONFLICT` | Konflik status / kunci idempotensi dipakai dengan body berbeda |
| 413 | `PAYLOAD_TOO_LARGE` | |
| 415 | `UNSUPPORTED_MEDIA_TYPE` | |
| 422 | `VALIDATION_FAILED` | |
| 429 | `RATE_LIMITED` | Disertai `Retry-After` |
| 500 | `INTERNAL_ERROR` | Tanpa stack trace; hanya `request_id` |
| 503 | `SERVICE_UNAVAILABLE` | Pemeliharaan/overload |

### 2.2 Header Rate Limit

`RateLimit-Limit`, `RateLimit-Remaining`, `RateLimit-Reset` (draf IETF), dan `Retry-After` pada 429.

## 3. Autentikasi API Mitra

- Header: `Authorization: Bearer stu_live_<prefix8>_<secret32>` (secret 32 byte acak, base62).
  Lingkungan uji memakai `stu_test_`.
- Server mencari berdasarkan `prefix`, lalu membandingkan `HMAC-SHA256(pepper, key)` dengan
  `key_hash` memakai perbandingan waktu-konstan.
- Kunci terikat pada satu `api_client` → satu organisasi (atau platform untuk kunci verifikasi).
- Pemeriksaan: status aktif, belum kedaluwarsa, IP sumber ∈ `ip_allowlist` (wajib untuk scope tulis),
  scope mencukupi.
- Kunci tidak pernah diterima lewat query string (akan ditolak & dicatat sebagai security event,
  karena query string tercatat di log/proxy).

### 3.1 Scope

| Scope | Izin |
|---|---|
| `certificates:verify` | Verifikasi sertifikat (kuota lebih tinggi) |
| `members:read` | Baca anggota organisasi (data minimal, email tersamar) |
| `members:read_pii` | Baca email & nomor induk lengkap anggota — hanya bila ada DPA dengan organisasi & disetujui Super Admin |
| `enrollments:read` | Baca enrollment & progres anggota organisasi |
| `enrollments:write` | Buat enrollment untuk anggota organisasi ke kelas yang tersedia bagi organisasi |
| `certificates:read` | Baca sertifikat anggota organisasi |
| `webhooks:manage` | Kelola endpoint webhook milik klien |

## 4. Endpoint

### 4.1 Verifikasi Sertifikat (Publik)

`GET /api/v1/certificates/verify/{code}`

- `{code}`: `verification_code` (12 karakter, boleh dengan tanda hubung) **atau** nomor sertifikat
  (URL-encoded, mis. `INTL%2FAWS-CCP%2FUSTU%2F2026%2F00001`).
- Validasi format ketat sebelum query (regex), panjang maks. 64.
- Rate limit tanpa kunci: **10/menit & 100/hari per IP** (+ CAPTCHA adaptif di UI web). Dengan
  kunci `certificates:verify`: 600/menit per kunci.

Respons 200 (lookup via `verification_code`):

```json
{
  "status": "valid",
  "certificate_number": "INTL/AWS-CCP/USTU/2026/00001",
  "holder_name": "Raka Prasetya",
  "program": { "name": "AWS Certified Cloud Practitioner", "category": "international", "provider": "Amazon Web Services (AWS)" },
  "issuer": "STU LMS — Semesta Teknologi Utama",
  "issued_date": "2026-02-22",
  "valid_until": "2029-02-22",
  "verification_url": "https://lms.semestateknologiutama.com/verifikasi/7KQ2M9XD4TRA"
}
```

Aturan data:
- Lookup via **nomor sertifikat saja** → `holder_name` disamarkan (`"R*** P*******"`) kecuali
  pemanggil menyertakan nama lengkap yang cocok (`?holder_name=...`, dicocokkan ternormalisasi) —
  mencegah *harvesting* nama dari nomor berurutan.
- Status `revoked` menyertakan `revoked_date` dan kategori alasan umum (`integrity_violation`,
  `data_error`, `holder_request`, `other`) — **tanpa** teks alasan internal.
- `superseded` menyertakan `superseded_by_verification_url`.
- **Tidak pernah** mengembalikan email, HP, nomor induk, skor, atau organisasi peserta korporat
  (kecuali organisasi menyetujui ditampilkan).
- `not_found` dikembalikan sebagai **404** dengan body `{ "status": "not_found" }`.

`POST /api/v1/certificates/verify-document` (Fase 3) — unggah PDF (≤ 5 MB) → cek tanda tangan PAdES
& `pdf_sha256`. Respons: `signature_valid`, `document_unmodified`, `certificate` (seperti di atas).

### 4.2 API Mitra — Anggota & Enrollment

| Metode & path | Scope | Keterangan |
|---|---|---|
| `GET /api/v1/partner/members` | `members:read` | Daftar anggota organisasi: `id`, `name`, `email` (tersamar kecuali scope `members:read_pii` disetujui), `participant_number`, `status` |
| `GET /api/v1/partner/course-classes` | `enrollments:read` | Kelas yang tersedia untuk organisasi |
| `GET /api/v1/partner/enrollments` | `enrollments:read` | Filter: `member_id`, `status`, `course_class_id`, `updated_since` |
| `GET /api/v1/partner/enrollments/{id}` | `enrollments:read` | Detail: progres, status, skor akhir, tanggal |
| `POST /api/v1/partner/enrollments` | `enrollments:write` | Body: `member_id` **atau** `{participant_number, email, name}` (undang bila belum ada), `course_class_id`. Wajib `Idempotency-Key`. Kuota & kontrak organisasi ditegakkan |
| `DELETE /api/v1/partner/enrollments/{id}` | `enrollments:write` | Batalkan bila belum mulai |
| `GET /api/v1/partner/certificates` | `certificates:read` | Sertifikat anggota organisasi (tanpa URL unduh PDF) |

Contoh `POST /api/v1/partner/enrollments`:

```http
POST /api/v1/partner/enrollments HTTP/1.1
Authorization: Bearer stu_live_AB12CD34_************************
Idempotency-Key: 5f0c2c1e-8a0e-4f7a-9d61-2f0f2b0d9a11
Content-Type: application/json

{ "member_id": "0191f7c2-7b8e-7c1a-9d2e-0a4b5c6d7e8f", "course_class_id": "0191f7c2-8a11-7e22-a3b4-c5d6e7f80912" }
```

```json
HTTP/1.1 201 Created
{
  "id": "0191f7d0-1c2d-7e3f-8a9b-0c1d2e3f4a5b",
  "member_id": "0191f7c2-7b8e-7c1a-9d2e-0a4b5c6d7e8f",
  "course_class_id": "0191f7c2-8a11-7e22-a3b4-c5d6e7f80912",
  "status": "enrolled",
  "progress_percent": 0,
  "enrolled_at": "2026-09-23T03:15:00Z"
}
```

Rate limit mitra: 120 request/menit per kunci; `POST` 30/menit; bulk melalui antrean
(maks. 500 enrollment/jam per organisasi, dapat dinaikkan kontrak).

### 4.3 Webhook Masuk — Midtrans

`POST /webhooks/midtrans`

1. Hanya menerima `application/json`, body ≤ 64 KB; IP sumber dicocokkan ke rentang IP resmi
   gateway bila tersedia (lapisan tambahan, bukan satu-satunya).
2. Hitung `SHA512(order_id + status_code + gross_amount + server_key)` → bandingkan waktu-konstan
   dengan `signature_key`. Gagal → **401**, catat `security_events.webhook_signature_invalid`.
3. Cari transaksi by `order_id`; bandingkan `gross_amount` dengan nilai di DB (harus sama persis).
4. **Konfirmasi ulang** status via `GET /v2/{order_id}/status` (server-to-server) sebelum mengubah
   status.
5. Proses idempoten dengan `payment_events.dedup_key`; transisi hanya sesuai mesin status
   (tidak ada `settled → pending`).
6. Balas `200` secepatnya; pekerjaan lanjutan (enrollment, email) via antrian.

Pemetaan status: `capture`(fraud_status=accept) / `settlement` → `settled`; `pending` → `pending`;
`deny` / `cancel` / `failure` → `failed`; `expire` → `expired`; `refund` / `partial_refund` →
`refunded` (sesuai jumlah).

### 4.4 Webhook Keluar ke Mitra (Fase 4)

Event: `enrollment.created`, `enrollment.completed`, `enrollment.failed`, `certificate.issued`,
`certificate.revoked`.

```http
POST https://hris.mitra.co.id/stu-webhook
Content-Type: application/json
STU-Webhook-Id: 0191f8a0-...
STU-Webhook-Timestamp: 1790000000
STU-Signature: v1=5d41402abc4b2a76b9719d911017c592...

{ "id": "0191f8a0-...", "type": "certificate.issued", "created_at": "2026-09-23T03:15:00Z",
  "data": { "certificate_number": "BNSP/K3U/PMB/2026/00088", "member_id": "...", "program": "Ahli K3 Umum",
            "issued_date": "2026-09-23", "valid_until": "2029-09-23" } }
```

- `STU-Signature = HMAC-SHA256(secret, "{timestamp}.{raw_body}")`, hex; mitra wajib menolak bila
  selisih timestamp > 5 menit.
- Retry: 1m, 5m, 30m, 2j, 6j, 24j (maks. 6), lalu endpoint ditandai gagal & admin dinotifikasi.
- URL tujuan harus `https`, tidak me-*resolve* ke IP privat/loopback/link-local/metadata (cek saat
  simpan **dan** saat kirim — mencegah DNS rebinding), tanpa mengikuti redirect.

### 4.5 Health & Operasional

| Path | Akses | Isi |
|---|---|---|
| `GET /up` | Publik (dipakai LB/uptime) | `200 OK` tanpa detail |
| `GET /internal/health` | Jaringan privat saja | Status DB, Redis, antrian, storage (tanpa versi/rahasia) |
| `GET /metrics` | Jaringan privat + auth | Prometheus |

## 5. Rute Web Internal (Ringkas)

Konvensi penamaan: **URL halaman** yang terlihat pengguna berbahasa Indonesia (`/masuk`, `/peserta/kelas/...`); **endpoint aksi internal** yang dipanggil form/Livewire (mis. `POST /checkout`, `POST /assessments/{id}/attempts`, `POST /admin/enrollments/{id}/approve` di [`04-arsitektur-sistem.md`](04-arsitektur-sistem.md) §8) dan seluruh **API** `/api/v1` memakai nama resource berbahasa Inggris sesuai kode. Keduanya berada di bawah middleware area yang sama.

Rute web mengikuti pola berikut (detail per halaman di dokumen 08):

| Area | Prefiks | Middleware wajib |
|---|---|---|
| Publik | `/`, `/program/{slug}`, `/verifikasi`, `/verifikasi/{code}`, `/kebijakan-privasi`, `/syarat-ketentuan`, `/developer/api` | `throttle`, header keamanan |
| Auth | `/masuk`, `/daftar`, `/lupa-kata-sandi`, `/reset-kata-sandi/{token}`, `/masuk/mfa`, `/verifikasi-email` | `guest`, `throttle:auth` |
| Peserta | `/peserta/...` | `auth`, `verified`, `active`, `role:participant`, `tenant`, `password.confirm` (aksi sensitif) |
| Trainer | `/trainer/...` | `auth`, `mfa`, `role:trainer`, `tenant` |
| Admin Organisasi | `/organisasi/...` | `auth`, `mfa`, `role:org_admin`, `tenant` |
| Admin Platform | `/admin/...` | `auth`, `mfa`, `role:super_admin\|academic_admin\|finance_admin\|support_admin`, `admin.ip_allowlist` (opsional) |
| Webhook | `/webhooks/...` | **tanpa** sesi/CSRF; verifikasi tanda tangan; `throttle:webhooks` |

Semua aksi Livewire memanggil `authorize()` ulang di server — atribut publik komponen dianggap
**tidak tepercaya** (lihat `09-standar-pengembangan.md`).

## 6. Rate Limit Ringkas

| Kunci | Batas |
|---|---|
| Login (per akun) | 5 gagal / 15 menit → penundaan progresif; 20 gagal / 24 jam → kunci sementara + email |
| Login (per IP) | 30 / menit |
| OTP kirim | 3 / jam per tujuan; 10 / jam per IP |
| OTP verifikasi | 5 percobaan per kode |
| Lupa kata sandi | 3 / jam per email, 10 / jam per IP |
| Verifikasi sertifikat web/API tanpa kunci | 10 / menit, 100 / hari per IP |
| Autosave jawaban | 60 / menit per attempt |
| Posting diskusi | 10 / 10 menit per pengguna |
| Checkout | 10 / jam per pengguna |
| Ekspor laporan | 10 / jam per pengguna |
| API mitra | 120 / menit per kunci |
| Webhook masuk | 300 / menit (global per provider) |

## 7. Kontrak & Pengujian

- `docs/api/openapi.yaml` adalah kontrak resmi; CI memvalidasi skema & menjalankan *contract test*
  dan *fuzzing* berbasis skema terhadap staging.
- Setiap endpoint baru wajib: skema request/response, contoh, daftar error, scope, rate limit,
  catatan keamanan, dan uji otorisasi lintas tenant.
