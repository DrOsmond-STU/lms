<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pemberitahuan keamanan: aplikasi autentikator (TOTP) dan kode pemulihan diganti. Tidak dapat dimatikan.
 */
final class MfaChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $base = rtrim((string) config('app.url'), '/');

        return (new MailMessage)
            ->subject('Autentikasi Dua Faktor Anda Diganti — '.config('app.name'))
            ->greeting('Halo,')
            ->line('Aplikasi autentikator dan kode pemulihan akun Anda baru saja diganti, dan semua sesi di perangkat lain telah dikeluarkan.')
            ->line('Jika bukan Anda yang melakukannya, segera atur ulang kata sandi dan hubungi tim dukungan agar MFA direset.')
            ->action('Atur Ulang Kata Sandi', $base.'/lupa-kata-sandi');
    }
}
