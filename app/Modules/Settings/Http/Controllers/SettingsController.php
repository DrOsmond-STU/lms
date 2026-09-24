<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
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
}
