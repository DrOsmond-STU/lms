<?php

declare(strict_types=1);

use App\Modules\Access\Models\ApprovalRequest;
use App\Modules\Access\RoleCode;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Notification\Mail\PlainNotificationMail;
use App\Modules\Notification\Services\Notifier;
use App\Modules\Settings\Services\SystemSettings;
use App\Support\Security\Totp;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(fn () => cache()->flush());

it('renders the dashboard of every workspace', function (RoleCode $role, string $path) {
    signIn($role);
    $this->get($path)->assertOk();
})->with([
    [RoleCode::Participant, '/peserta'],
    [RoleCode::Trainer, '/trainer'],
    [RoleCode::OrgAdmin, '/organisasi'],
    [RoleCode::SuperAdmin, '/admin'],
    [RoleCode::FinanceAdmin, '/admin'],
])->group('FR-RPT-001', 'FR-RPT-002', 'FR-RPT-003');

it('lists sessions and revokes another device immediately', function () {
    $user = makeUser(RoleCode::Participant);
    loginAs($user);
    nextRequest();
    $firstSession = session()->all();
    $this->flushSession();
    nextRequest();

    loginAs($user);
    nextRequest();
    $this->get('/akun/keamanan/sesi')->assertOk()->assertSee('Sesi ini');
    $other = DB::table('user_sessions')->where('user_id', $user->id)->orderBy('created_at')->value('id');
    $this->post(route('account.sessions.revoke', $other))->assertRedirect();

    $this->flushSession();
    nextRequest();
    $this->withSession($firstSession)->get('/peserta')->assertRedirect(route('login'));
})->group('FR-AUTH-009');

it('emails a security notice when logging in from a new device', function () {
    Notification::fake();
    Mail::fake();
    $user = makeUser(RoleCode::Participant);
    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0'])->post('/masuk', ['email' => $user->email, 'password' => UserFactory::PASSWORD]);
    $this->post('/keluar');
    nextRequest();
    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Safari/604.1'])->post('/masuk', ['email' => $user->email, 'password' => UserFactory::PASSWORD]);

    expect(DB::table('notifications')->where('user_id', $user->id)->where('category', 'security')->where('title', 'Login dari perangkat baru')->count())->toBe(1);
    Mail::assertQueued(PlainNotificationMail::class);
})->group('FR-AUTH-010');

it('redirects to the consent screen after a legal version change', function () {
    $user = makeUser(RoleCode::Participant);
    config(['legal.privacy_version' => '2099-01']);
    loginAs($user);
    nextRequest();

    $this->get('/peserta')->assertRedirect(route('consent.show'));
    $this->post(route('consent.store'), ['accept_terms' => '1', 'accept_privacy' => '1'])->assertRedirect();
    nextRequest();
    $this->get('/peserta')->assertOk();
    expect(DB::table('consents')->where('user_id', $user->id)->where('document', 'privacy')->where('version', '2099-01')->exists())->toBeTrue();
})->group('FR-CMS-003', 'FR-PRV-001');

it('keeps security settings within safe bounds and audits changes', function () {
    signIn(RoleCode::SuperAdmin);
    confirmAccess();
    $base = ['branding__app_display_name' => 'STU LMS', 'security__session_idle_privileged' => 30, 'security__session_idle_participant' => 120,
        'security__password_min_privileged' => 12, 'security__password_min_participant' => 8, 'security__registration_enabled' => '1'];

    $this->put(route('admin.settings.update'), array_merge($base, ['security__password_min_participant' => 4]))->assertSessionHasErrors('security__password_min_participant');
    $this->put(route('admin.settings.update'), array_merge($base, ['security__session_idle_privileged' => 90]))->assertSessionHasErrors('security__session_idle_privileged');
    $this->put(route('admin.settings.update'), array_merge($base, ['security__password_min_participant' => 10]))->assertSessionHasNoErrors();

    SystemSettings::applyToConfig();
    expect(config('security.password.min_participant'))->toBe(10)
        ->and(DB::table('audit_logs')->where('action', 'system_setting.updated')->exists())->toBeTrue();
})->group('FR-SET-002', 'FR-SET-005');

it('shows each user only their own notifications', function () {
    $owner = makeUser(RoleCode::Participant);
    app(Notifier::class)->send($owner, 'system', 'Rahasia pemilik', 'Isi pribadi', '/peserta');
    $id = DB::table('notifications')->where('user_id', $owner->id)->value('id');

    signIn(RoleCode::Participant);
    $this->get('/notifikasi')->assertOk()->assertDontSee('Rahasia pemilik');
    $this->post(route('notifications.open', $id))->assertNotFound();
})->group('FR-NTF-001');

it('rejects notifications pointing outside the application', function () {
    $user = makeUser();
    expect(fn () => app(Notifier::class)->send($user, 'system', 'x', 'y', 'https://evil.example'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(Notifier::class)->send($user, 'system', 'x', 'y', '//evil.example'))->toThrow(InvalidArgumentException::class);
})->group('FR-NTF-005');

it('lets an organization admin approve only their own organization members', function () {
    $org = makeOrganization();
    $otherOrg = makeOrganization();
    $applicant = makeUser(RoleCode::Participant, $otherOrg);
    $ownPending = makeUser(RoleCode::Participant);
    $ownPending->forceFill(['primary_organization_id' => null])->save();
    $pendingId = (string) Str::uuid7();
    $foreignId = (string) Str::uuid7();
    asSystem(fn () => DB::table('organization_members')->insert([
        ['id' => $pendingId, 'organization_id' => $org->id, 'user_id' => $ownPending->id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
        ['id' => $foreignId, 'organization_id' => $otherOrg->id, 'user_id' => $ownPending->id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
    ]));

    signIn(RoleCode::OrgAdmin, $org);
    $this->get('/organisasi/anggota')->assertOk()->assertSee($ownPending->name)->assertDontSee($applicant->name);
    $this->post(route('org.members.decide', $foreignId), ['decision' => 'approve'])->assertNotFound();
    $this->post(route('org.members.decide', $pendingId), ['decision' => 'approve'])->assertRedirect();

    expect(asSystem(fn () => DB::table('organization_members')->where('id', $pendingId)->value('status')))->toBe('active')
        ->and($ownPending->fresh()->primary_organization_id)->toBe($org->id);
})->group('FR-AUTH-003', 'SEC-AUTHZ-10');

it('scopes the audit viewer to the organization for organization admins', function () {
    $org = makeOrganization();
    app(AuditLogger::class)->record('uji.org_entry', null, 'organization', $org->id, null, null, $org->id);
    app(AuditLogger::class)->record('uji.platform_entry', null, 'system', null);

    signIn(RoleCode::OrgAdmin, $org);
    $this->get('/organisasi/audit')->assertOk()->assertSee('uji.org_entry')->assertDontSee('uji.platform_entry');
})->group('FR-AUD-002');

it('adds a super admin only after another super admin approves', function () {
    $candidate = makeUser(RoleCode::AcademicAdmin);
    signIn(RoleCode::SuperAdmin);
    confirmAccess();
    $this->post(route('admin.users.super-admin', $candidate), ['reason' => 'Penambahan Super Admin cadangan'])->assertRedirect();
    expect($candidate->fresh()->hasRole(RoleCode::SuperAdmin))->toBeFalse();
    $request = ApprovalRequest::query()->where('subject_id', $candidate->id)->firstOrFail();
    $this->post('/keluar');
    nextRequest();

    signIn(RoleCode::SuperAdmin);
    confirmAccess();
    $this->post(route('admin.second-approvals.decide', $request), ['decision' => 'approve'])->assertRedirect();
    expect($candidate->fresh()->hasRole(RoleCode::SuperAdmin))->toBeTrue();
})->group('FR-USER-002', 'docs/07 §3');

it('shows freshly generated recovery codes even when consent is outdated', function () {
    $trainer = makeUser(RoleCode::Trainer);
    config(['legal.terms_version' => '2099-02']);
    loginAs($trainer);
    nextRequest();
    $this->get('/akun/mfa/aktifkan')->assertOk();
    $secret = (string) session('mfa.setup_secret');

    $this->post('/akun/mfa/aktifkan', ['password' => UserFactory::PASSWORD, 'code' => Totp::codeAt($secret, Totp::currentStep())])
        ->assertRedirect(route('mfa.recovery-codes'));
    nextRequest();
    $this->get(route('mfa.recovery-codes'))->assertOk()->assertSee('Kode Pemulihan');
    // Setelah itu, halaman lain meminta persetujuan versi baru.
    $this->get('/trainer')->assertRedirect(route('consent.show'));
})->group('SEC-AUTH-14', 'FR-CMS-003');
