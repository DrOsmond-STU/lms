<?php

declare(strict_types=1);

namespace App\Modules\Cms\Http\Controllers;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Cms\Models\LandingPartner;
use App\Modules\Cms\Services\LandingImages;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Mitra pengguna (logo berjalan di beranda). Tanpa logo, nama ditampilkan sebagai wordmark.
 */
final class LandingPartnerController
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly LandingImages $images,
    ) {}

    public function index(): View
    {
        return view('cms.partners.index', ['partners' => LandingPartner::query()->orderBy('position')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('cms.partners.form', ['partner' => new LandingPartner(['type' => 'company', 'is_active' => true, 'position' => LandingPartner::query()->count() + 1])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $partner = new LandingPartner;
        $this->save($request, $partner);
        $this->audit->record('cms.partner.created', $this->actor($request), 'landing_partner', $partner->id, ['name' => $partner->name]);

        return redirect()->route('admin.landing.partners.index')->with('status', 'Mitra ditambahkan.');
    }

    public function edit(LandingPartner $partner): View
    {
        return view('cms.partners.form', ['partner' => $partner]);
    }

    public function update(Request $request, LandingPartner $partner): RedirectResponse
    {
        $this->save($request, $partner);
        $this->audit->record('cms.partner.updated', $this->actor($request), 'landing_partner', $partner->id, ['name' => $partner->name, 'is_active' => $partner->is_active]);

        return redirect()->route('admin.landing.partners.index')->with('status', 'Mitra diperbarui.');
    }

    public function destroy(Request $request, LandingPartner $partner): RedirectResponse
    {
        $this->images->delete($partner->logo_path);
        $partner->delete();
        $this->audit->record('cms.partner.deleted', $this->actor($request), 'landing_partner', $partner->id, ['name' => $partner->name]);

        return redirect()->route('admin.landing.partners.index')->with('status', 'Mitra dihapus.');
    }

    private function save(Request $request, LandingPartner $partner): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'type' => ['required', Rule::in(array_keys(LandingPartner::TYPES))],
            'website_url' => ['nullable', 'string', 'max:300', 'regex:/^https:\/\/\S+$/'],
            'logo' => ['nullable', 'file'],
            'remove_logo' => ['nullable', 'boolean'],
            'position' => ['required', 'integer', 'between:0,99'],
            'is_active' => ['nullable', 'boolean'],
        ], ['website_url.regex' => 'Alamat situs harus diawali https://.']);

        $actor = $this->actor($request);
        $logo = $partner->logo_path;
        if ($request->hasFile('logo')) {
            $new = $this->images->store($request->file('logo'), 'logo', $actor, 'logo');
            $this->images->delete($logo);
            $logo = $new;
        } elseif ($request->boolean('remove_logo')) {
            $this->images->delete($logo);
            $logo = null;
        }

        $partner->forceFill([
            'name' => trim($data['name']),
            'type' => $data['type'],
            'website_url' => $data['website_url'] ?? null,
            'logo_path' => $logo,
            'position' => (int) $data['position'],
            'is_active' => $request->boolean('is_active'),
            'updated_by' => $actor->id,
        ])->save();
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
