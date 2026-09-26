<?php

declare(strict_types=1);

namespace App\Modules\Notification\Jobs;

use App\Modules\Notification\Models\OutboundMessage;
use App\Modules\Notification\Models\PushSubscription;
use App\Modules\Notification\Services\WebPush;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Kirim Web Push ke semua langganan seorang pengguna; langganan yang mati (404/410) dihapus. */
final class SendPushNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 300];

    public function __construct(public readonly string $userId, public readonly string $title, public readonly string $body, public readonly ?string $path) {}

    public function handle(WebPush $push, TenantContext $tenant): void
    {
        $tenant->runAsSystem(function () use ($push): void {
            if (! WebPush::configured()) {
                return;
            }
            $url = rtrim((string) config('app.url'), '/').($this->path ?? '/notifikasi');
            PushSubscription::query()->where('user_id', $this->userId)->get()->each(function (PushSubscription $subscription) use ($push, $url): void {
                $log = OutboundMessage::log($this->userId, 'push', (string) parse_url($subscription->endpoint, PHP_URL_HOST), $this->title);
                $result = $push->send($subscription, ['title' => $this->title, 'body' => $this->body, 'url' => $url]);
                if ($result['ok']) {
                    $log->markSent();
                    $subscription->forceFill(['last_used_at' => now(), 'failures' => 0])->save();

                    return;
                }
                $log->markFailed((string) $result['error']);
                if ($result['gone'] || $subscription->failures >= 4) {
                    $subscription->delete();
                } else {
                    $subscription->forceFill(['failures' => $subscription->failures + 1])->save();
                }
            });
        });
    }
}
