#!/bin/bash
# ==========================================================================
# STU LMS — pemasangan/pembaruan di shared hosting cPanel (STAGING).
#
# Dijalankan oleh cron (lewat flock) dan dirancang untuk DIULANG: setiap tahap
# berpenanda, sehingga pemanggilan berikutnya melanjutkan, bukan mengulang.
# Pembaruan kode dipicu dengan membuat berkas $HOME/lms-deploy.request.
#
# Tahap:
#   1. composer install (--no-dev, dapat dilanjutkan)
#   2. npm ci + build aset (Node dari mise)
#   3. migrasi sebagai peran pemilik skema (pgsql_migrator)
#   4. sinkron peran/izin, cache konfigurasi/rute/view
#   5. Super Admin pertama (sekali; tautan atur kata sandi dikirim via email)
#
# Rahasia tidak pernah ditulis di sini — semuanya di $APP/.env (chmod 600).
# ==========================================================================
set -u

HOME_DIR=/home/semestat
APP=$HOME_DIR/lms-app
PHP=/opt/alt/php83/usr/bin/php
COMPOSER=/usr/local/bin/composer
LOG=$HOME_DIR/lms-setup.log
DONE=$HOME_DIR/.lms-setup.done
REQUEST=$HOME_DIR/lms-deploy.request
export PATH="$HOME_DIR/.local/share/mise/shims:$HOME_DIR/.local/bin:$PATH"

exec >>"$LOG" 2>&1

# Sudah selesai & tidak ada permintaan pembaruan → diam.
if [ -f "$DONE" ] && [ ! -f "$REQUEST" ]; then exit 0; fi
[ -f "$REQUEST" ] && { rm -f "$REQUEST" "$DONE" "$APP/.lms-assets.done"; echo "--- permintaan pembaruan diterima ---"; }

echo "===== PERCOBAAN $(date '+%F %T %Z') ====="
cd "$APP" || { echo "GAGAL: $APP tidak ada"; exit 1; }
[ -f .env ] || { echo "GAGAL: .env belum ada"; exit 1; }
chmod 600 .env
git log -1 --oneline 2>/dev/null

# --- 1. Dependensi PHP ------------------------------------------------------
export COMPOSER_PROCESS_TIMEOUT=900 COMPOSER_NO_INTERACTION=1
$PHP -d memory_limit=-1 "$COMPOSER" install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --classmap-authoritative
if [ ! -f vendor/autoload.php ]; then echo "dependensi belum tuntas — dilanjutkan cron berikutnya"; exit 0; fi

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private bootstrap/cache
chmod -R u=rwX,g=rX,o= storage bootstrap/cache
$PHP artisan package:discover --ansi || { echo "GAGAL: package:discover"; exit 1; }

# --- 2. Aset frontend -------------------------------------------------------
if [ ! -f .lms-assets.done ] || [ ! -f public/build/manifest.json ]; then
  echo "--- node $(node -v 2>/dev/null) / npm $(npm -v 2>/dev/null) ---"
  if npm ci --ignore-scripts --no-audit --no-fund && npm run build; then
    touch .lms-assets.done
    rm -rf node_modules
  else
    echo "aset belum berhasil dibangun — dilanjutkan cron berikutnya"; exit 0
  fi
fi

# --- 3. Skema ----------------------------------------------------------------
$PHP artisan config:clear
$PHP artisan migrate --database=pgsql_migrator --force || { echo "GAGAL: migrate"; exit 1; }

# --- 4. Peran/izin, data awal & cache produksi -----------------------------
$PHP artisan stu:access-sync
$PHP artisan stu:certificate-defaults
$PHP artisan stu:signing-key            # sertifikat penandatangan UJI (staging); produksi memakai PSrE/KMS
$PHP artisan stu:demo-content           # konten contoh sintetis untuk UAT (ditolak di produksi)
$PHP artisan config:cache && $PHP artisan route:cache && $PHP artisan view:cache && $PHP artisan event:cache

# --- 5. Super Admin pertama (sekali) ----------------------------------------
if [ ! -f "$HOME_DIR/.lms-admin.done" ] && [ -f "$HOME_DIR/.lms-admin-email" ]; then
  EMAIL="$(tr -d '[:space:]' < "$HOME_DIR/.lms-admin-email")"
  if $PHP artisan stu:bootstrap-admin --email="$EMAIL" --name="Super Admin STU"; then
    touch "$HOME_DIR/.lms-admin.done"
  fi
fi

echo "--- verifikasi ---"
$PHP -m | grep -iE '^(fileinfo|openssl|gd|pdo_pgsql|mbstring)$' | tr '\n' ' '; echo
$PHP artisan about --only=environment 2>&1 | head -20
$PHP artisan migrate:status --database=pgsql_migrator 2>&1 | tail -8
$PHP artisan stu:audit-verify

echo "===== SELESAI $(date '+%F %T %Z') ====="
touch "$DONE"
