<?php

declare(strict_types=1);

namespace App\Modules\Notification\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Notification\Models\InAppNotification;
use App\Modules\Notification\Services\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pusat notifikasi (FR-NTF-001, docs/08 PST-09) — hanya milik pengguna sendiri.
 */
final class NotificationController
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $unreadOnly = $request->query('filter') === 'belum-dibaca';

        $notifications = InAppNotification::query()
            ->where('user_id', $user->id)
            ->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
            'unreadOnly' => $unreadOnly,
            'categories' => Notifier::CATEGORIES,
            'workspace' => $user->defaultWorkspace() ?? 'participant',
        ]);
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $item = InAppNotification::query()->where('user_id', $user->id)->whereKey($notification)->firstOrFail();
        if ($item->read_at === null) {
            $item->forceFill(['read_at' => now()])->save();
        }

        return redirect($item->action_url ?? route('notifications.index'));
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        InAppNotification::query()->where('user_id', $user->id)->whereNull('read_at')->update(['read_at' => now()]);

        return redirect()->route('notifications.index')->with('status', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
