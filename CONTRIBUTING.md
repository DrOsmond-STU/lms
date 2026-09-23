# Panduan Kontribusi — STU LMS

Terima kasih telah berkontribusi. Panduan ini adalah ringkasan; aturan lengkap dan mengikat ada
di [`docs/09-standar-pengembangan.md`](docs/09-standar-pengembangan.md). Indeks seluruh
dokumentasi: [`docs/README.md`](docs/README.md).

> **Menemukan celah keamanan?** Jangan membuat issue/PR publik. Ikuti prosedur di
> [`SECURITY.md`](SECURITY.md).

## 1. Prasyarat

| Alat | Versi |
|---|---|
| Docker Engine + Docker Compose v2 (atau Docker Desktop/OrbStack) | terbaru stabil |
| Git | ≥ 2.34 (mendukung tanda tangan commit SSH) |
| PHP (opsional di host, untuk IDE) | 8.3+ (disarankan 8.4) |
| Composer (opsional di host) | 2.8+ |
| Node.js (opsional di host) | LTS aktif (22 atau 24) |
| `make`, `pre-commit` (atau CaptainHook), `gitleaks` | terbaru |

Wajib sebelum commit pertama:

1. Kunci GPG/SSH untuk **commit bertanda tangan** didaftarkan di akun GitHub, lalu
   `git config commit.gpgsign true` (untuk SSH: `git config gpg.format ssh` dan
   `git config user.signingkey ~/.ssh/id_ed25519.pub`).
2. MFA aktif di akun GitHub.

## 2. Menyiapkan lingkungan lokal

```bash
git clone git@github.com:<org>/lms.git && cd lms
make setup      # .env dari .env.example, kontainer Sail, composer/npm install,
                # key:generate, migrate --seed (data sintetis), pasang hook pre-commit
make up         # menyalakan kontainer
npm run dev     # Vite dev server
```

Layanan lokal: aplikasi `http://localhost`, Mailpit `http://localhost:8025`, konsol MinIO
`http://localhost:9001`. Detail di [§15 standar pengembangan](docs/09-standar-pengembangan.md#15-lingkungan-pengembangan-lokal).

Aturan penting:

- **Jangan pernah** meng-commit `.env` atau secret apa pun; hanya `.env.example` dengan nilai dummy.
- **Hanya data sintetis** di lokal — jangan menyalin data produksi/staging.
- Gunakan kredensial **sandbox** untuk layanan pihak ketiga (Midtrans, dsb.).

## 3. Cabang & commit

- Model **trunk-based**: buat cabang berumur pendek dari `main`, rebase sebelum merge.
- Prefiks cabang: `feat/`, `fix/`, `sec/`, `chore/`, `docs/` — contoh
  `feat/CERT-012-revoke-maker-checker`.
- Pesan commit & judul PR mengikuti **Conventional Commits**:
  `type(scope): deskripsi` — mis. `fix(payment): verify webhook signature with hash_equals`.
  Type: `feat`, `fix`, `sec`, `perf`, `refactor`, `test`, `docs`, `build`, `ci`, `chore`,
  `revert`. Scope = nama modul huruf kecil atau area (`ci`, `docker`, `deps`).
- Semua commit **bertanda tangan**. Force push ke `main` tidak diizinkan.
- Pekerjaan yang belum selesai digabung di balik *feature flag*, bukan cabang panjang.

## 4. Proses pull request

1. Pastikan check lokal lulus: `make ci` (atau `composer lint`, `composer stan`,
   `composer test`, `npm run build`).
2. Buka PR ke `main` (boleh *draft* dahulu), isi **template PR** termasuk
   **checklist keamanan**, tautkan tiket dan kode kebutuhan (`FR-*`, `NFR-*`, `SEC-*`).
3. Jaga ukuran PR ≤ ~400 baris berubah; > 800 baris harus dipecah atau dijelaskan.
4. Perbarui dokumentasi terkait **di PR yang sama** (API, skema DB, glosarium, ADR, CHANGELOG).
5. Approval yang dibutuhkan:
   - **1 approval** untuk perubahan umum;
   - **2 approval** (termasuk tim keamanan via CODEOWNERS) untuk path sensitif:
     `app/Modules/{Identity,Access,Payment,Certification,Audit}`, `app/Support/Security`,
     `app/Support/Tenancy`, `config/`, `docker/`, `.github/`, `routes/webhooks.php`.
6. Merge dengan **squash merge** setelah semua check hijau dan semua diskusi selesai.

Reviewer memakai [checklist code review](docs/09-standar-pengembangan.md#11-checklist-code-review);
item dinyatakan selesai sesuai [Definition of Done](docs/09-standar-pengembangan.md#12-definition-of-ready--definition-of-done).

## 5. Check wajib di CI

| Check | Isi |
|---|---|
| `lint` | Laravel Pint (PSR-12), Rector dry-run, lint JS/Blade, commitlint |
| `static-analysis` | PHPStan/Larastan level max (baseline tidak boleh bertambah) |
| `test` | Pest: Unit, Feature, Arch (batas modul), Security (otorisasi & lintas tenant) |
| `migrations` | Migrasi maju–mundur–maju + uji Row-Level Security |
| `frontend-build` | `npm ci && npm run build` |
| `sast` | Semgrep |
| `sca` | `composer audit`, `npm audit`, pemeriksaan lisensi |
| `secrets` | Gitleaks |
| `container-scan` | Trivy (bila `docker/` berubah) |
| `required-approvals` | 2 approval untuk path sensitif |

PR tidak dapat di-merge bila ada temuan baru tingkat **High/Critical**.

## 6. Aturan kode singkat

- `declare(strict_types=1);` di setiap berkas PHP; identifier kode berbahasa Inggris sesuai
  [glosarium](docs/00-glosarium.md); teks UI Bahasa Indonesia melalui `lang/id`.
- Ikuti struktur modul `app/Modules/*` — modul lain hanya diakses lewat `Contracts` dan `Events`.
- Controller/Livewire tipis → FormRequest → Action/Service → Model; otorisasi via Policy di
  **setiap** aksi (termasuk aksi Livewire).
- Dilarang: `$request->all()` ke model, `{!! !!}`, `x-html` dengan data pengguna, SQL
  berinterpolasi, `eval`/`unserialize`/`shell_exec`, `rand`/`mt_rand`/`uniqid` untuk token,
  skrip inline atau dari CDN pihak ketiga.
- Dependensi baru memerlukan persetujuan (lisensi allowlist: MIT, BSD, Apache-2.0, ISC).

Daftar lengkap: [`docs/09-standar-pengembangan.md` §5](docs/09-standar-pengembangan.md#5-aturan-secure-coding-laravel-wajib).

## 7. Pelaporan keamanan

Kerentanan dilaporkan secara privat sesuai [`SECURITY.md`](SECURITY.md). Perbaikan dikerjakan
di cabang `sec/` dengan judul netral sampai perbaikan ter-deploy.
