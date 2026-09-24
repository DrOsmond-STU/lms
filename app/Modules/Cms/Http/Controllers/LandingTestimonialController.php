<?php

declare(strict_types=1);

namespace App\Modules\Cms\Http\Controllers;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Cms\Models\LandingTestimonial;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Testimoni beranda. Terbit hanya bila admin mengonfirmasi persetujuan publikasi dari
 * pemberi testimoni (nama & kutipan adalah data pribadi — UU PDP).
 */
final class LandingTestimonialController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('cms.testimonials.index', ['testimonials' => LandingTestimonial::query()->orderBy('position')->orderBy('created_at')->get()]);
    }

    public function create(): View
    {
        return view('cms.testimonials.form', ['testimonial' => new LandingTestimonial(['rating' => 5, 'position' => LandingTestimonial::query()->count() + 1])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $testimonial = new LandingTestimonial;
        $this->save($request, $testimonial);
        $this->audit->record('cms.testimonial.created', $this->actor($request), 'landing_testimonial', $testimonial->id, ['published' => $testimonial->is_published]);

        return redirect()->route('admin.landing.testimonials.index')->with('status', 'Testimoni ditambahkan.');
    }

    public function edit(LandingTestimonial $testimonial): View
    {
        return view('cms.testimonials.form', ['testimonial' => $testimonial]);
    }

    public function update(Request $request, LandingTestimonial $testimonial): RedirectResponse
    {
        $this->save($request, $testimonial);
        $this->audit->record('cms.testimonial.updated', $this->actor($request), 'landing_testimonial', $testimonial->id, ['published' => $testimonial->is_published]);

        return redirect()->route('admin.landing.testimonials.index')->with('status', 'Testimoni diperbarui.');
    }

    public function destroy(Request $request, LandingTestimonial $testimonial): RedirectResponse
    {
        $testimonial->delete();
        $this->audit->record('cms.testimonial.deleted', $this->actor($request), 'landing_testimonial', $testimonial->id);

        return redirect()->route('admin.landing.testimonials.index')->with('status', 'Testimoni dihapus.');
    }

    private function save(Request $request, LandingTestimonial $testimonial): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'role_title' => ['nullable', 'string', 'max:120'],
            'organization_name' => ['nullable', 'string', 'max:160'],
            'program_name' => ['nullable', 'string', 'max:200'],
            'quote' => ['required', 'string', 'min:10', 'max:600'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'position' => ['required', 'integer', 'between:0,99'],
            'consent_confirmed' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
        ]);
        if ($request->boolean('is_published') && ! $request->boolean('consent_confirmed')) {
            throw ValidationException::withMessages(['consent_confirmed' => 'Testimoni hanya dapat diterbitkan setelah pemberi testimoni menyetujui publikasi.']);
        }

        $testimonial->forceFill([
            'name' => trim($data['name']),
            'role_title' => $data['role_title'] ?? null,
            'organization_name' => $data['organization_name'] ?? null,
            'program_name' => $data['program_name'] ?? null,
            'quote' => trim($data['quote']),
            'rating' => (int) $data['rating'],
            'position' => (int) $data['position'],
            'consent_confirmed' => $request->boolean('consent_confirmed'),
            'is_published' => $request->boolean('is_published'),
            'is_sample' => false,
            'updated_by' => $this->actor($request)->id,
        ])->save();
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
