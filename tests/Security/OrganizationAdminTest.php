<?php

declare(strict_types=1);

use App\Modules\Access\RoleCode;
use App\Modules\Organization\Models\Organization;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => cache()->flush());

it('creates an organization with an uppercase, unique, immutable code', function () {
    signIn(RoleCode::AcademicAdmin);

    $this->post('/admin/organisasi', ['name' => 'Politeknik Uji Coba', 'code' => 'pucb', 'type' => 'institution', 'accreditation' => 'Unggul'])
        ->assertRedirect();
    $organization = Organization::query()->where('code', 'PUCB')->firstOrFail();

    $this->post('/admin/organisasi', ['name' => 'Duplikat', 'code' => 'PUCB', 'type' => 'corporate'])->assertSessionHasErrors('code');

    $this->put("/admin/organisasi/{$organization->id}", ['name' => 'Politeknik Uji Coba Baru', 'code' => 'HACK', 'type' => 'corporate'])->assertRedirect();
    $organization->refresh();
    expect($organization->name)->toBe('Politeknik Uji Coba Baru')
        ->and($organization->code)->toBe('PUCB')
        ->and($organization->type)->toBe('institution')
        ->and(DB::table('audit_logs')->where('action', 'organization.created')->where('subject_id', $organization->id)->exists())->toBeTrue();
})->group('FR-ORG-001');

it('rejects public email providers as organization domains', function () {
    signIn(RoleCode::AcademicAdmin);
    $organization = makeOrganization();

    $this->post("/admin/organisasi/{$organization->id}/domain", ['domain' => 'Gmail.com'])->assertSessionHasErrors('domain');
    $this->post("/admin/organisasi/{$organization->id}/domain", ['domain' => 'bukan domain'])->assertSessionHasErrors('domain');
    $this->post("/admin/organisasi/{$organization->id}/domain", ['domain' => 'Kampus-Resmi.AC.ID'])->assertSessionHasNoErrors();

    expect(DB::table('organization_domains')->where('organization_id', $organization->id)->pluck('domain')->all())->toBe(['kampus-resmi.ac.id']);
})->group('FR-AUTH-003');

it('archives with a reason after re-authentication', function () {
    signIn(RoleCode::SuperAdmin);
    $organization = makeOrganization();

    $this->post("/admin/organisasi/{$organization->id}/status", ['reason' => 'kerja sama berakhir'])->assertRedirect(route('password.confirm'));
    confirmAccess();
    $this->post("/admin/organisasi/{$organization->id}/status", ['reason' => ''])->assertSessionHasErrors('reason');
    $this->post("/admin/organisasi/{$organization->id}/status", ['reason' => 'kerja sama berakhir'])->assertRedirect();

    expect($organization->fresh()->status)->toBe('inactive')
        ->and(DB::table('audit_logs')->where('action', 'organization.archived')->value('reason'))->toBe('kerja sama berakhir');
})->group('FR-ORG-001', 'FR-AUTH-014');

it('denies organization management to roles without the permission', function () {
    signIn(RoleCode::FinanceAdmin);

    $this->get('/admin/organisasi')->assertForbidden();
    $this->post('/admin/organisasi', ['name' => 'X', 'code' => 'XX', 'type' => 'corporate'])->assertForbidden();
})->group('SEC-AUTHZ-01');

it('treats search wildcards literally', function () {
    signIn(RoleCode::AcademicAdmin);
    makeOrganization();

    $this->get('/admin/organisasi?q=%25')->assertOk()->assertSee('Belum ada organisasi yang cocok');
})->group('SEC-INPUT');
