# 14 — Secure SDLC & Keamanan Rantai Pasok

> Status: **Disetujui untuk development** · Versi 1.0 · Pemilik: Security Lead + Tech Lead
>
> Acuan: OWASP SAMM, NIST SSDF (SP 800-218), SLSA, OpenSSF Scorecard. Detail standar kode di
> [`../09-standar-pengembangan.md`](../09-standar-pengembangan.md), strategi uji di
> [`../10-strategi-pengujian.md`](../10-strategi-pengujian.md), pipeline di
> [`../11-devops-dan-deployment.md`](../11-devops-dan-deployment.md).

## 1. Aktivitas Keamanan per Tahap

| Tahap | Aktivitas wajib | Keluaran |
|---|---|---|
| **Perencanaan** | Klasifikasi fitur: apakah menyentuh auth, izin, data K3/K4, pembayaran, sertifikat, berkas, integrasi eksternal? → label `security-impact` | Label & kebutuhan SEC di tiket |
| **Desain** | Threat modeling ringan (STRIDE) untuk fitur berlabel; review desain oleh Security Champion; update [01](01-model-ancaman.md) | Catatan threat model, ADR bila perlu |
| **Implementasi** | Standar secure coding (09), pre-commit (Pint, gitleaks), pustaka keamanan standar | Kode + uji keamanan unit/feature |
| **Verifikasi** | CI: SAST, SCA, secret scan, uji otorisasi & tenant, arch test, uji keamanan; code review dengan checklist ([17](17-checklist-keamanan.md)); 2 reviewer untuk jalur sensitif | PR hijau |
| **Rilis** | DAST di staging, image scan & signing, checklist rilis, persetujuan rilis | Artefak bertanda tangan, catatan rilis |
| **Operasi** | Monitoring & deteksi (11), patch (13), respons insiden (15), pentest berkala | Laporan, perbaikan |

## 2. Gerbang Otomatis di CI (Security Gates)

| ID | Gerbang | Alat (acuan) | Kriteria gagal |
|---|---|---|---|
| SEC-SDLC-01 | Secret scanning | gitleaks (pre-commit + CI), GitHub secret scanning + push protection | Rahasia apa pun terdeteksi |
| SEC-SDLC-02 | SAST | Semgrep (ruleset PHP/Laravel + aturan kustom STU), Larastan level ≥ 8 dengan aturan terlarang (`{!!`, `*Raw` interpolasi, `unserialize`, `exec`, `$request->all()` ke model, `withoutGlobalScope` tanpa anotasi, `Gate::before` bypass) | Temuan Error/High baru |
| SEC-SDLC-03 | SCA dependensi | `composer audit`, `npm audit --omit=dev`, Dependabot/Renovate, OSV-Scanner | CVE High/Critical dengan perbaikan tersedia; paket *abandoned* |
| SEC-SDLC-04 | Lisensi | `composer licenses` / license checker | Lisensi di luar allowlist tanpa persetujuan |
| SEC-SDLC-05 | Uji keamanan | `tests/Security`, matriks otorisasi, RLS, lintas tenant, regresi PROTO-xx | Gagal apa pun |
| SEC-SDLC-06 | IaC scan | Checkov/tfsec/Trivy config | Misconfig High |
| SEC-SDLC-07 | Container scan & SBOM | Trivy + Syft; cosign sign | CVE Critical/High dengan fix; image tanpa tanda tangan |
| SEC-SDLC-08 | DAST | OWASP ZAP baseline tiap deploy staging; full authenticated scan mingguan | Alert High |
| SEC-SDLC-09 | Workflow CI | actionlint + zizmor (keamanan GitHub Actions) | Temuan High (mis. injeksi ekspresi, `pull_request_target`) |

Pengecualian temuan (false positive/risk accepted) dicatat di berkas baseline yang **hanya boleh
berkurang** dan setiap entri memuat alasan + penyetuju + kedaluwarsa.

## 3. Code Review

| ID | Kebutuhan |
|---|---|
| SEC-SDLC-10 | Semua perubahan ke `main` melalui PR; minimal 1 reviewer; **2 reviewer** (salah satunya Security Champion/Tech Lead) untuk path sensitif di `CODEOWNERS`: `app/Modules/{Identity,Access,Payment,Certification,Audit,Privacy,Integration}`, `app/Support/Security`, `config/`, `database/migrations` (RLS/izin), `routes/`, `docker/`, `.github/`, `composer.json/lock`, `package.json/lock`. |
| SEC-SDLC-11 | Reviewer memakai checklist keamanan PR ([17](17-checklist-keamanan.md) §1). Penulis PR mengisi bagian keamanan di template PR (dampak keamanan, izin baru, data baru, endpoint baru, threat model diperbarui?). |
| SEC-SDLC-12 | Branch protection: status check wajib hijau, tanpa force push, tanpa merge oleh penulis sendiri tanpa approval, *signed commits* wajib di `main`, *linear history*; admin repo tidak dapat mem-*bypass* (termasuk owner) kecuali prosedur darurat tercatat. |

## 4. Manajemen Dependensi & Rantai Pasok

| ID | Kebutuhan |
|---|---|
| SEC-SDLC-13 | Lockfile (`composer.lock`, `package-lock.json`) dikomit; instalasi di CI/Docker memakai mode *frozen* (`composer install --no-dev --prefer-dist`, `npm ci`); `npm` dengan `--ignore-scripts` bila memungkinkan; registry resmi saja (Packagist, npm) — tidak ada paket dari URL git arbitrer. |
| SEC-SDLC-14 | **Penambahan dependensi baru** memerlukan justifikasi di PR: fungsi, alternatif, popularitas & pemeliharaan (rilis ≤ 12 bulan, maintainer aktif), riwayat keamanan, lisensi, ukuran; pemeriksaan *typosquatting* nama paket. Utamakan fitur bawaan Laravel. |
| SEC-SDLC-15 | Pembaruan dependensi otomatis (Renovate/Dependabot) mingguan; pembaruan keamanan segera; *minor/patch* digabung setelah CI hijau; *major* dijadwalkan. Pembaruan paket baru diberi jeda ≥ 3 hari sejak rilis (kecuali patch keamanan) untuk mengurangi risiko paket terkompromi. |
| SEC-SDLC-16 | GitHub Actions: action pihak ketiga **dipin ke commit SHA**; `permissions:` minimal per job (default `contents: read`); tidak ada `pull_request_target` dengan checkout kode PR; rahasia tidak tersedia untuk PR dari fork; environment `production` dengan reviewer wajib; OIDC ke cloud. |
| SEC-SDLC-17 | **Provenance build** (SLSA L2 → L3): image dibangun hanya oleh CI, ditandatangani (cosign keyless/KMS), attestasi provenance & SBOM dilampirkan; deploy memverifikasi tanda tangan & provenance. |
| SEC-SDLC-18 | Aset frontend di-*bundle* sendiri; tidak ada skrip runtime dari CDN pihak ketiga (PROTO-20). Bila terpaksa (mis. Snap Midtrans), hanya di halaman yang membutuhkan dan dicantumkan di CSP halaman itu. |

## 5. Pengujian Penetrasi & Program Kerentanan

| ID | Kebutuhan |
|---|---|
| SEC-SDLC-19 | **Pentest eksternal pihak ketiga independen sebelum go-live** — lingkup: aplikasi web (semua peran), API publik & mitra, webhook, alur pembayaran (sandbox), penerbitan & verifikasi sertifikat, isolasi tenant, konfigurasi infrastruktur/cloud; metodologi OWASP WSTG + verifikasi ASVS L2; dilakukan di staging yang identik produksi dengan akun uji tiap peran. **Retest** setelah perbaikan. Laporan disimpan rahasia. |
| SEC-SDLC-20 | Pentest ulang **tahunan** dan setelah perubahan besar (modul baru berisiko tinggi, perubahan arsitektur auth/tenant/pembayaran). |
| SEC-SDLC-21 | **Vulnerability Disclosure Policy** publik ([`/SECURITY.md`](../../SECURITY.md) + `security.txt`) sejak go-live; program *bug bounty* dipertimbangkan setelah 6 bulan stabil. |
| SEC-SDLC-22 | **SLA perbaikan temuan** (sejak dikonfirmasi): Critical ≤ 72 jam (mitigasi sementara ≤ 24 jam), High ≤ 7 hari, Medium ≤ 30 hari, Low ≤ 90 hari. **Gerbang rilis**: tidak ada temuan Critical/High terbuka; Medium terbuka memerlukan penerimaan risiko tertulis. |

## 6. Pelatihan & Budaya

| ID | Kebutuhan |
|---|---|
| SEC-SDLC-23 | Onboarding developer: pelatihan secure coding Laravel (OWASP Top 10, dokumen folder `keamanan/`), sesi walkthrough temuan purwarupa ([16](16-temuan-keamanan-purwarupa.md)). Penyegaran tahunan + sesi *lessons learned* setelah insiden/pentest. |
| SEC-SDLC-24 | Security Champion per tim dengan alokasi waktu ≥ 10%; rapat keamanan dua mingguan (review temuan, dependensi, alert). |
| SEC-SDLC-25 | Metrik program keamanan dilaporkan bulanan: jumlah & umur temuan per severity, % PR berlabel yang melewati threat modeling, cakupan uji keamanan, waktu patch rata-rata, hasil DAST. |

## 7. Penggunaan Alat AI dalam Pengembangan

| ID | Kebutuhan |
|---|---|
| SEC-SDLC-26 | Kode yang dihasilkan/dibantu alat AI diperlakukan seperti kode developer lain: wajib review manusia, uji, dan lolos semua gerbang. Tidak memasukkan rahasia, data pribadi produksi, atau laporan pentest ke alat AI yang tidak disetujui organisasi. Dependensi yang disarankan AI diverifikasi keberadaan & reputasinya (cegah *package hallucination/slopsquatting*). |
