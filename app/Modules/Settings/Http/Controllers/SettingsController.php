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
 * Pengaturan sistem (FR-SET-001/002/005) — hanya dalam batas aman kode.
 */
final class SettingsController
{
    public function edit(): View
    {
        $values = SystemSettings::all();
        $effective = [];
        foreach (SystemSettings::DEFINITIONS as $key => $definition) {
            $effective[$key] = $values[$key] ?? (isset($definition['config']) ? config($definition['config']) : match ($key) {
                'branding.app_display_name' => config('app.name'),
                default => '',
            });
        }

        return view('settings.edit', ['definitions' => SystemSettings::DEFINITIONS, 'values' => $effective]);
    }

    public function update(Request $request, SystemSettings $settings, AuditLogger $audit): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $settings->update($request->all(), $user, $audit);

        return back()->with('status', 'Pengaturan disimpan dan berlaku seketika.');
    }
}
