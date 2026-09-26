<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Services\WebPush;
use App\Modules\Notification\Services\WhatsAppGateway;
use App\Modules\Settings\Services\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pengaturan sistem bertab (FR-SET-001/002/005). Setiap tab dijaga izinnya sendiri; tab
 * teknis/keamanan juga memerlukan re-autentikasi saat menyimpan (lihat rute).
 */
final class SettingsController
{
    public function index(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        foreach (SystemSettings::TABS as $tab => [, $viewPermission]) {
            if ($user->can($viewPermission)) {
                return redirect()->route($tab === 'pemilik' ? 'admin.settings.owner' : 'admin.settings.tab', $tab === 'pemilik' ? [] : ['tab' => $tab]);
            }
        }

        abort(403);
    }

    public function show(Request $request, string $tab): View
    {
        $user = $this->user($request);
        abort_unless($user->can(SystemSettings::TABS[$tab][1]), 403);

        $values = [];
        foreach (SystemSettings::forTab($tab) as $key => $definition) {
            $values[$key] = SystemSettings::get($key);
        }

        return view('settings.tab', [
            'tab' => $tab,
            'definitions' => SystemSettings::forTab($tab),
            'values' => $values,
            'canUpdate' => $user->can(SystemSettings::TABS[$tab][2]),
        ]);
    }

    public function update(Request $request, string $tab, SystemSettings $settings, AuditLogger $audit): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($user->can(SystemSettings::TABS[$tab][2]), 403);
        $settings->update($tab, $request->all(), $user, $audit);

        return redirect()->route('admin.settings.tab', $tab)->with('status', 'Pengaturan '.SystemSettings::TABS[$tab][0].' disimpan dan berlaku seketika.');
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /** Bangkitkan pasangan kunci VAPID baru dan simpan (privat terenkripsi). */
    public function generateVapid(Request $request, SystemSettings $settings, AuditLogger $audit): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $keys = WebPush::generateKeys();
        $settings->put(['push.vapid_public' => $keys['public'], 'push.vapid_private' => $keys['private']], $user, $audit, 'vapid_generated');

        return redirect()->route('admin.settings.tab', 'integrasi')->with('status', 'Kunci VAPID baru dibuat. Aktifkan Web Push lalu simpan.');
    }

    /** Uji gateway WhatsApp ke nomor HP admin yang sedang masuk. */
    public function testWhatsApp(Request $request, WhatsAppGateway $gateway): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $phone = $user->getAttribute('phone_encrypted');
        if (! WhatsAppGateway::configured()) {
            return back()->with('status', 'Gateway WhatsApp belum lengkap: aktifkan, isi endpoint dan token, lalu simpan.');
        }
        if (! is_string($phone) || $phone === '') {
            return back()->with('status', 'Isi nomor HP Anda di Akun Saya terlebih dahulu.');
        }
        $result = $gateway->send($phone, 'Pesan uji dari '.config('app.name').' — gateway WhatsApp terhubung.');

        return back()->with('status', $result['ok'] ? 'Pesan uji terkirim (HTTP '.$result['status'].').' : 'Gagal: '.$result['error']);
    }
}
