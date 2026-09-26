<?php

declare(strict_types=1);

namespace App\Modules\Notification\Http\Controllers;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Models\NotificationPreference;
use App\Modules\Notification\Models\PushSubscription;
use App\Modules\Notification\Services\Notifier;
use App\Modules\Notification\Services\WebPush;
use App\Modules\Notification\Services\WhatsAppGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Preferensi kanal notifikasi & langganan push milik pengguna sendiri. */
final class NotificationPreferenceController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function edit(Request $request): View
    {
        $user = $this->user($request);

        return view('notifications.preferences', [
            'preference' => NotificationPreference::for($user),
            'categories' => Notifier::CATEGORIES,
            'pushConfigured' => WebPush::configured(),
            'vapidPublic' => WebPush::publicKey(),
            'subscriptions' => PushSubscription::query()->where('user_id', $user->id)->orderByDesc('created_at')->get(),
            'whatsappConfigured' => WhatsAppGateway::configured(),
            'hasPhone' => is_string($user->getAttribute('phone_encrypted')),
            'workspace' => $user->defaultWorkspace() ?? 'participant',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate([
            'email_enabled' => ['nullable', 'boolean'], 'push_enabled' => ['nullable', 'boolean'], 'whatsapp_enabled' => ['nullable', 'boolean'],
            'muted' => ['nullable', 'array'], 'muted.*' => [Rule::in(array_keys(Notifier::CATEGORIES))],
        ]);
        $preference = NotificationPreference::for($user);
        $preference->forceFill([
            'email_enabled' => (bool) ($data['email_enabled'] ?? false), 'push_enabled' => (bool) ($data['push_enabled'] ?? false),
            'whatsapp_enabled' => (bool) ($data['whatsapp_enabled'] ?? false),
            'muted_categories' => array_values(array_diff(array_unique($data['muted'] ?? []), ['security'])),
        ])->save();
        $this->audit->record('notification.preferences_updated', $user, 'user', $user->id);

        return back()->with('status', 'Preferensi notifikasi disimpan.');
    }

    /** Peramban mendaftarkan langganan push (JSON dari PushManager.subscribe()). */
    public function subscribe(Request $request): JsonResponse
    {
        $user = $this->user($request);
        abort_unless(WebPush::configured(), 404);
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000', 'url', 'starts_with:https://'],
            'keys.p256dh' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],
            'keys.auth' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);
        $hash = hash('sha256', $data['endpoint']);
        PushSubscription::query()->where('endpoint_hash', $hash)->delete();
        $subscription = new PushSubscription;
        $subscription->forceFill([
            'user_id' => $user->id, 'endpoint' => $data['endpoint'], 'endpoint_hash' => $hash,
            'p256dh' => $data['keys']['p256dh'], 'auth' => $data['keys']['auth'], 'user_agent' => Str::limit((string) $request->userAgent(), 290, ''),
        ])->save();
        NotificationPreference::for($user)->forceFill(['push_enabled' => true])->save();

        return response()->json(['ok' => true, 'id' => $subscription->id]);
    }

    public function unsubscribe(Request $request, PushSubscription $subscription): RedirectResponse|JsonResponse
    {
        $user = $this->user($request);
        abort_unless($subscription->user_id === $user->id, 404);
        $subscription->delete();

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('status', 'Langganan push dihapus.');
    }

    /** Uji kirim ke diri sendiri (in-app + kanal aktif), maks. 3× per 10 menit. */
    public function test(Request $request, Notifier $notifier): RedirectResponse
    {
        $user = $this->user($request);
        $key = 'notif-test:'.$user->id;
        if ((int) Cache::get($key, 0) >= 3) {
            return back()->with('status', 'Uji kirim dibatasi 3× per 10 menit.');
        }
        Cache::put($key, (int) Cache::get($key, 0) + 1, now()->addMinutes(10));
        $notifier->send($user, 'system', 'Uji notifikasi', 'Ini pesan uji dari preferensi notifikasi Anda.', '/notifikasi', email: true);

        return back()->with('status', 'Pesan uji dikirim ke kanal yang aktif.');
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
