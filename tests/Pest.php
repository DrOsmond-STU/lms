<?php

declare(strict_types=1);

use App\Modules\Access\RoleCode;
use App\Modules\Access\Services\RoleAssigner;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\MfaService;
use App\Modules\Organization\Models\Organization;
use App\Support\Security\Totp;
use App\Support\Tenancy\TenantContext;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(DatabaseTransactions::class)->in('Feature', 'Security');
pest()->extend(TestCase::class)->in('Unit', 'Arch');

/*
| Helper persona (data sintetis — docs/10 §4).
*/

function asSystem(Closure $callback): mixed
{
    $tenant = app(TenantContext::class);
    $tenant->applySystem();
    try {
        return $callback();
    } finally {
        $tenant->clear();
    }
}

function makeOrganization(string $type = 'institution'): Organization
{
    return asSystem(fn () => Organization::factory()->state(['type' => $type])->create());
}

function makeUser(RoleCode $role = RoleCode::Participant, ?Organization $organization = null, array $attributes = []): User
{
    return asSystem(function () use ($role, $organization, $attributes): User {
        $organization ??= $role->isPlatform() ? null : Organization::factory()->create();
        $user = User::factory()->create(array_merge(['primary_organization_id' => $organization?->id], $attributes));
        app(RoleAssigner::class)->assign($user, $role, $organization?->id, null);

        if ($organization !== null) {
            DB::table('organization_members')->insert([
                'id' => (string) Str::uuid7(), 'organization_id' => $organization->id, 'user_id' => $user->id,
                'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $user->fresh();
    });
}

/** Mendaftarkan TOTP terkonfirmasi & mengembalikan secret-nya. */
function enrollTotp(User $user): string
{
    $secret = Totp::generateSecret();
    asSystem(fn () => app(MfaService::class)->confirmTotp($user, $secret, Totp::codeAt($secret, Totp::currentStep())));
    // Reset langkah terakhir agar kode saat ini dapat dipakai lagi pada uji login.
    DB::table('user_mfa_methods')->where('user_id', $user->id)->update(['last_totp_step' => Totp::currentStep() - 2]);

    return $secret;
}

/** Login penuh melalui HTTP (dengan MFA bila terdaftar). */
function loginAs(User $user, ?string $totpSecret = null): void
{
    test()->post('/masuk', ['email' => $user->email, 'password' => UserFactory::PASSWORD]);
    if ($totpSecret !== null) {
        test()->post('/masuk/mfa', ['code' => Totp::codeAt($totpSecret, Totp::currentStep())]);
    }
}

/**
 * Mensimulasikan request baru (seperti PHP-FPM): lupakan pengguna yang di-cache guard
 * sehingga data akun dimuat ulang dari basis data.
 */
function nextRequest(): void
{
    app('auth')->forgetGuards();
}
