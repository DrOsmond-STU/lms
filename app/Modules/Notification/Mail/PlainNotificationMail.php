<?php

declare(strict_types=1);

namespace App\Modules\Notification\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email notifikasi umum: judul, isi singkat, tautan ke aplikasi (dibangun dari APP_URL).
 */
final class PlainNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $headline,
        public readonly string $message,
        public readonly ?string $actionPath,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->headline.' — '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.notification', with: [
            'headline' => $this->headline,
            'message' => $this->message,
            'url' => $this->actionPath === null ? null : rtrim((string) config('app.url'), '/').$this->actionPath,
        ]);
    }
}
