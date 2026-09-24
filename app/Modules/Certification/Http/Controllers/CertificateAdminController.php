<?php

declare(strict_types=1);

namespace App\Modules\Certification\Http\Controllers;

use App\Modules\Access\Services\ApprovalWorkflow;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Catalog\Models\Program;
use App\Modules\Certification\Models\Certificate;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Organization;
use App\Support\Database\Like;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Basis data sertifikat (FR-CERT-011) & pengajuan pencabutan (FR-CERT-009, maker–checker).
 * Ekspor tercatat di audit; nilai sel dinetralkan dari CSV injection (SEC-INPUT-19).
 */
final class CertificateAdminController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        return view('certificates.admin-index', [
            'certificates' => $this->query($request)->with('program:id,name')->orderByDesc('issued_at')->paginate(30)->withQueryString(),
            'programs' => Program::query()->orderBy('name')->get(['id', 'name']),
            'organizations' => Organization::query()->orderBy('name')->get(['id', 'name', 'code']),
            'filters' => $request->only(['q', 'status', 'program', 'organisasi', 'tahun']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        $query = $this->query($request)->orderByDesc('issued_at');
        $this->audit->record('certificate.exported', $user, 'certificate', null, ['filters' => $request->only(['q', 'status', 'program', 'organisasi', 'tahun']), 'rows' => (clone $query)->count()]);

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['Nomor', 'Kode Verifikasi', 'Nama', 'Program', 'Kategori', 'Terbit', 'Berlaku Hingga', 'Status']);
            $query->chunk(500, function ($rows) use ($out): void {
                foreach ($rows as $certificate) {
                    /** @var Certificate $certificate */
                    fputcsv($out, array_map([self::class, 'neutralize'], [
                        $certificate->number, $certificate->verification_code, $certificate->holder_name, $certificate->program_name,
                        $certificate->category, $certificate->issued_at->format('Y-m-d'), $certificate->valid_until?->format('Y-m-d') ?? '',
                        Certificate::statusLabel($certificate->publicStatus()),
                    ]));
                }
            });
            fclose($out);
        }, 'sertifikat-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function show(Certificate $certificate): View
    {
        $certificate->load('program', 'enrollment.courseClass');
        $revocation = DB::table('certificate_revocations')->where('certificate_id', $certificate->id)->first();
        $pending = DB::table('approval_requests')->where('action', 'certificate.revoke')->where('subject_id', $certificate->id)->whereNull('decision')->first();
        $people = User::query()->whereIn('id', array_filter([$certificate->approved_by, $revocation?->requested_by, $revocation?->approved_by, $pending?->requested_by]))->pluck('name', 'id');

        return view('certificates.admin-show', compact('certificate', 'revocation', 'pending', 'people'));
    }

    public function requestRevoke(Request $request, Certificate $certificate, ApprovalWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'reason_code' => ['required', Rule::in(['integrity_violation', 'data_error', 'holder_request', 'other'])],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        abort_unless(in_array($certificate->status, ['active', 'generating', 'generation_failed'], true), 409);
        /** @var User $user */
        $user = $request->user();
        $workflow->request('certificate.revoke', 'certificate', $certificate->id, ['reason_code' => $data['reason_code'], 'number' => $certificate->number], $data['reason'], $user);

        return back()->with('status', 'Permintaan pencabutan diajukan. Menunggu persetujuan admin kedua.');
    }

    /** @return Builder<Certificate> */
    private function query(Request $request): Builder
    {
        $search = Str::limit(trim((string) $request->query('q', '')), 100, '');
        $status = (string) $request->query('status', '');
        $year = (int) $request->query('tahun', 0);

        return Certificate::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($where) => $where->where('number', strtoupper($search))->orWhere('holder_name', 'ilike', Like::contains($search))->orWhere('verification_code', strtoupper(str_replace('-', '', $search)))))
            ->when($status === 'revoked', fn ($query) => $query->where('status', 'revoked'))
            ->when($status === 'expired', fn ($query) => $query->where('status', 'active')->whereNotNull('valid_until')->where('valid_until', '<', now()->toDateString()))
            ->when($status === 'valid', fn ($query) => $query->where('status', 'active')->where(fn ($w) => $w->whereNull('valid_until')->orWhere('valid_until', '>=', now()->toDateString())))
            ->when($status === 'processing', fn ($query) => $query->whereIn('status', ['generating', 'generation_failed']))
            ->when(Str::isUuid((string) $request->query('program')), fn ($query) => $query->where('program_id', $request->query('program')))
            ->when(Str::isUuid((string) $request->query('organisasi')), fn ($query) => $query->where('organization_id', $request->query('organisasi')))
            ->when($year >= 2000 && $year <= 2100, fn ($query) => $query->whereYear('issued_at', $year));
    }

    public static function neutralize(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
