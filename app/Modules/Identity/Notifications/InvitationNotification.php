<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Undangan akun buatan admin (FR-USER-003): tautan atur kata sandi sekali pakai.
 * URL dibangun dari APP_URL (SEC-AUTH-22); payload antrian terenkripsi.
 */
final class InvitationNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        #[\SensitiveParameter] private readonly string $token,
        private readonly string $roleLabel,
        private readonly int $hours,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.url'), '/').'/undangan/'.$this->token;

        return (new MailMessage)
            ->subject('Undangan Akun '.config('app.name'))
            ->greeting('Halo,')
            ->line('Anda diundang bergabung ke '.config('app.name')." sebagai {$this->roleLabel}.")
            ->action('Atur Kata Sandi', $url)
            ->line("Tautan berlaku {$this->hours} jam dan hanya dapat dipakai sekali.")
            ->line('Akun admin & trainer wajib memakai autentikasi dua faktor (aplikasi authenticator) saat masuk pertama kali.')
            ->line('Jika Anda tidak mengenal undangan ini, abaikan email ini.');
    }
}
