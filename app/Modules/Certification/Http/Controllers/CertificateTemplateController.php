<?php

declare(strict_types=1);

namespace App\Modules\Certification\Http\Controllers;

use App\Modules\Access\Services\ApprovalWorkflow;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Catalog\Models\Program;
use App\Modules\Certification\Models\Certificate;
use App\Modules\Certification\Models\CertificateTemplate;
use App\Modules\Certification\Services\CertificatePdfRenderer;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Template sertifikat berversi (FR-CERT-001, SEC-CERT-04): field teks terstruktur dengan
 * placeholder terbatas; immutable setelah dipakai; aktivasi melalui persetujuan kedua.
 */
final class CertificateTemplateController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $templates = CertificateTemplate::query()->orderBy('category')->orderBy('program_id')->orderByDesc('version')->get();
        $programs = Program::query()->whereIn('id', $templates->pluck('program_id')->filter())->pluck('name', 'id');
        $pending = DB::table('approval_requests')->where('action', 'certificate_template.activate')->whereNull('decision')->pluck('subject_id')->flip();

        return view('certificates.templates-index', compact('templates', 'programs', 'pending'));
    }

    public function create(Request $request): View
    {
        $sourceId = (string) $request->query('dari', '');
        $source = Str::isUuid($sourceId) ? CertificateTemplate::query()->whereKey($sourceId)->first() : null;

        return view('certificates.template-form', [
            'template' => $source?->replicate(['is_active', 'used_at', 'activated_by']) ?? (new CertificateTemplate)->forceFill([
                'category' => 'international', 'title_text' => 'Sertifikat Pelatihan', 'accent_color' => '#0e3a63',
                'body_text' => 'telah menyelesaikan dan dinyatakan lulus {kategori} program berikut yang diselenggarakan oleh {penyelenggara}.',
            ]),
            'programs' => Program::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        /** @var User $user */
        $user = $request->user();
        $version = (int) CertificateTemplate::query()->where('category', $data['category'])->where('program_id', $data['program_id'] ?? null)->max('version') + 1;

        $template = new CertificateTemplate;
        $template->forceFill($data + ['version' => $version, 'is_active' => false, 'created_by' => $user->id])->save();
        $this->audit->record('certificate_template.created', $user, 'certificate_template', $template->id, ['version' => $version, 'category' => $template->category]);

        return redirect()->route('admin.templates.index')->with('status', 'Template versi '.$version.' dibuat (belum aktif).');
    }

    public function edit(CertificateTemplate $template): View
    {
        abort_if($template->isLocked() || $template->is_active, 409, 'Template sudah dipakai/aktif — buat versi baru.');

        return view('certificates.template-form', ['template' => $template, 'programs' => Program::query()->orderBy('name')->get(['id', 'name'])]);
    }

    public function update(Request $request, CertificateTemplate $template): RedirectResponse
    {
        abort_if($template->isLocked() || $template->is_active, 409, 'Template sudah dipakai/aktif — buat versi baru.');
        $data = $this->validated($request);
        unset($data['category'], $data['program_id']);
        /** @var User $user */
        $user = $request->user();
        $template->forceFill($data)->save();
        $this->audit->record('certificate_template.updated', $user, 'certificate_template', $template->id);

        return redirect()->route('admin.templates.index')->with('status', 'Template diperbarui.');
    }

    public function requestActivation(Request $request, CertificateTemplate $template, ApprovalWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        abort_if($template->is_active, 409);
        /** @var User $user */
        $user = $request->user();
        $workflow->request('certificate_template.activate', 'certificate_template', $template->id, ['version' => $template->version], $data['reason'], $user);

        return back()->with('status', 'Aktivasi template diajukan. Menunggu persetujuan admin kedua.');
    }

    /** Hapus template yang tidak aktif dan belum pernah dipakai menerbitkan sertifikat. */
    public function destroy(Request $request, CertificateTemplate $template): RedirectResponse
    {
        if ($template->is_active || $template->used_at !== null || DB::table('certificates')->where('template_id', $template->id)->exists()) {
            throw ValidationException::withMessages(['template' => 'Template aktif atau yang sudah dipakai tidak dapat dihapus.']);
        }
        if (DB::table('approval_requests')->where('subject_id', $template->id)->whereNull('decision')->exists()) {
            throw ValidationException::withMessages(['template' => 'Template sedang menunggu persetujuan aktivasi.']);
        }
        /** @var User $actor */
        $actor = $request->user();
        $template->delete();
        $this->audit->record('certificate_template.deleted', $actor, 'certificate_template', $template->id, ['name' => $template->name, 'version' => $template->version]);

        return redirect()->route('admin.templates.index')->with('status', 'Template "'.$template->name.'" dihapus.');
    }

    public function preview(CertificateTemplate $template, CertificatePdfRenderer $renderer): Response
    {
        $sample = new Certificate;
        $sample->forceFill([
            'number' => 'INT/CONTOH/STU/'.now()->format('Y').'/00001', 'verification_code' => '0000CONTOH00',
            'holder_name' => 'Nama Peserta Contoh', 'program_name' => 'Nama Program Contoh', 'provider_name' => 'Penyelenggara Contoh',
            'category' => $template->category, 'issued_at' => now(), 'valid_until' => now()->addYears(3),
        ]);

        return response($renderer->render($sample, $template), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="pratinjau-template.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $request->merge(['accent_color' => mb_strtolower((string) $request->input('accent_color'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'category' => ['required', Rule::in(array_keys(Program::CATEGORIES))],
            'program_id' => ['nullable', 'uuid', Rule::exists('programs', 'id')],
            'title_text' => ['required', 'string', 'max:120'],
            'body_text' => ['required', 'string', 'max:500'],
            'signatory_name' => ['required', 'string', 'max:120'],
            'signatory_title' => ['required', 'string', 'max:120'],
            'accent_color' => ['required', 'regex:/^#[0-9a-f]{6}$/'],
        ]);

        preg_match_all('/\{[^}]*\}/', $data['body_text'], $matches);
        $unknown = array_diff($matches[0], CertificateTemplate::PLACEHOLDERS);
        if ($unknown !== []) {
            throw ValidationException::withMessages(['body_text' => 'Placeholder tidak dikenal: '.implode(', ', $unknown)]);
        }

        return $data;
    }
}
