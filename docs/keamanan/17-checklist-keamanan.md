# 17 — Checklist Keamanan (PR, Fitur, Go-Live, Berkala)

> Status: **Disetujui untuk development** · Versi 1.0 · Pemilik: Security Lead
>
> Checklist ini adalah alat kerja harian. §1 disalin ke template PR
> (`.github/pull_request_template.md`), §3 menjadi syarat go-live yang ditandatangani.

## 1. Checklist Pull Request (Penulis & Reviewer)

**Umum**
- [ ] Tidak ada rahasia, kredensial, token, atau data pribadi nyata di kode/uji/fixture/log.
- [ ] Semua gerbang CI hijau (SAST, SCA, secret scan, uji, arch test, lisensi).
- [ ] Label `security-impact` dipasang bila menyentuh auth, izin, data K3/K4, pembayaran, sertifikat, berkas, integrasi, konfigurasi; threat model diperbarui atau dinyatakan tidak berubah.

**Autentikasi & Otorisasi**
- [ ] Rute/aksi baru memiliki middleware peran + `authorize()`/Policy (deny by default).
- [ ] Objek diambil melalui query ber-scope; objek di luar scope → 404.
- [ ] Livewire: ID sensitif `#[Locked]`, otorisasi ulang di setiap aksi.
- [ ] Uji otorisasi: pemilik, pengguna lain tenant sama, tenant lain, peran tanpa izin.
- [ ] Aturan SoD/maker–checker dipatuhi untuk aksi berdampak tinggi.
- [ ] Aksi sensitif mensyaratkan re-auth.

**Input & Output**
- [ ] Input via FormRequest + `validated()`; tidak ada `$request->all()` ke model; `$fillable` tidak memuat field sensitif.
- [ ] `exists`/ID relasi dibatasi scope tenant.
- [ ] Tidak ada `{!! !!}` (kecuali `<x-safe-html>`), `x-html`, `innerHTML`, `*Raw` dengan interpolasi, `unserialize`, `exec`.
- [ ] Teks pengguna yang masuk ke email/PDF/WA/ekspor di-escape; ekspor memakai penulis anti formula injection.
- [ ] URL dari pengguna divalidasi (allowlist) / request keluar via `SafeHttpClient`.
- [ ] Redirect hanya ke path internal.

**Data & Kriptografi**
- [ ] Kolom baru diklasifikasi (K1–K4); K4 terenkripsi/ter-hash; tabel ber-tenant punya `organization_id` + RLS + uji RLS.
- [ ] Nilai acak dari CSPRNG; perbandingan rahasia `hash_equals`.
- [ ] Data pribadi baru tercatat di RoPA & retensi ditentukan (koordinasi DPO).
- [ ] Log tidak memuat PII sensitif/rahasia; event audit/keamanan yang relevan dicatat.

**Berkas**
- [ ] Unggahan: allowlist jenis (magic bytes), ukuran, AV scan, nama acak, bucket privat, URL bertanda tangan, `Content-Disposition`.

**Dependensi & Infra**
- [ ] Dependensi baru dijustifikasi (pemeliharaan, lisensi, keamanan).
- [ ] Perubahan konfigurasi/infra melalui IaC dan ditinjau; tidak melonggarkan header/CSP/CORS/egress tanpa ADR.

## 2. Checklist Desain Fitur (Sebelum Implementasi)

- [ ] Aset & data apa yang disentuh? Kelas data?
- [ ] Siapa aktor yang boleh/tidak boleh? Matriks izin diperbarui (dok. 07)?
- [ ] Apa batas kepercayaan yang dilintasi (input klien, pihak ketiga, berkas)?
- [ ] Abuse case: bagaimana peserta curang / admin jahat / penyerang eksternal menyalahgunakannya?
- [ ] Apakah ada operasi uang/sertifikat/nilai → idempotensi, transaksi, audit, SoD?
- [ ] Apakah ada notifikasi/ekspor/API baru → minimisasi data?
- [ ] Apakah perlu DPIA (dok. [12](12-privasi-data-dan-kepatuhan-uu-pdp.md) SEC-PRIV-17)?
- [ ] Rate limit & batas sumber daya ditentukan?
- [ ] Kebutuhan SEC-* ditambahkan ke tiket & uji direncanakan.

## 3. Checklist Go-Live (Wajib 100%)

Ditandatangani: Tech Lead, Security Lead, DPO, DevOps Lead, Product Owner.

### 3.1 Tata Kelola & Kepatuhan
- [ ] DPO ditunjuk; kontak DPO dipublikasikan.
- [ ] Kebijakan Privasi & S&K final disetujui Legal, berversi, tersedia publik.
- [ ] RoPA lengkap; DPIA (platform, integritas ujian, berbagi data organisasi, verifikasi publik) disetujui DPO.
- [ ] DPA/perjanjian pengendali bersama dengan organisasi mitra awal ditandatangani.
- [ ] Pendaftaran PSE Lingkup Privat selesai.
- [ ] Tinjauan legal penggunaan merek pihak ketiga pada katalog & template sertifikat.
- [ ] `SECURITY.md` & `/.well-known/security.txt` aktif; alamat pelaporan dipantau.

### 3.2 Pengujian
- [ ] Pentest eksternal selesai & retest: **0 Critical/High terbuka**; Medium terbuka punya penerimaan risiko.
- [ ] DAST full authenticated scan staging: 0 High.
- [ ] Uji matriks otorisasi, lintas tenant, RLS: 100% lulus.
- [ ] Uji regresi PROTO-01..30 lulus.
- [ ] Uji beban (1.000 peserta ujian serentak; verifikasi 50 rps) memenuhi NFR.
- [ ] Uji restore backup & runbook DR tervalidasi (waktu tercatat).
- [ ] Tabletop insiden (minimal PB-01 & PB-03) dilaksanakan.

### 3.3 Autentikasi & Akses
- [ ] Tidak ada akun demo/default; Super Admin dibuat via bootstrap; jumlah Super Admin ≤ 3.
- [ ] Semua akun non-peserta telah mendaftarkan MFA; Super Admin memakai WebAuthn (bila Fase 3 sudah berjalan) — minimal TOTP.
- [ ] Rate limit & lockout aktif dan teruji; CAPTCHA adaptif aktif.
- [ ] Cookie sesi `__Host-`, `Secure`, `HttpOnly`, `SameSite=Lax` terverifikasi di produksi.
- [ ] Tinjauan akses awal (daftar admin & peran) terdokumentasi.

### 3.4 Aplikasi
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, boot check aktif; Telescope/Debugbar tidak terpasang.
- [ ] Header keamanan & CSP: nilai A+ (securityheaders.com / Observatory); laporan CSP diterima & dipantau.
- [ ] Path sensitif (`/.env`, `/.git`, `/vendor`, `/storage`, `/horizon` publik) → 404/terlarang.
- [ ] Halaman error kustom tanpa stack trace.
- [ ] Verifikasi sertifikat: nama tersamar via nomor, rate limit, `noindex`, tidak ada contoh nomor nyata.
- [ ] Kunci penandatangan sertifikat produksi di KMS/HSM; sertifikat penandatangan dari PSrE/CA terpercaya; PDF uji tampil "tanda tangan valid" di Adobe Reader.
- [ ] Midtrans produksi: server key di secret manager; webhook URL produksi terdaftar; uji end-to-end transaksi nyata kecil + refund.
- [ ] Pemindai malware aktif & teruji dengan EICAR.

### 3.5 Infrastruktur
- [ ] TLS A+ (SSL Labs), HSTS aktif (preload diajukan setelah stabil), CAA & DNSSEC.
- [ ] Origin hanya dapat diakses dari CDN; DB/Redis tanpa IP publik; egress allowlist aktif.
- [ ] WAF mode blok dengan rule set yang sudah di-*tuning*.
- [ ] SPF/DKIM/DMARC (min. `quarantine`, target `reject`) terverifikasi.
- [ ] Image bertanda tangan & diverifikasi saat deploy; SBOM tersimpan; 0 CVE Critical/High yang dapat diperbaiki.
- [ ] Backup PITR aktif; backup immutable di akun terpisah; enkripsi kunci terpisah.
- [ ] Log audit cloud → akun security-logs; deteksi ancaman terkelola aktif.
- [ ] Akses admin infra via SSO + MFA + JIT; tidak ada SSH publik; tidak ada kunci statis manusia.

### 3.6 Monitoring & Respons
- [ ] Aturan deteksi DET-01..16 aktif & diuji dengan simulasi.
- [ ] Rantai hash audit berjalan; verifikasi harian hijau; salinan WORM berjalan.
- [ ] On-call & eskalasi terjadwal (24/7 untuk SEV-1); kontak darurat vendor tersedia.
- [ ] Uptime monitoring eksternal & status page aktif.
- [ ] Template pemberitahuan PDP & alur persetujuan siap.

## 4. Checklist Operasional Berkala

| Frekuensi | Aktivitas |
|---|---|
| Harian | Tinjau alert High/Critical; status verifikasi rantai audit; rekonsiliasi KMS vs sertifikat; rekonsiliasi pembayaran |
| Mingguan | Laporan aktivitas admin; laporan anomali approval/nilai; hasil ZAP & pemindaian image; pembaruan dependensi |
| Bulanan | Tinjauan akun admin platform; uji restore; sampel log untuk PII; metrik program keamanan; tinjauan penerimaan risiko yang akan kedaluwarsa |
| Triwulanan | Access review Admin Organisasi; tabletop; inventaris kunci & rahasia; tinjauan WAF rules |
| Semesteran | Tinjauan RoPA; uji rotasi darurat rahasia |
| Tahunan | Pentest eksternal; DR drill; rotasi kunci terjadwal; pelatihan keamanan & PDP; audit kepatuhan PDP; review menyeluruh dokumen `keamanan/` |
