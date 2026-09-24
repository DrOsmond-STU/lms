<?php

declare(strict_types=1);

namespace App\Modules\Cms\Http\Controllers;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Cms\Models\SiteProfile;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Informasi pemilik situs yang tampil di beranda & kaki halaman.
 */
final class SiteProfileController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function edit(): View
    {
        return view('cms.profile', ['profile' => SiteProfile::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $https = ['nullable', 'string', 'max:300', 'regex:/^https:\/\/\S+$/'];
        $data = $request->validate([
            'company_name' => ['required', 'string', 'min:2', 'max:160'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'about' => ['nullable', 'string', 'max:1200'],
            'address' => ['nullable', 'string', 'max:300'],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+() .-]{6,30}$/'],
            'whatsapp' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+() .-]{6,30}$/'],
            'business_hours' => ['nullable', 'string', 'max:120'],
            'website_url' => $https,
            'linkedin_url' => $https,
            'instagram_url' => $https,
        ], ['*.regex' => 'Format tidak valid (tautan harus diawali https://; nomor hanya angka, spasi, +, -).']);

        /** @var User $actor */
        $actor = $request->user();
        $profile = SiteProfile::current();
        $profile->forceFill(array_map(fn ($value) => is_string($value) ? trim($value) : $value, $data) + ['id' => 1, 'updated_by' => $actor->id]);
        $changed = array_keys($profile->getDirty());
        $profile->save();
        $this->audit->record('cms.site_profile.updated', $actor, 'site_profile', null, ['fields' => array_values(array_diff($changed, ['updated_by', 'updated_at']))]);

        return redirect()->route('admin.landing.profile.edit')->with('status', 'Informasi pemilik situs disimpan.');
    }
}
