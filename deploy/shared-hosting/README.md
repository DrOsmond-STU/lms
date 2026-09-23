# Deploy ke Shared Hosting cPanel (STAGING)

Lingkungan **staging/UAT** STU LMS di `https://lms.semestateknologiutama.com` (akun cPanel
Domainesia). Shared hosting **bukan** target produksi penuh — lihat
[`docs/04-arsitektur-sistem.md`](../../docs/04-arsitektur-sistem.md) ADR-004 dan
[`docs/11-devops-dan-deployment.md`](../../docs/11-devops-dan-deployment.md).

## Topologi

| Komponen | Nilai |
|---|---|
| Kode | Git deploy cPanel → `~/lms-app` (branch pengembangan) |
| Docroot subdomain | `~/lms-app/public` (kode & `.env` di luar web root) |
| PHP web & CLI | `/opt/alt/php83` (platform Composer dipatok ke 8.3) |
| Basis data | PostgreSQL 16: `semestat_lms`; peran `semestat_lmsmig` (pemilik skema, hanya migrasi) & `semestat_lmsapp` (runtime, tanpa BYPASSRLS) |
| Sesi / cache | `file` (storage/, di luar web root) |
| Antrian | `database`, diproses cron `queue:work --stop-when-empty` |
| Email | `sendmail` server (SPF/DKIM domain aktif) |
| Penjadwal | cron `schedule:run` |

## Cron

```
*/5 * * * *  flock -n $HOME/.lms-setup.lock /bin/bash $HOME/lms-setup.sh
*   * * * *  cd $HOME/lms-app && /opt/alt/php83/usr/bin/php artisan schedule:run >> /dev/null 2>&1
*/2 * * * *  cd $HOME/lms-app && /opt/alt/php83/usr/bin/php artisan queue:work database --stop-when-empty --tries=3 --max-time=100 >> /dev/null 2>&1
```

`lms-setup.sh` adalah salinan [`lms-setup.sh`](lms-setup.sh) di `$HOME` (di luar repo agar
cron tetap berjalan walau direktori aplikasi diganti).

## Memperbarui staging

1. Push ke branch yang dipasang, lalu picu *Deploy* (git pull) pada Git deployment cPanel.
2. Buat berkas `~/lms-deploy.request` → cron menjalankan ulang `lms-setup.sh`
   (composer, build aset, migrasi, cache).
3. Periksa `~/lms-setup.log`.

## Rahasia

`~/lms-app/.env` (chmod 600) berisi `APP_KEY`, `SECURITY_PEPPER`, dan kredensial DB yang
dibangkitkan acak saat pemasangan. Tidak pernah dikomit. Rotasi: lihat
[`docs/keamanan/06-kriptografi-dan-manajemen-kunci.md`](../../docs/keamanan/06-kriptografi-dan-manajemen-kunci.md).

## Perbedaan dari target produksi (diterima untuk staging)

| Kontrol | Produksi (dok. 11/13) | Staging shared hosting |
|---|---|---|
| Sesi/cache | Redis | File (di luar web root) |
| Antrian | Redis + Horizon | Database + cron |
| WAF/CDN | Cloudflare/setara | Imunify360 bawaan hosting |
| Isolasi | Kontainer non-root read-only | Akun cPanel (LVE) |
| Log/SIEM | Log JSON terpusat | `storage/logs` |
| Backup | PITR + immutable | Backup harian cPanel |
