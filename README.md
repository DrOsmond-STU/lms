# STU LMS

Learning Management System untuk pelatihan & sertifikasi **Internasional** dan **BNSP** —
dibangun dengan **Laravel 13 · Livewire 4 · PostgreSQL 16 · Redis 7**, dengan keamanan sebagai
prioritas utama.

> 📚 Seluruh spesifikasi (kebutuhan, arsitektur, database, API, RBAC, keamanan, pengujian, DevOps,
> roadmap) ada di [`docs/`](docs/README.md). Kebijakan pelaporan kerentanan: [`SECURITY.md`](SECURITY.md).
> Purwarupa UI statis (acuan desain) ada di [`prototype/`](prototype/README.md) dan **tidak di-deploy**.

## Status

**Fase 0 — Fondasi** sudah berjalan: autentikasi + MFA, RBAC, isolasi tenant dengan Row-Level
Security, jejak audit berantai hash, header keamanan/CSP, shell UI, uji keamanan otomatis, dan CI.
Rincian: [`docs/README.md` → Status Implementasi](docs/README.md#status-implementasi--fase-0-fondasi).

## Menjalankan Secara Lokal

### Prasyarat
PHP 8.4 (ekstensi `pdo_pgsql`, `redis`, `intl`, `sodium`), Composer 2, Node 22, PostgreSQL 16, Redis 7
— atau cukup Docker.

### Opsi A — Tanpa Docker

```bash
# 1. Peran & basis data (sekali saja; dijalankan sebagai superuser PostgreSQL)
sudo -u postgres psql -f docker/postgres/init-roles.sql

# 2. Dependensi & konfigurasi
composer install
npm ci --ignore-scripts && npm run build
cp .env.example .env
php artisan key:generate
php artisan stu:pepper

# 3. Skema (sebagai pemilik skema) & data awal (sebagai peran aplikasi)
php artisan migrate --database=pgsql_migrator
php artisan db:seed          # peran/izin + akun demo (HANYA di APP_ENV=local)

# 4. Jalankan
php artisan serve            # http://localhost:8000
```

### Opsi B — Docker Compose

```bash
cp .env.example .env && php artisan key:generate && php artisan stu:pepper
docker compose up -d --build
docker compose exec app php artisan migrate --database=pgsql_migrator
docker compose exec app php artisan db:seed
# Aplikasi: http://localhost:8080 · Mailpit: http://localhost:8025
```

### Akun demo (hanya lokal)

Kata sandi semua akun: `Demo-Lokal-2026!` (lihat `database/seeders/LocalDemoSeeder.php`).
Akun selain peserta **wajib mengaktifkan MFA** (aplikasi autentikator) saat login pertama.

| Email | Peran |
|---|---|
| `superadmin@stu-lms.test` | Super Admin |
| `akademik@stu-lms.test` | Admin Akademik |
| `keuangan@stu-lms.test` | Admin Keuangan |
| `hr@stu-lms.test` | Admin Organisasi (korporat) |
| `trainer@stu-lms.test` | Trainer |
| `peserta@stu-lms.test` | Peserta |

Produksi **tidak** memiliki akun demo — Super Admin pertama dibuat dengan
`php artisan stu:bootstrap-admin --email=... --name=...` (tautan atur kata sandi dikirim via email).

## Pengujian & Pemeriksaan Kualitas

```bash
vendor/bin/pest                                      # unit, feature, security, arch (PostgreSQL nyata sebagai stu_app)
vendor/bin/pint --test                               # gaya kode
vendor/bin/phpstan analyse --memory-limit=1G         # Larastan level 8
composer audit && npm audit                          # kerentanan dependensi
php artisan stu:audit-verify                         # verifikasi rantai hash jejak audit
```

Uji membuat skema di basis data `stu_lms_test` sebagai `stu_migrator`, lalu berjalan sebagai
`stu_app` sehingga Row-Level Security benar-benar teruji.

## Struktur

```
app/Modules/          Modul domain (Identity, Access, Organization, Audit, … menyusul per fase)
app/Support/          Security (CSP, TOTP, hashing token, guard produksi), Tenancy (konteks RLS)
config/security.php   Baseline keamanan (MFA, sesi, rate limit, kata sandi)
config/navigation.php Menu per area (dari purwarupa)
database/migrations/  Skema + kebijakan RLS + trigger append-only
docker/               Konfigurasi PostgreSQL (peran), PHP, Nginx
docs/                 Spesifikasi lengkap
prototype/            Purwarupa UI statis (referensi)
tests/                Unit, Security, Arch
```

## Kontribusi

Lihat [`CONTRIBUTING.md`](CONTRIBUTING.md) dan [`docs/09-standar-pengembangan.md`](docs/09-standar-pengembangan.md).
Setiap PR wajib lulus CI dan checklist keamanan.
