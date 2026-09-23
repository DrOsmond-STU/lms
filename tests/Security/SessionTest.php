<?php

declare(strict_types=1);

use App\Modules\Access\RoleCode;
use App\Modules\Access\Services\RoleAssigner;
use App\Support\Security\Totp;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => cache()->flush());

it('revokes all sessions when the session version changes', function () {
    $user = makeUser();
    loginAs($user);
    $this->get('/peserta')->assertOk();

    DB::table('users')->where('id', $user->id)->increment('session_version');

    nextRequest();
    $this->get('/peserta')->assertRedirect(route('login'));
    $this->assertGuest();
})->group('SEC-AUTH-19');

it('logs out a deactivated account on its next request', function () {
    $user = makeUser();
    loginAs($user);

    DB::table('users')->where('id', $user->id)->update(['status' => 'deactivated']);

    nextRequest();
    $this->get('/peserta')->assertRedirect(route('login'));
    $this->assertGuest();
})->group('SEC-AUTH-29');

it('enforces the idle timeout', function () {
    $user = makeUser();
    loginAs($user);

    $this->travel(121)->minutes();

    $this->get('/peserta')->assertRedirect(route('login'));
})->group('SEC-AUTH-18');

it('applies a revoked role immediately', function () {
    $organization = makeOrganization();
    $user = makeUser(RoleCode::Participant, $organization);
    asSystem(fn () => app(RoleAssigner::class)->assign($user, RoleCode::OrgAdmin, $organization->id, null));
    $secret = enrollTotp($user->fresh());
    loginAs($user->fresh(), $secret);
    $this->get('/organisasi')->assertOk();

    asSystem(fn () => app(RoleAssigner::class)->revoke($user->fresh(), RoleCode::OrgAdmin, $organization->id, null, 'uji'));

    // Versi sesi berubah → sesi dicabut; setelah login ulang area organisasi tidak ada lagi.
    nextRequest();
    $this->get('/organisasi')->assertRedirect(route('login'));
    loginAs($user->fresh(), null);
    $this->post('/masuk/mfa', ['code' => Totp::codeAt($secret, Totp::currentStep() + 1)]);
    nextRequest();
    $this->get('/dasbor')->assertRedirect(route('participant.dashboard'));
    $this->get('/organisasi')->assertNotFound();
})->group('SEC-AUTHZ-18');
