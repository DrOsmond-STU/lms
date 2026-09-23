<?php

declare(strict_types=1);

use App\Modules\Access\RoleCode;

beforeEach(fn () => cache()->flush());

it('denies other areas as 404 for every role', function (RoleCode $role, string $own) {
    $user = makeUser($role);
    $secret = $role->requiresMfa() ? enrollTotp($user) : null;
    loginAs($user, $secret);

    foreach (['/peserta', '/trainer', '/organisasi', '/admin'] as $area) {
        $response = $this->get($area);
        $area === $own ? $response->assertOk() : $response->assertNotFound();
    }
})->with([
    [RoleCode::Participant, '/peserta'],
    [RoleCode::Trainer, '/trainer'],
    [RoleCode::OrgAdmin, '/organisasi'],
    [RoleCode::SuperAdmin, '/admin'],
    [RoleCode::AcademicAdmin, '/admin'],
    [RoleCode::FinanceAdmin, '/admin'],
    [RoleCode::SupportAdmin, '/admin'],
])->group('SEC-AUTHZ-01', 'SEC-AUTHZ-03');

it('redirects guests to login for every protected area', function (string $area) {
    $this->get($area)->assertRedirect(route('login'));
})->with(['/peserta', '/trainer', '/organisasi', '/admin', '/dasbor', '/akun/mfa/aktifkan', '/konfirmasi-akses'])
    ->group('SEC-AUTHZ-01');

it('grants permissions through the gate without a super-admin bypass', function () {
    $admin = makeUser(RoleCode::SuperAdmin);
    $trainer = makeUser(RoleCode::Trainer);

    expect($admin->can('certificate.approve'))->toBeTrue()
        ->and($admin->can('assessment.attempt'))->toBeFalse()
        ->and($trainer->can('certificate.approve'))->toBeFalse()
        ->and($trainer->can('submission.review'))->toBeTrue();
})->group('SEC-AUTHZ-19');
