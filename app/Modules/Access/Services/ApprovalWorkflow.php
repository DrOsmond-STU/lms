<?php

declare(strict_types=1);

namespace App\Modules\Access\Services;

use App\Modules\Access\Models\ApprovalRequest;
use App\Modules\Access\RoleCode;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Certification\Models\Certificate;
use App\Modules\Certification\Models\CertificateTemplate;
use App\Modules\Certification\Services\CertificateIssuer;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Services\Notifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Maker–checker generik (keamanan/03 SEC-AUTHZ-15, docs/07 §3): aksi berdampak tinggi
 * dieksekusi hanya setelah disetujui pengguna BERBEDA yang berwenang. Constraint DB
 * `approval_requests_sod_check` menjamin pemutus ≠ pengaju.
 */
final class ApprovalWorkflow
{
    public const MAX_SUPER_ADMINS = 3;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly Notifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function request(string $action, string $subjectType, string $subjectId, array $payload, string $reason, User $requester): ApprovalRequest
    {
        try {
            $request = DB::transaction(function () use ($action, $subjectType, $subjectId, $payload, $reason, $requester): ApprovalRequest {
                $request = new ApprovalRequest;
                $request->forceFill([
                    'id' => (string) Str::uuid7(),
                    'action' => $action,
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'payload' => $payload,
                    'reason' => $reason,
                    'requested_by' => $requester->id,
                    'requested_at' => now(),
                    'expires_at' => now()->addDays(7),
                ])->save();
                $this->audit->record('approval.requested', $requester, $subjectType, $subjectId, ['action' => $action] + $payload, $reason);

                return $request;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['reason' => 'Sudah ada permintaan persetujuan yang terbuka untuk objek ini.']);
        }

        foreach ($this->eligibleDeciders($action, $requester) as $decider) {
            $this->notifier->send($decider, 'system', 'Persetujuan kedua diperlukan', (ApprovalRequest::ACTIONS[$action] ?? $action).' menunggu keputusan Anda.', '/admin/persetujuan');
        }

        return $request;
    }

    public function canDecide(ApprovalRequest $request, User $user): bool
    {
        if ($request->requested_by === $user->id || ! $request->isOpen()) {
            return false;
        }

        return match ($request->action) {
            'certificate.revoke' => $user->hasPermission('certificate.revoke'),
            'role.assign_super_admin' => $user->hasRole(RoleCode::SuperAdmin),
            'certificate_template.activate' => $user->hasPermission('certificate_template.activate'),
            default => false,
        };
    }

    /** @throws ValidationException */
    public function decide(ApprovalRequest $request, User $decider, bool $approve, ?string $reason): void
    {
        if (! $this->canDecide($request, $decider)) {
            throw ValidationException::withMessages(['decision' => 'Anda tidak berwenang memutus permintaan ini (pengaju tidak boleh memutus sendiri).']);
        }

        DB::transaction(function () use ($request, $decider, $approve, $reason): void {
            $updated = ApprovalRequest::query()->whereKey($request->id)->whereNull('decision')->update([
                'decision' => $approve ? 'approved' : 'rejected',
                'decided_by' => $decider->id,
                'decided_at' => now(),
                'decision_reason' => $reason,
            ]);
            if ($updated !== 1) {
                throw ValidationException::withMessages(['decision' => 'Permintaan sudah diputus.']);
            }
            $this->audit->record($approve ? 'approval.approved' : 'approval.rejected', $decider, $request->subject_type, $request->subject_id, ['action' => $request->action], $reason);

            if ($approve) {
                $this->execute($request, $decider);
            }
        });

        $this->notifier->send($request->requester, 'system', 'Permintaan '.($approve ? 'disetujui' : 'ditolak'), (ApprovalRequest::ACTIONS[$request->action] ?? $request->action).' telah '.($approve ? 'disetujui' : 'ditolak').'.', '/admin/persetujuan');
    }

    /** @return list<User> */
    private function eligibleDeciders(string $action, User $requester): array
    {
        $roles = $action === 'role.assign_super_admin' ? [RoleCode::SuperAdmin->value] : [RoleCode::SuperAdmin->value, RoleCode::AcademicAdmin->value];

        return array_values(User::query()->where('status', 'active')->whereKeyNot($requester->id)
            ->whereHas('roles', fn ($query) => $query->whereIn('code', $roles))
            ->limit(20)->get()->all());
    }

    private function execute(ApprovalRequest $request, User $decider): void
    {
        $requester = User::query()->findOrFail($request->requested_by);

        match ($request->action) {
            'certificate.revoke' => app(CertificateIssuer::class)->revoke(
                Certificate::query()->findOrFail($request->subject_id),
                (string) $request->payload['reason_code'],
                $request->reason,
                $requester,
                $decider,
            ),
            'role.assign_super_admin' => $this->assignSuperAdmin(User::query()->findOrFail($request->subject_id), $decider),
            'certificate_template.activate' => $this->activateTemplate(CertificateTemplate::query()->findOrFail($request->subject_id), $decider),
            default => throw ValidationException::withMessages(['decision' => 'Aksi tidak dikenal.']),
        };
    }

    private function activateTemplate(CertificateTemplate $template, User $decider): void
    {
        CertificateTemplate::query()->where('category', $template->category)
            ->when($template->program_id === null, fn ($query) => $query->whereNull('program_id'), fn ($query) => $query->where('program_id', $template->program_id))
            ->where('is_active', true)->update(['is_active' => false]);
        $template->forceFill(['is_active' => true, 'activated_by' => $decider->id])->save();
    }

    private function assignSuperAdmin(User $target, User $decider): void
    {
        $count = DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')->where('roles.code', RoleCode::SuperAdmin->value)->distinct()->count('role_user.user_id');
        if ($count >= self::MAX_SUPER_ADMINS) {
            throw ValidationException::withMessages(['decision' => 'Jumlah Super Admin sudah mencapai batas '.self::MAX_SUPER_ADMINS.' akun.']);
        }
        if (RoleAssigner::conflictsWith($target, RoleCode::SuperAdmin)) {
            throw ValidationException::withMessages(['decision' => 'Akun peserta tidak dapat menjadi Super Admin — gunakan akun terpisah.']);
        }

        app(RoleAssigner::class)->assign($target, RoleCode::SuperAdmin, null, $decider);
    }
}
