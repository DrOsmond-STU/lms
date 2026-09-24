<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pemberitahuan keamanan: kata sandi diubah (FR-AUTH-010). Tidak dapat dimatikan.
 */
final class PasswordChangedNotification extends Notification implements ShouldQueue
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
            ->subject('Kata Sandi Anda Telah Diubah — '.config('app.name'))
            ->greeting('Halo,')
            ->line('Kata sandi akun Anda baru saja diubah, dan semua sesi di perangkat lain telah dikeluarkan.')
            ->line('Jika bukan Anda yang melakukannya, segera atur ulang kata sandi dan hubungi tim dukungan.')
            ->action('Atur Ulang Kata Sandi', $base.'/lupa-kata-sandi');
    }
}
