<?php

declare(strict_types=1);

namespace App\Modules\Cms\Http\Controllers;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Cms\Models\LandingSlide;
use App\Modules\Cms\Services\LandingImages;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Slide beranda (Konten Beranda → Slide). Tanpa slide aktif, beranda memakai slide bawaan.
 */
final class LandingSlideController
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly LandingImages $images,
    ) {}

    public function index(): View
    {
        return view('cms.slides.index', ['slides' => LandingSlide::query()->orderBy('position')->orderBy('created_at')->get()]);
    }

    public function create(): View
    {
        return view('cms.slides.form', ['slide' => (new LandingSlide)->forceFill(['is_active' => true, 'position' => LandingSlide::query()->count() + 1])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $slide = new LandingSlide;
        $this->save($request, $slide);
        $this->audit->record('cms.slide.created', $this->actor($request), 'landing_slide', $slide->id, ['title' => $slide->title]);

        return redirect()->route('admin.landing.slides.index')->with('status', 'Slide ditambahkan.');
    }

    public function edit(LandingSlide $slide): View
    {
        return view('cms.slides.form', ['slide' => $slide]);
    }

    public function update(Request $request, LandingSlide $slide): RedirectResponse
    {
        $this->save($request, $slide);
        $this->audit->record('cms.slide.updated', $this->actor($request), 'landing_slide', $slide->id, ['title' => $slide->title, 'is_active' => $slide->is_active]);

        return redirect()->route('admin.landing.slides.index')->with('status', 'Slide diperbarui.');
    }

    public function destroy(Request $request, LandingSlide $slide): RedirectResponse
    {
        $this->images->delete($slide->image_path);
        $slide->delete();
        $this->audit->record('cms.slide.deleted', $this->actor($request), 'landing_slide', $slide->id, ['title' => $slide->title]);

        return redirect()->route('admin.landing.slides.index')->with('status', 'Slide dihapus.');
    }

    private function save(Request $request, LandingSlide $slide): void
    {
        $data = $request->validate([
            'eyebrow' => ['nullable', 'string', 'max:80'],
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:300'],
            'cta_label' => ['nullable', 'required_with:cta_url', 'string', 'max:40'],
            'cta_url' => ['nullable', 'required_with:cta_label', 'string', 'max:300', 'regex:/^(\/([^\/].*)?|https:\/\/\S+)$/'],
            'image' => ['nullable', 'file'],
            'remove_image' => ['nullable', 'boolean'],
            'position' => ['required', 'integer', 'between:0,99'],
            'is_active' => ['nullable', 'boolean'],
        ], ['cta_url.regex' => 'Tautan harus path internal (mis. /program) atau alamat https://.']);

        $actor = $this->actor($request);
        $image = $slide->image_path;
        if ($request->hasFile('image')) {
            $new = $this->images->store($request->file('image'), 'slide', $actor, 'image');
            $this->images->delete($image);
            $image = $new;
        } elseif ($request->boolean('remove_image')) {
            $this->images->delete($image);
            $image = null;
        }

        $slide->forceFill([
            'eyebrow' => $data['eyebrow'] ?? null,
            'title' => trim($data['title']),
            'subtitle' => $data['subtitle'] ?? null,
            'cta_label' => $data['cta_label'] ?? null,
            'cta_url' => $data['cta_url'] ?? null,
            'image_path' => $image,
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
