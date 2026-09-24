<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dikirim bila seseorang mendaftar memakai email yang sudah terdaftar. Halaman registrasi
 * memberi respons identik sehingga tidak menjadi oracle keberadaan akun (SEC-AUTH-06).
 */
final class AccountAlreadyExistsNotification extends Notification implements ShouldQueue
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
            ->subject('Percobaan Pendaftaran dengan Email Anda — '.config('app.name'))
            ->greeting('Halo,')
            ->line('Seseorang mencoba mendaftar akun baru memakai alamat email ini, padahal akun Anda sudah ada.')
            ->action('Masuk ke Akun', $base.'/masuk')
            ->line('Lupa kata sandi? Gunakan menu "Lupa kata sandi" di halaman masuk.')
            ->line('Jika bukan Anda yang mencoba mendaftar, abaikan email ini. Akun Anda tidak berubah.');
    }
}
