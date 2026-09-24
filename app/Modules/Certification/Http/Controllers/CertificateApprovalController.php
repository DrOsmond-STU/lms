<?php

declare(strict_types=1);

namespace App\Modules\Certification\Http\Controllers;

use App\Modules\Certification\Services\CertificateIssuer;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Services\CompletionEvaluator;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Antrean approval sertifikat (FR-CERT-002, docs/08 ADM-02) dengan ringkasan bukti dan
 * aturan pemisahan tugas; aksi setujui/tolak memerlukan re-autentikasi.
 */
final class CertificateApprovalController
{
    public function __construct(private readonly CertificateIssuer $issuer) {}

    public function index(): View
    {
        $queue = Enrollment::query()->with(['user:id,name,email', 'program:id,name,category,short_code', 'courseClass:id,batch_name'])
            ->where('status', 'pending_approval')->orderBy('completed_at')->paginate(30);

        return view('certificates.approval-index', ['queue' => $queue]);
    }

    public function show(Request $request, Enrollment $enrollment): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($enrollment->status === 'pending_approval', 404);
        $enrollment->load('user', 'program', 'courseClass');
        $attempts = DB::table('exam_attempts')->join('assessments', 'assessments.id', '=', 'exam_attempts.assessment_id')
            ->where('exam_attempts.enrollment_id', $enrollment->id)->orderBy('exam_attempts.started_at')
            ->get(['assessments.title', 'assessments.kind', 'exam_attempts.attempt_no', 'exam_attempts.status', 'exam_attempts.score', 'exam_attempts.passed', 'exam_attempts.integrity_flags']);
        $progressFlags = DB::table('lesson_progress')->where('enrollment_id', $enrollment->id)->whereNotNull('integrity_flags')->count();

        return view('certificates.approval-show', [
            'enrollment' => $enrollment,
            'check' => CompletionEvaluator::check($enrollment),
            'attempts' => $attempts,
            'progressFlags' => $progressFlags,
            'blocker' => $this->issuer->approvalBlocker($enrollment, $user),
        ]);
    }

    public function approve(Request $request, Enrollment $enrollment): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $certificate = $this->issuer->approve($enrollment, $user);

        return redirect()->route('admin.approvals.index')->with('status', 'Sertifikat '.$certificate->number.' disetujui. PDF sedang dibuat.');
    }

    public function reject(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        /** @var User $user */
        $user = $request->user();
        $this->issuer->reject($enrollment, $user, $data['reason']);

        return redirect()->route('admin.approvals.index')->with('status', 'Approval ditolak dan peserta dinotifikasi.');
    }
}
