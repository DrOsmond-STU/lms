<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications;

use App\Support\Queue\UrgentDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Kode OTP verifikasi registrasi (FR-AUTH-002). Payload antrian terenkripsi (memuat OTP).
 */
final class RegistrationCodeNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, UrgentDelivery;

    public function __construct(
        #[\SensitiveParameter] private readonly string $code,
        private readonly int $minutes,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kode Verifikasi Pendaftaran — '.config('app.name'))
            ->greeting('Halo,')
            ->line('Gunakan kode berikut untuk menyelesaikan pendaftaran akun Anda:')
            ->line('**'.$this->code.'**')
            ->line("Kode berlaku {$this->minutes} menit. Jangan berikan kode ini kepada siapa pun, termasuk yang mengaku petugas kami.")
            ->line('Jika Anda tidak mendaftar, abaikan email ini.');
    }
}
