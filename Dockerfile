# syntax=docker/dockerfile:1.7
# STU LMS — image aplikasi (docs/11 §3, keamanan/13 SEC-INFRA-16..18).
# Produksi: pin base image ke digest (@sha256:...) melalui Renovate/Dependabot.

ARG PHP_VERSION=8.4
ARG NODE_VERSION=22

# ---- Tahap 1: dependensi PHP (tanpa dev) -----------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# ---- Tahap 2: aset frontend (Vite) -----------------------------------------
FROM node:${NODE_VERSION}-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY app ./app
RUN npm run build

# ---- Tahap 3: runtime PHP-FPM ----------------------------------------------
FROM php:${PHP_VERSION}-fpm-alpine AS runtime

RUN apk add --no-cache libpq icu-libs libzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev icu-dev libzip-dev linux-headers \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl zip opcache pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear /var/cache/apk/*

COPY docker/php/php-production.ini /usr/local/etc/php/conf.d/zz-stu.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf

WORKDIR /var/www/html
COPY --chown=root:root . .
COPY --from=vendor --chown=root:root /app/vendor ./vendor
COPY --from=assets --chown=root:root /app/public/build ./public/build

# Manifest paket dibuat saat build (root FS read-only saat runtime).
RUN APP_ENV=build php artisan package:discover --ansi

# Kode read-only milik root; hanya storage & cache yang dapat ditulis oleh user aplikasi.
RUN rm -rf prototype docs tests node_modules .git .github \
    && addgroup -S -g 10001 app && adduser -S -u 10001 -G app app \
    && chown -R app:app storage bootstrap/cache \
    && chmod -R u=rwX,go= storage bootstrap/cache

USER 10001:10001
EXPOSE 9000
HEALTHCHECK --interval=30s --timeout=5s CMD php-fpm -t || exit 1
CMD ["php-fpm", "--nodaemonize"]
