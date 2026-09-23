<?php

declare(strict_types=1);

use App\Modules\Access\RoleCode;
use App\Modules\Identity\Services\MfaService;
use App\Support\Security\Totp;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => cache()->flush());

it('forces privileged roles to enroll MFA before accessing anything', function (RoleCode $role, string $area) {
    $user = makeUser($role);
    loginAs($user);

    $this->get($area)->assertRedirect(route('mfa.setup'));
    $this->get('/dasbor')->assertRedirect(route('mfa.setup'));
    $this->get('/akun/mfa/aktifkan')->assertOk()->assertSee('Autentikasi Dua Faktor');
})->with([
    [RoleCode::SuperAdmin, '/admin'],
    [RoleCode::AcademicAdmin, '/admin'],
    [RoleCode::FinanceAdmin, '/admin'],
    [RoleCode::OrgAdmin, '/organisasi'],
    [RoleCode::Trainer, '/trainer'],
])->group('SEC-AUTH-10');

it('enrolls TOTP with password + first code and shows recovery codes once', function () {
    $user = makeUser(RoleCode::SuperAdmin);
    loginAs($user);
    $this->get('/akun/mfa/aktifkan');
    $secret = session('mfa.setup_secret');

    $this->post('/akun/mfa/aktifkan', ['password' => 'salah', 'code' => Totp::codeAt($secret, Totp::currentStep())])
        ->assertSessionHasErrors('password');

    $this->post('/akun/mfa/aktifkan', ['password' => UserFactory::PASSWORD, 'code' => Totp::codeAt($secret, Totp::currentStep())])
        ->assertRedirect(route('mfa.recovery-codes'));
    $this->get('/akun/mfa/kode-pemulihan')->assertOk()->assertSee('hanya ditampilkan sekali', false);
    $this->get('/akun/mfa/kode-pemulihan')->assertRedirect(route('dashboard')); // tidak dapat dilihat lagi

    expect(DB::table('mfa_recovery_codes')->where('user_id', $user->id)->count())->toBe(10)
        ->and(DB::table('user_mfa_methods')->where('user_id', $user->id)->value('secret_encrypted'))->not->toContain($secret);
    $this->get('/admin')->assertOk();
})->group('SEC-AUTH-13', 'SEC-AUTH-14');

it('does not authenticate before the second factor and cannot skip the MFA step', function () {
    $user = makeUser(RoleCode::SuperAdmin);
    $secret = enrollTotp($user);

    $this->post('/masuk', ['email' => $user->email, 'password' => UserFactory::PASSWORD])
        ->assertRedirect(route('mfa.challenge'));
    $this->assertGuest();
    $this->get('/admin')->assertRedirect(route('login'));

    // Kembali ke URL yang dituju sebelum login (intended).
    $this->post('/masuk/mfa', ['code' => Totp::codeAt($secret, Totp::currentStep())])->assertRedirect('/admin');
    $this->assertAuthenticatedAs($user);
    $this->get('/admin')->assertOk();
})->group('SEC-AUTH-36');

it('rejects a replayed TOTP code', function () {
    $user = makeUser(RoleCode::Trainer);
    $secret = enrollTotp($user);
    $code = Totp::codeAt($secret, Totp::currentStep());

    loginAs($user, $secret);
    $this->assertAuthenticated();
    $this->post('/keluar');

    $this->post('/masuk', ['email' => $user->email, 'password' => UserFactory::PASSWORD]);
    $this->post('/masuk/mfa', ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest();
})->group('SEC-AUTH-13');

it('accepts each recovery code only once', function () {
    $user = makeUser(RoleCode::Trainer);
    enrollTotp($user);
    $codes = asSystem(fn () => app(MfaService::class)->regenerateRecoveryCodes($user));

    $this->post('/masuk', ['email' => $user->email, 'password' => UserFactory::PASSWORD]);
    $this->post('/masuk/mfa', ['recovery_code' => $codes[0]])->assertRedirect(route('dashboard'));
    $this->post('/keluar');

    $this->post('/masuk', ['email' => $user->email, 'password' => UserFactory::PASSWORD]);
    $this->post('/masuk/mfa', ['recovery_code' => $codes[0]])->assertSessionHasErrors('code');
    $this->assertGuest();
})->group('SEC-AUTH-14');

it('sends the user back to login after too many wrong codes', function () {
    $user = makeUser(RoleCode::Trainer);
    enrollTotp($user);
    $this->post('/masuk', ['email' => $user->email, 'password' => UserFactory::PASSWORD]);

    foreach (range(1, 4) as $_) {
        $this->post('/masuk/mfa', ['code' => '000000'])->assertSessionHasErrors('code');
    }
    $this->post('/masuk/mfa', ['code' => '000000'])->assertRedirect(route('login'));
    $this->get('/masuk/mfa')->assertRedirect(route('login'));
})->group('SEC-AUTH-13');

it('expires a pending MFA login after five minutes', function () {
    $user = makeUser(RoleCode::Trainer);
    $secret = enrollTotp($user);
    $this->post('/masuk', ['email' => $user->email, 'password' => UserFactory::PASSWORD]);

    $this->travel(6)->minutes();

    $this->post('/masuk/mfa', ['code' => Totp::codeAt($secret, Totp::currentStep())])->assertRedirect(route('login'));
    $this->assertGuest();
})->group('SEC-AUTH-36');
