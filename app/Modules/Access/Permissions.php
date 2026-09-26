<?php

declare(strict_types=1);

namespace App\Modules\Access;

/**
 * Katalog izin & pemetaan peran → izin (docs/07-rbac-dan-multi-tenant.md §4–§5).
 *
 * Sumber kebenaran tunggal: diseed ke DB oleh `AccessSynchronizer` dan tidak dapat diubah
 * dari UI (keamanan/03 SEC-AUTHZ-23). Perubahan wajib lewat PR yang ditinjau Security Lead.
 * Batasan scope (milik sendiri / organisasi / kelas diampu) ditegakkan oleh Policy & RLS,
 * bukan oleh daftar ini.
 */
final class Permissions
{
    /** @var array<string, list<string>> resource => actions */
    public const CATALOG = [
        'user' => ['view_any', 'view', 'create', 'update', 'deactivate', 'reset_mfa', 'assign_role', 'export'],
        'organization' => ['view_any', 'view', 'create', 'update', 'archive', 'manage_members', 'access_review'],
        'program' => ['view_any', 'view', 'create', 'update', 'submit_review', 'publish', 'archive'],
        'course_class' => ['view_any', 'view', 'create', 'update', 'archive', 'assign_trainer'],
        'content' => ['view', 'create', 'update', 'delete', 'publish'],
        'assessment' => ['view', 'create', 'update', 'delete', 'view_answer_key', 'grade_manual', 'reset_attempt', 'attempt'],
        'enrollment' => ['view_any', 'view', 'create', 'bulk_create', 'cancel', 'override_status'],
        'assignment' => ['view', 'create', 'update', 'delete'],
        'submission' => ['view_any', 'view', 'create', 'review'],
        'attendance' => ['view_any', 'manage_session', 'record_manual', 'check_in'],
        'live_session' => ['view', 'create', 'update', 'delete'],
        'certificate' => ['view_any', 'view', 'approve', 'reject', 'revoke', 'reissue', 'download'],
        'certificate_template' => ['view_any', 'create', 'update', 'activate'],
        'payment' => ['view_any', 'view', 'refund_request', 'refund_approve', 'mark_paid_manual', 'reconcile', 'export'],
        'coupon' => ['view_any', 'create', 'update', 'deactivate'],
        'discussion' => ['view', 'post', 'moderate', 'report'],
        'gamification' => ['view_leaderboard', 'manage'],
        'notification_setting' => ['view', 'update'],
        'report' => ['view_platform', 'view_organization', 'view_class', 'export'],
        'referral' => ['view', 'view_any', 'pay'],
        'cms' => ['view', 'update', 'publish'],
        'privacy_request' => ['view_any', 'process', 'create'],
        'api_key' => ['view_any', 'create', 'revoke'],
        'integration' => ['view', 'update'],
        'system_setting' => ['view', 'update'],
        'audit_log' => ['view', 'export'],
        'calendar' => ['view', 'manage'],
    ];

    /**
     * Pemetaan peran → izin. '*' pada resource berarti semua aksi resource tersebut.
     *
     * @var array<string, list<string>>
     */
    public const ROLE_MAP = [
        'super_admin' => [
            'user.*', 'organization.*', 'program.*', 'course_class.*', 'content.*',
            'assessment.view', 'assessment.create', 'assessment.update', 'assessment.delete',
            'assessment.view_answer_key', 'assessment.grade_manual', 'assessment.reset_attempt',
            'enrollment.*', 'assignment.*', 'submission.view_any', 'submission.view', 'submission.review',
            'attendance.view_any', 'attendance.manage_session', 'attendance.record_manual',
            'live_session.*', 'certificate.view_any', 'certificate.view', 'certificate.approve',
            'certificate.reject', 'certificate.revoke', 'certificate.reissue', 'certificate.download',
            'certificate_template.*', 'payment.*', 'coupon.*', 'discussion.view', 'discussion.moderate',
            'gamification.*', 'notification_setting.*', 'report.*', 'cms.*',
            'privacy_request.view_any', 'privacy_request.process', 'api_key.*', 'integration.*',
            'system_setting.*', 'audit_log.*', 'referral.*', 'calendar.*',
        ],
        'academic_admin' => [
            'user.view_any', 'user.view', 'user.create', 'user.update', 'user.deactivate', 'user.assign_role', 'user.export',
            'organization.*', 'program.*', 'course_class.*', 'content.*',
            'assessment.view', 'assessment.create', 'assessment.update', 'assessment.delete',
            'assessment.view_answer_key', 'assessment.grade_manual', 'assessment.reset_attempt',
            'enrollment.*', 'assignment.*', 'submission.view_any', 'submission.view', 'submission.review',
            'attendance.view_any', 'attendance.manage_session', 'attendance.record_manual', 'live_session.*',
            'certificate.view_any', 'certificate.view', 'certificate.approve', 'certificate.reject',
            'certificate.revoke', 'certificate.reissue', 'certificate.download', 'certificate_template.*',
            'payment.view_any', 'payment.view', 'discussion.view', 'discussion.moderate', 'gamification.*',
            'report.view_platform', 'report.export', 'cms.*', 'referral.view_any',
            'privacy_request.view_any', 'privacy_request.process', 'audit_log.view', 'calendar.*',
        ],
        'finance_admin' => [
            'program.view_any', 'program.view', 'payment.*', 'coupon.*', 'report.view_platform', 'report.export', 'audit_log.view', 'referral.*',
        ],
        'support_admin' => [
            'user.view_any', 'user.view', 'user.reset_mfa', 'enrollment.view_any', 'enrollment.view', 'payment.view',
        ],
        'org_admin' => [
            'user.view_any', 'user.view', 'user.create', 'user.update', 'user.export',
            'organization.view', 'organization.update', 'organization.manage_members', 'organization.access_review',
            'program.view_any', 'program.view', 'course_class.view_any', 'course_class.view',
            'enrollment.view_any', 'enrollment.view', 'enrollment.create', 'enrollment.bulk_create',
            'attendance.view_any', 'certificate.view_any', 'certificate.view', 'certificate.download',
            'payment.view_any', 'payment.view', 'gamification.view_leaderboard',
            'report.view_organization', 'report.export', 'audit_log.view', 'calendar.view',
        ],
        'trainer' => [
            'program.view_any', 'program.view', 'course_class.view_any', 'course_class.view', 'course_class.update',
            'content.*', 'assessment.view', 'assessment.create', 'assessment.update', 'assessment.delete',
            'assessment.view_answer_key', 'assessment.grade_manual', 'assessment.reset_attempt',
            'enrollment.view_any', 'enrollment.view', 'assignment.*', 'submission.view_any', 'submission.view', 'submission.review',
            'attendance.view_any', 'attendance.manage_session', 'attendance.record_manual', 'live_session.*',
            'discussion.view', 'discussion.post', 'discussion.moderate', 'gamification.view_leaderboard',
            'report.view_class', 'report.export', 'calendar.view',
        ],
        'participant' => [
            'program.view_any', 'program.view', 'course_class.view', 'content.view', 'assessment.attempt',
            'enrollment.view', 'enrollment.create', 'enrollment.cancel', 'submission.view', 'submission.create',
            'attendance.check_in', 'live_session.view', 'certificate.view', 'certificate.download',
            'payment.view', 'payment.refund_request', 'discussion.view', 'discussion.post', 'discussion.report',
            'gamification.view_leaderboard', 'notification_setting.view', 'notification_setting.update',
            'privacy_request.create', 'referral.view', 'calendar.view',
        ],
    ];

    /** @return list<string> seluruh kode izin */
    public static function all(): array
    {
        $codes = [];
        foreach (self::CATALOG as $resource => $actions) {
            foreach ($actions as $action) {
                $codes[] = $resource.'.'.$action;
            }
        }

        return $codes;
    }

    public static function exists(string $code): bool
    {
        return in_array($code, self::all(), true);
    }

    /** @return list<string> izin terurai untuk satu peran */
    public static function forRole(string $role): array
    {
        $expanded = [];
        foreach (self::ROLE_MAP[$role] ?? [] as $entry) {
            [$resource, $action] = explode('.', $entry, 2);
            $actions = $action === '*' ? (self::CATALOG[$resource] ?? []) : [$action];
            foreach ($actions as $item) {
                $expanded[] = $resource.'.'.$item;
            }
        }

        return array_values(array_unique($expanded));
    }
}
