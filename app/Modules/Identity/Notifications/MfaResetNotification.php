<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pemberitahuan keamanan: MFA direset oleh admin (FR-USER-006, FR-AUTH-010).
 */
final class MfaResetNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Autentikasi Dua Faktor Direset — '.config('app.name'))
            ->greeting('Halo,')
            ->line('Atas permintaan yang telah diverifikasi, administrator mereset autentikasi dua faktor akun Anda. Semua sesi telah dikeluarkan.')
            ->line('Saat masuk berikutnya, Anda akan diminta mendaftarkan aplikasi authenticator kembali.')
            ->line('Jika Anda tidak pernah meminta reset ini, segera hubungi tim dukungan.');
    }
}
