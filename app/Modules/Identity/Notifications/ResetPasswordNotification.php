<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email atur ulang kata sandi. URL dibangun dari APP_URL, bukan header Host
 * (keamanan/02 SEC-AUTH-22). Dikirim via antrian agar waktu respons seragam (SEC-AUTH-06);
 * payload antrian dienkripsi karena memuat token.
 */
final class ResetPasswordNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(CanResetPassword $notifiable): MailMessage
    {
        $base = rtrim((string) config('app.url'), '/');
        $email = $notifiable->getEmailForPasswordReset();
        $url = $base.'/reset-kata-sandi/'.rawurlencode($this->token).'?email='.rawurlencode($email);
        $minutes = (int) config('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject('Atur Ulang Kata Sandi — '.config('app.name'))
            ->greeting('Halo,')
            ->line('Kami menerima permintaan untuk mengatur ulang kata sandi akun Anda.')
            ->action('Atur Ulang Kata Sandi', $url)
            ->line("Tautan ini berlaku {$minutes} menit dan hanya dapat dipakai sekali.")
            ->line('Jika Anda tidak meminta ini, abaikan email ini — kata sandi Anda tidak berubah. Kami tidak pernah meminta kata sandi atau kode OTP Anda.');
    }
}
