<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Access\Models\Role;
use App\Modules\Access\RoleCode;
use App\Modules\Identity\Notifications\ResetPasswordNotification;
use App\Modules\Organization\Models\Organization;
use App\Modules\Referral\Models\ReferralProfile;
use Database\Factories\UserFactory;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string|null $password
 * @property string $status
 * @property string|null $primary_organization_id
 * @property string|null $referred_by
 * @property Carbon|null $referred_at
 * @property int $session_version
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $password_changed_at
 * @property Carbon|null $last_login_at
 */
#[UseFactory(UserFactory::class)]
final class User extends Authenticatable implements CanResetPasswordContract
{
    use CanResetPassword;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUuids;
    use Notifiable;

    /**
     * Hanya field profil dasar. Field sensitif (status, peran, organisasi, password,
     * session_version) diisi service, tidak pernah dari input massal (SEC-INPUT-10).
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'email', 'locale', 'timezone'];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token', 'phone_encrypted', 'phone_bidx'];

    /** @var list<string>|null cache izin per request */
    private ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'referred_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'password' => 'hashed',
            'phone_encrypted' => 'encrypted',
            'session_version' => 'integer',
        ];
    }

    /**
     * Email selalu disimpan huruf kecil & tanpa spasi tepi (constraint DB users_email_lowercase).
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => self::normalizeEmail($value));
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withPivot(['id', 'organization_id', 'granted_by', 'granted_at', 'expires_at'])
            ->where(fn ($query) => $query->whereNull('role_user.expires_at')->orWhere('role_user.expires_at', '>', now()));
    }

    /** @return BelongsTo<Organization, $this> */
    public function primaryOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'primary_organization_id');
    }

    /** @return BelongsTo<User, $this> */
    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    /** @return HasOne<ReferralProfile, $this> */
    public function referralProfile(): HasOne
    {
        return $this->hasOne(ReferralProfile::class);
    }

    /** @return HasMany<MfaMethod, $this> */
    public function mfaMethods(): HasMany
    {
        return $this->hasMany(MfaMethod::class);
    }

    /** @return HasMany<RecoveryCode, $this> */
    public function recoveryCodes(): HasMany
    {
        return $this->hasMany(RecoveryCode::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** @return list<RoleCode> */
    public function roleCodes(): array
    {
        return array_values($this->roles->map(fn (Role $role) => RoleCode::from($role->code))->unique()->all());
    }

    public function hasRole(RoleCode $role, ?string $organizationId = null): bool
    {
        return $this->roles->contains(function (Role $item) use ($role, $organizationId): bool {
            if ($item->code !== $role->value) {
                return false;
            }

            return $organizationId === null || $item->getRelationValue('pivot')?->getAttribute('organization_id') === $organizationId;
        });
    }

    public function isPlatformStaff(): bool
    {
        return $this->roles->contains(fn (Role $role): bool => $role->is_platform);
    }

    public function requiresMfa(): bool
    {
        foreach ($this->roleCodes() as $role) {
            if ($role->requiresMfa()) {
                return true;
            }
        }

        return false;
    }

    public function totpMethod(): ?MfaMethod
    {
        return $this->mfaMethods()->where('type', 'totp')->whereNotNull('confirmed_at')->first();
    }

    public function hasConfirmedMfa(): bool
    {
        return $this->mfaMethods()->whereNotNull('confirmed_at')->exists();
    }

    /**
     * Organisasi dalam lingkup administrasi tenant pengguna: organisasi tempat ia memegang
     * peran Admin Organisasi. Baris milik pengguna sendiri tetap terlihat lewat
     * `user_id = app_user_id()` pada kebijakan RLS, sehingga peserta/trainer tidak melihat
     * data anggota lain dari organisasi yang sama.
     *
     * @return list<string>
     */
    public function tenantOrganizationIds(): array
    {
        /** @var list<string> */
        return array_values($this->roles
            ->filter(fn (Role $role): bool => in_array($role->code, [RoleCode::OrgAdmin->value, RoleCode::Supervisor->value], true))
            ->map(fn (Role $role) => $role->getRelationValue('pivot')?->getAttribute('organization_id'))
            ->filter()
            ->unique()
            ->values()
            ->all());
    }

    /**
     * Kelas yang diampu pengguna sebagai trainer (lingkup RLS `app.trainer_class_ids`).
     *
     * @return list<string>
     */
    public function trainerClassIds(): array
    {
        if (! $this->hasRole(RoleCode::Trainer)) {
            return [];
        }

        /** @var list<string> */
        return DB::table('class_trainers')->where('user_id', $this->id)->pluck('course_class_id')->all();
    }

    /**
     * Kode izin efektif pengguna. Di-cache per instance (per request) sehingga perubahan
     * peran berlaku pada request berikutnya (SEC-AUTHZ-18).
     *
     * @return list<string>
     */
    public function permissionCodes(): array
    {
        if ($this->permissionCache === null) {
            $roleIds = $this->roles->pluck('id')->all();
            /** @var list<string> $codes */
            $codes = $roleIds === [] ? [] : DB::table('permission_role')
                ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                ->whereIn('permission_role.role_id', $roleIds)
                ->distinct()
                ->pluck('permissions.code')
                ->all();
            $this->permissionCache = $codes;
        }

        return $this->permissionCache;
    }

    public function hasPermission(string $code): bool
    {
        return $this->isActive() && in_array($code, $this->permissionCodes(), true);
    }

    public function flushPermissionCache(): void
    {
        $this->permissionCache = null;
        $this->unsetRelation('roles');
    }

    /** Workspace default setelah login (peran dengan hak tertinggi). */
    public function defaultWorkspace(): ?string
    {
        $order = [RoleCode::SuperAdmin, RoleCode::AcademicAdmin, RoleCode::FinanceAdmin, RoleCode::SupportAdmin, RoleCode::OrgAdmin, RoleCode::Supervisor, RoleCode::Trainer, RoleCode::Participant];
        $codes = $this->roleCodes();
        foreach ($order as $role) {
            if (in_array($role, $codes, true)) {
                return $role->workspace();
            }
        }

        return null;
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
