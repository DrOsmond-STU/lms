<?php

declare(strict_types=1);

use App\Modules\Access\RoleCode;
use App\Modules\Organization\Models\OrganizationMember;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('runs the application as a database role that cannot bypass RLS', function () {
    $role = DB::selectOne('select current_user as name, rolbypassrls, rolsuper from pg_roles where rolname = current_user');

    expect($role->name)->toBe('stu_app')
        ->and($role->rolbypassrls)->toBeFalse()
        ->and($role->rolsuper)->toBeFalse();
})->group('SEC-AUTHZ-11');

it('isolates organization members per tenant at the database level', function () {
    $orgA = makeOrganization();
    $orgB = makeOrganization('corporate');
    $adminA = makeUser(RoleCode::OrgAdmin, $orgA);
    makeUser(RoleCode::Participant, $orgA);
    makeUser(RoleCode::Participant, $orgB);
    $tenant = app(TenantContext::class);

    $tenant->clear();
    expect(OrganizationMember::query()->count())->toBe(0); // tanpa konteks → tidak ada baris

    $tenant->applyFor($adminA);
    $visible = OrganizationMember::query()->pluck('organization_id')->unique()->values()->all();
    expect($visible)->toBe([$orgA->id]);

    $tenant->applySystem();
    expect(OrganizationMember::query()->whereIn('organization_id', [$orgA->id, $orgB->id])->count())->toBe(3);
})->group('SEC-AUTHZ-11', 'SEC-AUTHZ-10');

it('rejects writes into another tenant even with a crafted query', function () {
    $orgA = makeOrganization();
    $orgB = makeOrganization();
    $adminA = makeUser(RoleCode::OrgAdmin, $orgA);
    $outsider = makeUser(RoleCode::Participant, $orgB);

    app(TenantContext::class)->applyFor($adminA);

    expect(fn () => DB::transaction(fn () => DB::table('organization_members')->insert([
        'id' => (string) Str::uuid7(), 'organization_id' => $orgB->id, 'user_id' => $outsider->id,
        'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ])))->toThrow(QueryException::class, 'row-level security');

    // Update baris tenant lain tidak menyentuh apa pun.
    expect(DB::table('organization_members')->where('organization_id', $orgB->id)->update(['status' => 'removed']))->toBe(0);
})->group('SEC-AUTHZ-11');

it('clears the tenant context at the end of each web request', function () {
    $user = makeUser();
    loginAs($user);
    $this->get('/peserta')->assertOk();

    $setting = DB::selectOne("select coalesce(current_setting('app.user_id', true), '') as v")->v;
    expect($setting)->toBe('');
})->group('SEC-AUTHZ-20');
