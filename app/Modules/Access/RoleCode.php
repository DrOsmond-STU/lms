<?php

declare(strict_types=1);

namespace App\Modules\Access;

/**
 * Peran sistem (docs/07-rbac-dan-multi-tenant.md §2).
 */
enum RoleCode: string
{
    case SuperAdmin = 'super_admin';
    case AcademicAdmin = 'academic_admin';
    case FinanceAdmin = 'finance_admin';
    case SupportAdmin = 'support_admin';
    case OrgAdmin = 'org_admin';
    case Supervisor = 'supervisor';
    case Trainer = 'trainer';
    case Participant = 'participant';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::AcademicAdmin => 'Admin Akademik',
            self::FinanceAdmin => 'Admin Keuangan',
            self::SupportAdmin => 'Admin Layanan',
            self::OrgAdmin => 'Admin Organisasi',
            self::Supervisor => 'Supervisor',
            self::Trainer => 'Trainer',
            self::Participant => 'Peserta',
        };
    }

    /** Peran platform tidak terikat organisasi (role_user.organization_id NULL). */
    public function isPlatform(): bool
    {
        return in_array($this, [self::SuperAdmin, self::AcademicAdmin, self::FinanceAdmin, self::SupportAdmin], true);
    }

    /**
     * Peran yang wajib terikat organisasi. Trainer & peserta boleh tanpa organisasi
     * (trainer internal STU, peserta umum yang mendaftar mandiri) — docs/07 §2.
     * Supervisor = pemantau organisasi (hanya baca: progres, nilai, presensi, laporan).
     */
    public function requiresOrganization(): bool
    {
        return in_array($this, [self::OrgAdmin, self::Supervisor], true);
    }

    public function requiresMfa(): bool
    {
        return in_array($this->value, config('security.mfa_required_roles', []), true);
    }

    /** Area/workspace UI yang dituju setelah login. */
    public function workspace(): string
    {
        return match ($this) {
            self::Participant => 'participant',
            self::Trainer => 'trainer',
            self::OrgAdmin, self::Supervisor => 'organization',
            default => 'admin',
        };
    }
}
