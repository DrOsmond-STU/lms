<?php

declare(strict_types=1);

namespace App\Modules\Notification\Jobs;

use App\Modules\Identity\Models\User;
use App\Modules\Notification\Models\OutboundMessage;
use App\Modules\Notification\Services\WhatsAppGateway;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Kirim pesan WhatsApp ke nomor HP pengguna lewat gateway yang dikonfigurasi. */
final class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 600];

    public function __construct(public readonly string $userId, public readonly string $title, public readonly string $body, public readonly ?string $path) {}

    public function handle(WhatsAppGateway $gateway, TenantContext $tenant): void
    {
        $tenant->runAsSystem(function () use ($gateway): void {
            $user = User::query()->find($this->userId);
            $phone = $user instanceof User ? $user->getAttribute('phone_encrypted') : null;
            if (! WhatsAppGateway::configured() || ! is_string($phone) || $phone === '') {
                return;
            }
            $log = OutboundMessage::log($this->userId, 'whatsapp', WhatsAppGateway::mask($phone), $this->title);
            $message = '*'.$this->title."*\n".$this->body.($this->path !== null ? "\n".rtrim((string) config('app.url'), '/').$this->path : '');
            $result = $gateway->send($phone, $message);
            $result['ok'] ? $log->markSent() : $log->markFailed((string) $result['error']);
        });
    }
}
