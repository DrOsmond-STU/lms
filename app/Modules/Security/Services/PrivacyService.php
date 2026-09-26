<?php

declare(strict_types=1);

namespace App\Modules\Security\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Audit\Services\SecurityEventLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\DeviceSessions;
use App\Modules\Identity\Services\OneTimeTokens;
use App\Modules\Notification\Services\Notifier;
use App\Modules\Security\Models\PrivacyRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Hak subjek data (UU PDP No. 27/2022): salinan data (JSON, langsung) dan penghapusan akun
 * (anonimisasi setelah ditinjau admin; sertifikat & rekam kelulusan disimpan sesuai kewajiban
 * penyelenggara, dengan nama pemegang tetap pada sertifikat).
 */
final class PrivacyService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SecurityEventLogger $events,
        private readonly Notifier $notifier,
        private readonly OneTimeTokens $tokens,
        private readonly DeviceSessions $devices,
    ) {}

    /** @return array<string, mixed> */
    public function export(User $user): array
    {
        $this->audit->record('privacy.exported', $user, 'user', $user->id);

        return [
            'generated_at' => now()->toIso8601String(),
            'profile' => ['name' => $user->name, 'email' => $user->email, 'phone' => $user->getAttribute('phone_encrypted'), 'status' => $user->status, 'locale' => $user->locale, 'created_at' => $user->created_at?->toIso8601String(), 'last_login_at' => $user->last_login_at?->toIso8601String()],
            'roles' => $user->roles->map(fn ($role) => ['role' => $role->code, 'organization_id' => $role->getRelationValue('pivot')?->getAttribute('organization_id')])->values()->all(),
            'consents' => DB::table('consents')->where('user_id', $user->id)->orderBy('accepted_at')->get(['document', 'version', 'accepted_at', 'withdrawn_at', 'channel'])->all(),
            'enrollments' => DB::table('enrollments')->join('programs', 'programs.id', '=', 'enrollments.program_id')->where('enrollments.user_id', $user->id)
                ->get(['programs.name as program', 'enrollments.status', 'enrollments.progress_percent', 'enrollments.final_score', 'enrollments.enrolled_at', 'enrollments.completed_at'])->all(),
            'exam_attempts' => DB::table('exam_attempts')->join('assessments', 'assessments.id', '=', 'exam_attempts.assessment_id')->where('exam_attempts.user_id', $user->id)
                ->get(['assessments.title', 'exam_attempts.attempt_no', 'exam_attempts.status', 'exam_attempts.score', 'exam_attempts.passed', 'exam_attempts.started_at', 'exam_attempts.submitted_at'])->all(),
            'certificates' => DB::table('certificates')->where('user_id', $user->id)->get(['number', 'program_name', 'status', 'issued_at', 'valid_until'])->all(),
            'payments' => DB::table('payment_transactions')->where('user_id', $user->id)->get(['invoice_number', 'status', 'gross_amount', 'created_at', 'settled_at'])->all(),
            'notifications' => DB::table('notifications')->where('user_id', $user->id)->orderByDesc('created_at')->limit(200)->get(['category', 'title', 'created_at', 'read_at'])->all(),
            'sessions' => DB::table('user_sessions')->where('user_id', $user->id)->orderByDesc('created_at')->limit(50)->get(['device_label', 'ip', 'created_at', 'last_activity_at', 'revoked_at'])->all(),
        ];
    }

    public function requestDeletion(User $user, ?string $note): PrivacyRequest
    {
        if (PrivacyRequest::query()->where('user_id', $user->id)->where('kind', 'delete')->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['note' => 'Permintaan penghapusan Anda sudah tercatat dan sedang ditinjau.']);
        }
        $request = new PrivacyRequest;
        $request->forceFill(['user_id' => $user->id, 'kind' => 'delete', 'status' => 'pending', 'note' => $note !== null ? Str::limit($note, 500, '') : null])->save();
        $this->audit->record('privacy.delete_requested', $user, 'user', $user->id, null, $note);
        $this->notifier->send($user, 'security', 'Permintaan penghapusan akun diterima', 'Kami akan meninjau permintaan Anda paling lama 14 hari kerja. Anda dapat membatalkannya lewat halaman Privasi.', '/akun/privasi', email: true);

        return $request;
    }

    public function cancelRequest(User $user, PrivacyRequest $request): void
    {
        abort_unless($request->user_id === $user->id && $request->status === 'pending', 404);
        $request->forceFill(['status' => 'rejected', 'decision_note' => 'Dibatalkan oleh pengguna', 'processed_at' => now()])->save();
        $this->audit->record('privacy.delete_cancelled', $user, 'user', $user->id);
    }

    /** Admin menyetujui: akun dianonimkan dan semua sesi/token dicabut. */
    public function process(User $actor, PrivacyRequest $request, string $note): void
    {
        abort_unless($request->status === 'pending', 409);
        $user = $request->user;
        if ($user->isPlatformStaff()) {
            throw ValidationException::withMessages(['decision_note' => 'Akun staf platform tidak dapat dianonimkan lewat permintaan privasi; cabut perannya terlebih dahulu.']);
        }
        $this->notifier->send($user, 'security', 'Akun Anda dihapus', 'Sesuai permintaan Anda, data pribadi telah dianonimkan. Sertifikat yang pernah terbit tetap dapat diverifikasi.', null, email: true);
        DB::transaction(function () use ($actor, $request, $user, $note): void {
            $anonymousEmail = 'deleted-'.substr(hash('sha256', $user->id), 0, 16).'@anonymized.invalid';
            $user->forceFill([
                'name' => 'Pengguna Dihapus', 'email' => $anonymousEmail, 'phone_encrypted' => null, 'phone_bidx' => null,
                'status' => 'anonymized', 'deactivated_at' => now(), 'session_version' => $user->session_version + 1, 'remember_token' => Str::random(60),
                'password' => null, 'primary_organization_id' => null,
            ])->save();
            DB::table('role_user')->where('user_id', $user->id)->delete();
            DB::table('organization_members')->where('user_id', $user->id)->delete();
            DB::table('push_subscriptions')->where('user_id', $user->id)->delete();
            DB::table('notification_preferences')->where('user_id', $user->id)->delete();
            DB::table('ai_conversations')->where('user_id', $user->id)->delete();
            DB::table('notifications')->where('user_id', $user->id)->delete();
            DB::table('participant_profiles')->where('user_id', $user->id)->delete();
            $request->forceFill(['status' => 'processed', 'decision_note' => $note, 'processed_by' => $actor->id, 'processed_at' => now()])->save();
            $this->audit->record('privacy.anonymized', $actor, 'user', $user->id, null, $note);
        });
        $this->tokens->revokeAll($user);
        $this->devices->revokeOthers($user, null, 'account_anonymized');
        $this->events->log('account_anonymized', 'info', $user->id, ['by' => $actor->id]);
    }

    public function reject(User $actor, PrivacyRequest $request, string $note): void
    {
        abort_unless($request->status === 'pending', 409);
        $request->forceFill(['status' => 'rejected', 'decision_note' => $note, 'processed_by' => $actor->id, 'processed_at' => now()])->save();
        $this->audit->record('privacy.delete_rejected', $actor, 'user', $request->user_id, null, $note);
        $this->notifier->send($request->user, 'security', 'Permintaan penghapusan tidak dapat dipenuhi', $note, '/akun/privasi', email: true);
    }
}
