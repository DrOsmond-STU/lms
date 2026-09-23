# 13 — Keamanan Infrastruktur, Jaringan & Header HTTP

> Status: **Disetujui untuk development** · Versi 1.0 · Pemilik: DevOps Lead + Security Lead
>
> Acuan: CIS Benchmarks (Linux, Docker, Kubernetes, PostgreSQL, Nginx), ASVS v5.0 V13
> (Configuration), OWASP Secure Headers Project. Topologi & runbook operasional di
> [`../11-devops-dan-deployment.md`](../11-devops-dan-deployment.md).

## 1. Akun Cloud & Identitas

| ID | Kebutuhan |
|---|---|
| SEC-INFRA-01 | Struktur akun/proyek cloud terpisah: `prod`, `staging`, `shared-services` (CI, registry), `security-logs` (log & audit WORM), `backup` (backup immutable). Guardrail organisasi (SCP/Org Policy): larang menonaktifkan logging audit cloud, larang bucket publik (kecuali allowlist), batasi region ke Jakarta, wajibkan enkripsi. |
| SEC-INFRA-02 | Akun root/owner dikunci (MFA hardware, tidak dipakai sehari-hari, alert saat dipakai). Akses manusia melalui SSO + MFA dengan peran berbatas waktu (JIT); tidak ada IAM user dengan access key jangka panjang untuk manusia. |
| SEC-INFRA-03 | Workload memakai *workload identity* (IAM role/service account) dengan least privilege per komponen (web, worker umum, worker sertifikat, worker media, backup). CI/CD memakai **OIDC federation** — tanpa kunci cloud statis di GitHub. |
| SEC-INFRA-04 | Log audit cloud (CloudTrail/Cloud Audit Logs) aktif di semua region & dikirim ke akun `security-logs` (immutable, retensi ≥ 1 tahun); deteksi ancaman terkelola (GuardDuty/Security Command Center) aktif. |

## 2. Jaringan

| ID | Kebutuhan |
|---|---|
| SEC-INFRA-05 | VPC dengan subnet privat untuk app, worker, DB, Redis; hanya load balancer di subnet publik. DB & Redis tanpa IP publik. |
| SEC-INFRA-06 | Origin hanya menerima lalu lintas dari CDN/WAF: security group/firewall allowlist rentang IP CDN + *authenticated origin pulls* (mTLS) atau tunnel (mis. Cloudflare Tunnel). Akses langsung ke IP origin → ditolak. |
| SEC-INFRA-07 | **Egress terkontrol**: app & worker keluar melalui NAT/egress proxy dengan allowlist domain (payment gateway, email, WA, IdP, KMS/Secret Manager, object storage, HIBP, CAPTCHA, registry paket hanya saat build). Kontainer pemrosesan media & PDF **tanpa egress**. |
| SEC-INFRA-08 | Security group per peran (web ↔ DB 5432, web ↔ Redis 6379, dsb.) dengan prinsip deny-all; tidak ada `0.0.0.0/0` untuk port selain 443 di LB. |
| SEC-INFRA-09 | Akses administratif ke node/cluster via **SSM Session Manager / IAP / Tailscale** dengan MFA & perekaman sesi; **tidak ada SSH terbuka** ke internet; tidak ada kunci SSH bersama. |
| SEC-INFRA-10 | Proteksi DDoS L3/L4 (penyedia cloud/CDN) & L7 (WAF rate limiting, challenge); rencana lonjakan saat ujian serentak (pre-scaling). |

## 3. Edge, WAF & Header HTTP

| ID | Kebutuhan |
|---|---|
| SEC-INFRA-11 | **WAF** dengan managed ruleset OWASP Core Rule Set (mode blok setelah masa *tuning* 2 minggu di staging), aturan bot, rate limiting per rute (login, OTP, verifikasi, API), blokir negara/ASN hanya bila ada dasar (tidak default). Aturan kustom untuk `/admin` (opsional Zero-Trust access). |
| SEC-INFRA-12 | **Header keamanan** pada semua respons HTML (middleware aplikasi untuk CSP ber-nonce; Nginx untuk sisanya): |

```
Strict-Transport-Security: max-age=63072000; includeSubDomains; preload
Content-Security-Policy: default-src 'self';
    script-src 'self' 'nonce-{NONCE}' 'strict-dynamic';
    style-src 'self' 'nonce-{NONCE}';
    img-src 'self' data: https://{cdn-media-domain};
    font-src 'self';
    connect-src 'self';
    media-src 'self' blob: https://{cdn-media-domain};
    frame-src 'none';                      # halaman checkout: https://app.midtrans.com (hanya rute itu)
    frame-ancestors 'none';
    form-action 'self';
    base-uri 'none';
    object-src 'none';
    upgrade-insecure-requests;
    report-to csp-endpoint
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin      # no-referrer pada halaman reset/verifikasi
Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()
Cross-Origin-Opener-Policy: same-origin
Cross-Origin-Resource-Policy: same-origin
X-Frame-Options: DENY                                 # kompatibilitas browser lama
Cache-Control: no-store                               # halaman terautentikasi
```

Catatan CSP: Livewire/Alpine harus dikonfigurasi agar kompatibel tanpa `unsafe-eval` (Alpine CSP
build) — bila tidak memungkinkan pada versi yang dipakai, pengecualian dibatasi & dicatat di ADR.
Target nilai **A+** di securityheaders.com dan Mozilla Observatory.

| ID | Kebutuhan |
|---|---|
| SEC-INFRA-13 | Header yang membocorkan teknologi dihapus: `Server` (disamarkan), `X-Powered-By` (`expose_php=Off`), versi framework. |
| SEC-INFRA-14 | Berkas & path sensitif tidak dapat diakses: Nginx `root` = `public/`; tolak `/.env`, `/.git`, `/*.bak`, `/storage`, `/vendor`, `composer.*`, `/phpinfo`, `/horizon` & `/telescope` (Horizon hanya via jaringan admin + izin; Telescope/Debugbar **tidak terpasang** di produksi). Uji otomatis pasca-deploy memeriksa path ini → 404. |
| SEC-INFRA-15 | `security.txt` di `/.well-known/security.txt` (kontak, kebijakan, kedaluwarsa) sesuai RFC 9116. |

## 4. Kontainer & Host

| ID | Kebutuhan |
|---|---|
| SEC-INFRA-16 | Image minimal (distroless/alpine/debian-slim) dengan digest dipin, dibangun ulang mingguan & saat CVE kritis; tanpa alat build/shell yang tidak perlu di image runtime. |
| SEC-INFRA-17 | Kontainer berjalan **non-root**, root FS read-only (direktori tulis via volume `tmpfs` spesifik), `no-new-privileges`, capabilities di-*drop* semua (tambah hanya yang perlu), seccomp/AppArmor default, batas CPU/memori, tanpa mode `privileged`, tanpa mount Docker socket. |
| SEC-INFRA-18 | Pemindaian image (Trivy) di CI: gagal build untuk CVE Critical/High yang memiliki perbaikan; SBOM (Syft) dilampirkan; image ditandatangani (cosign) dan **verifikasi tanda tangan** saat deploy (admission policy). |
| SEC-INFRA-19 | Bila Kubernetes: Pod Security Standards `restricted`, NetworkPolicy deny-all default, RBAC cluster least privilege, secret via CSI driver dari secret manager (bukan Secret base64 di manifest), audit log API server aktif, node auto-upgrade. |
| SEC-INFRA-20 | Host OS (bila VM): CIS Level 1, update keamanan otomatis, auditd, tanpa layanan tak perlu, disk terenkripsi. |

## 5. Konfigurasi PHP, Laravel & Web Server

| ID | Kebutuhan |
|---|---|
| SEC-INFRA-21 | `php.ini` produksi: `display_errors=Off`, `log_errors=On`, `expose_php=Off`, `allow_url_include=Off`, `allow_url_fopen=Off` (kecuali dibutuhkan pustaka — gunakan HTTP client), `session.use_strict_mode=1`, `disable_functions` untuk `exec, shell_exec, system, passthru, proc_open, popen, pcntl_exec` pada pool web (worker media/PDF memakai image terpisah), `open_basedir` bila layak, `upload_max_filesize`/`post_max_size` sesuai [05](05-keamanan-berkas-dan-media.md), OPcache dengan `validate_timestamps=0`. |
| SEC-INFRA-22 | Laravel: `APP_ENV=production`, `APP_DEBUG=false` (cek saat boot — gagal start bila salah), `APP_URL` https, `TrustProxies` hanya rentang CDN/LB, `TrustHosts` domain resmi, cache konfigurasi/rute/view, *maintenance mode* tanpa secret bypass publik. Paket dev (`--no-dev`) tidak terpasang. |
| SEC-INFRA-23 | PostgreSQL: TLS wajib, `password_encryption=scram-sha-256`, `log_connections`/`log_disconnections` on, `pgaudit` untuk DDL & peran, parameter `statement_timeout` untuk peran app (mis. 30 detik) & report (mis. 5 menit), ekstensi hanya yang diperlukan. |
| SEC-INFRA-24 | Redis: TLS, AUTH/ACL per pengguna (app vs horizon), `rename-command` / ACL untuk menonaktifkan `FLUSHALL`, `CONFIG`, `KEYS` bagi app; tidak terekspos publik. |

## 6. Backup & Ketahanan

| ID | Kebutuhan |
|---|---|
| SEC-INFRA-25 | Backup DB: PITR (RPO ≤ 15 menit) + snapshot harian (35 hari) + bulanan (12 bulan), dienkripsi dengan kunci berbeda dari produksi, disalin ke akun `backup` dengan **immutability/object lock** (ransomware-resistant). Kredensial produksi tidak dapat menghapus backup. |
| SEC-INFRA-26 | Uji restore bulanan ke lingkungan terisolasi (hasil & waktu tercatat); DR drill tahunan (lihat [15](15-respons-insiden-dan-kontinuitas.md)). Data hasil restore uji dihapus setelah verifikasi. |
| SEC-INFRA-27 | Object storage: versioning aktif; bucket sertifikat & audit object lock; replikasi ke lokasi kedua di Indonesia untuk sertifikat. |

## 7. Manajemen Kerentanan & Patch

| ID | Kebutuhan |
|---|---|
| SEC-INFRA-28 | Pemindaian kerentanan infrastruktur mingguan (image, host, konfigurasi cloud/CSPM); pemindaian eksternal (port/TLS) bulanan. |
| SEC-INFRA-29 | SLA patch (dihitung dari ketersediaan perbaikan): **Critical ≤ 72 jam** (≤ 24 jam bila dieksploitasi aktif & terekspos internet), **High ≤ 7 hari**, Medium ≤ 30 hari, Low ≤ 90 hari. Rilis keamanan PHP/Laravel/Livewire diterapkan sesuai SLA ini. |
| SEC-INFRA-30 | Inventaris aset (layanan, domain, sertifikat TLS, bucket, akun) dipelihara dari IaC; aset di luar IaC ("shadow IT") dideteksi & ditindaklanjuti. |

## 8. Domain, DNS & Email

| ID | Kebutuhan |
|---|---|
| SEC-INFRA-31 | Registrar dengan MFA & *registry lock*; DNSSEC; CAA record; pemantauan perubahan DNS; subdomain yang tidak dipakai dihapus (cegah *subdomain takeover*). |
| SEC-INFRA-32 | Email: SPF (`-all`), DKIM (2048-bit, rotasi tahunan), **DMARC `p=reject`** setelah masa pemantauan (`p=none` → `quarantine` → `reject`), MTA-STS & TLS-RPT, BIMI opsional; domain pengirim terpisah untuk transaksional (mis. `notif.semestateknologiutama.com`). |
| SEC-INFRA-33 | Domain berkas pengguna terpisah & tanpa cookie ([05](05-keamanan-berkas-dan-media.md) SEC-FILE-17). |

## 9. Lingkungan Non-Produksi

| ID | Kebutuhan |
|---|---|
| SEC-INFRA-34 | Staging dilindungi (SSO/Zero-Trust atau IP allowlist + basic auth), `noindex`, banner "STAGING", kredensial & kunci terpisah dari produksi (sandbox payment gateway, CA uji untuk sertifikat), data sintetis saja. |
| SEC-INFRA-35 | Preview environment per PR (bila ada) tidak memiliki akses ke rahasia produksi/staging dan dihapus otomatis saat PR ditutup. |
