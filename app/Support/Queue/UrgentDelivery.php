<?php

declare(strict_types=1);

namespace App\Support\Queue;

/**
 * Notifikasi yang dibatasi waktu (OTP, tautan atur ulang kata sandi) dikirim lewat koneksi
 * antrian "mendesak". Default sama dengan antrian biasa; di hosting tanpa worker permanen
 * (cron worker berjeda beberapa menit) diset `deferred` agar terkirim tepat setelah respons —
 * respons tetap tidak menunggu pengiriman email (waktu respons seragam, SEC-AUTH-06).
 */
trait UrgentDelivery
{
    /** @return array<string, string> */
    public function viaConnections(): array
    {
        return ['mail' => (string) config('queue.urgent_connection')];
    }
}
