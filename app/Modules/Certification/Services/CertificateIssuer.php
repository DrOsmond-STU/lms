<?php

declare(strict_types=1);

namespace App\Modules\Certification\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Certification\Jobs\GenerateCertificatePdf;
use App\Modules\Certification\Models\Certificate;
use App\Modules\Certification\Models\CertificateTemplate;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Services\EnrollmentService;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Services\ClassAccess;
use App\Modules\Notification\Services\Notifier;
use App\Modules\Organization\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Penerbitan & pencabutan sertifikat (FR-CERT-002/004/005/009, keamanan/07):
 * approval dengan SoD, nomor atomik dari sequence dalam transaksi yang sama, kode
 * verifikasi CSPRNG, PDF dibuat sekali oleh job idempoten, hash disimpan.
 */
final class CertificateIssuer
{
    public function __construct(
        private readonly EnrollmentService $enrollments,
        private readonly AuditLogger $audit,
        private readonly Notifier $notifier,
        private readonly ClassAccess $classAccess,
        private readonly CertificatePdfRenderer $renderer,
        private readonly CertificateSigner $signer,
    ) {}

    /** Alasan SoD/kondisi yang menghalangi approver (null = boleh). */
    public function approvalBlocker(Enrollment $enrollment, User $approver): ?string
    {
        if ($enrollment->status !== 'pending_approval') {
            return 'Enrollment tidak berada dalam antrean approval.';
        }
        if ($enrollment->user_id === $approver->id) {
            return 'Anda tidak dapat menyetujui sertifikat milik sendiri.';
        }
        // SoD: trainer (termasuk admin yang juga trainer kelas ini) tidak menyetujui kelasnya sendiri.
        if ($this->classAccess->teaches($approver, $enrollment->course_class_id)) {
            return 'Trainer pengampu kelas ini tidak boleh menyetujui sertifikatnya (pemisahan tugas).';
        }
        if ($this->activeTemplate($enrollment) === null) {
            return 'Belum ada template sertifikat aktif untuk kategori/program ini.';
        }

        return null;
    }

    /** @throws ValidationException */
    public function approve(Enrollment $enrollment, User $approver): Certificate
    {
        if (($blocker = $this->approvalBlocker($enrollment, $approver)) !== null) {
            throw ValidationException::withMessages(['enrollment' => $blocker]);
        }
        /** @var CertificateTemplate $template */
        $template = $this->activeTemplate($enrollment);
        $program = $enrollment->program;
        $holder = $enrollment->user;

        $certificate = DB::transaction(function () use ($enrollment, $approver, $template, $program, $holder): Certificate {
            $this->enrollments->transition($enrollment, 'passed', $approver, 'Sertifikat disetujui', [
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            $year = (int) now()->timezone('Asia/Jakarta')->format('Y');
            DB::table('certificate_sequences')->insertOrIgnore(['program_id' => $program->id, 'year' => $year, 'last_value' => 0]);
            $sequence = DB::selectOne('UPDATE certificate_sequences SET last_value = last_value + 1 WHERE program_id = ? AND year = ? RETURNING last_value', [$program->id, $year]);
            $orgCode = $enrollment->organization_id === null ? 'STU' : (string) (Organization::query()->whereKey($enrollment->organization_id)->value('code') ?? 'STU');
            $number = implode('/', [
                $program->category === 'bnsp' ? 'BNSP' : 'INT',
                $program->short_code,
                $orgCode,
                $year,
                str_pad((string) $sequence->last_value, 5, '0', STR_PAD_LEFT),
            ]);

            $certificate = new Certificate;
            $certificate->forceFill([
                'id' => (string) Str::uuid7(),
                'organization_id' => $enrollment->organization_id,
                'enrollment_id' => $enrollment->id,
                'user_id' => $holder->id,
                'program_id' => $program->id,
                'template_id' => $template->id,
                'number' => $number,
                'verification_code' => $this->uniqueCode(),
                'holder_name' => $holder->name,
                'holder_name_masked' => self::mask($holder->name),
                'program_name' => $program->name,
                'provider_name' => $program->provider_name,
                'category' => $program->category,
                'issued_at' => now(),
                'valid_until' => $program->certificate_validity_months > 0 ? now()->addMonths($program->certificate_validity_months)->toDateString() : null,
                'status' => 'generating',
                'approved_by' => $approver->id,
            ])->save();

            CertificateTemplate::query()->whereKey($template->id)->whereNull('used_at')->update(['used_at' => now()]);
            $this->audit->record('certificate.approved', $approver, 'certificate', $certificate->id, [
                'number' => $number, 'enrollment_id' => $enrollment->id, 'final_score' => $enrollment->final_score,
            ], organizationId: $enrollment->organization_id);

            return $certificate;
        });

        GenerateCertificatePdf::dispatch($certificate->id)->afterCommit();

        return $certificate;
    }

    public function reject(Enrollment $enrollment, User $approver, string $reason): void
    {
        if ($enrollment->status !== 'pending_approval') {
            throw ValidationException::withMessages(['reason' => 'Enrollment tidak berada dalam antrean approval.']);
        }

        DB::transaction(function () use ($enrollment, $approver, $reason): void {
            $this->enrollments->transition($enrollment, 'in_progress', $approver, $reason, ['rejection_reason' => $reason]);
            $this->audit->record('certificate.rejected', $approver, 'enrollment', $enrollment->id, null, $reason, $enrollment->organization_id);
        });

        $this->notifier->send($enrollment->user, 'certificate', 'Approval sertifikat ditolak', 'Alasan: '.$reason.'. Hubungi trainer/admin untuk tindak lanjut.', '/peserta/pembelajaran', email: true);
    }

    /** Job pembuatan PDF — idempoten per sertifikat (SEC-CERT-20). */
    public function generatePdf(string $certificateId): void
    {
        DB::transaction(function () use ($certificateId): void {
            /** @var Certificate|null $certificate */
            $certificate = Certificate::query()->whereKey($certificateId)->lockForUpdate()->first();
            if ($certificate === null || ! in_array($certificate->status, ['generating', 'generation_failed'], true)) {
                return;
            }

            /** @var CertificateTemplate $template */
            $template = CertificateTemplate::query()->findOrFail($certificate->template_id);
            try {
                $pdf = $this->renderer->render($certificate, $template);
            } catch (Throwable $exception) {
                $certificate->forceFill(['status' => 'generation_failed'])->save();
                report($exception);

                return;
            }

            $key = 'certificates/'.$certificate->issued_at->format('Y').'/'.$certificate->id.'.pdf';
            Storage::disk('local')->put($key, $pdf);
            $certificate->forceFill([
                'status' => 'active',
                'pdf_storage_key' => $key,
                'pdf_sha256' => hash('sha256', $pdf),
                'signature_cert_fingerprint' => $this->signer->isConfigured() ? $this->signer->fingerprint() : null,
                'signed_at' => $this->signer->isConfigured() ? now() : null,
            ])->save();
            $this->audit->record('certificate.issued', null, 'certificate', $certificate->id, ['number' => $certificate->number, 'sha256' => $certificate->pdf_sha256], organizationId: $certificate->organization_id);

            $this->notifier->send($certificate->user, 'certificate', 'Sertifikat terbit', 'Sertifikat '.$certificate->program_name.' ('.$certificate->number.') sudah dapat diunduh.', '/peserta/sertifikat', email: true);
        });
    }

    /** Dieksekusi setelah persetujuan kedua (maker–checker) — FR-CERT-009. */
    public function revoke(Certificate $certificate, string $reasonCode, string $reasonText, User $requestedBy, User $approvedBy): void
    {
        DB::transaction(function () use ($certificate, $reasonCode, $reasonText, $requestedBy, $approvedBy): void {
            $updated = Certificate::query()->whereKey($certificate->id)->whereIn('status', ['active', 'generating', 'generation_failed'])->update(['status' => 'revoked', 'updated_at' => now()]);
            if ($updated !== 1) {
                throw ValidationException::withMessages(['reason' => 'Sertifikat sudah tidak aktif.']);
            }
            DB::table('certificate_revocations')->insert([
                'id' => (string) Str::uuid7(), 'certificate_id' => $certificate->id, 'reason_code' => $reasonCode,
                'reason_text' => $reasonText, 'requested_by' => $requestedBy->id, 'approved_by' => $approvedBy->id, 'revoked_at' => now(),
            ]);
            $this->audit->record('certificate.revoked', $approvedBy, 'certificate', $certificate->id, ['reason_code' => $reasonCode, 'requested_by' => $requestedBy->id], $reasonText, $certificate->organization_id);
        });

        $this->notifier->send($certificate->user, 'certificate', 'Sertifikat dicabut', 'Sertifikat '.$certificate->number.' dicabut. Alasan: '.$reasonText, '/peserta/sertifikat', email: true);
    }

    public function activeTemplate(Enrollment $enrollment): ?CertificateTemplate
    {
        $program = $enrollment->program;

        return CertificateTemplate::query()->where('is_active', true)->where('category', $program->category)
            ->where(fn ($query) => $query->where('program_id', $program->id)->orWhereNull('program_id'))
            ->orderByRaw('program_id IS NULL')
            ->first();
    }

    /** Nama tersamar untuk pencarian via nomor (SEC-CERT-11): "R*** P*******". */
    public static function mask(string $name): string
    {
        return collect(preg_split('/\s+/u', trim($name)) ?: [])
            ->map(fn (string $part): string => mb_substr($part, 0, 1).str_repeat('*', max(1, mb_strlen($part) - 1)))
            ->implode(' ');
    }

    private function uniqueCode(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $code = VerificationCode::generate();
            if (! DB::table('certificates')->where('verification_code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Kode verifikasi bentrok berulang.');
    }
}
