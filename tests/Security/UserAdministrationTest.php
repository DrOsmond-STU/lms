<?php

declare(strict_types=1);

use App\Modules\Access\RoleCode;
use App\Modules\Identity\Http\Controllers\InvitationController;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\InvitationNotification;
use App\Modules\Identity\Notifications\MfaResetNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    cache()->flush();
    Notification::fake();
});

/** Mengambil token undangan dari notifikasi palsu. */
function invitationToken(User $user): string
{
    $token = '';
    Notification::assertSentTo($user, InvitationNotification::class, function (InvitationNotification $notification) use ($user, &$token) {
        $token = basename((string) $notification->toMail($user)->actionUrl);

        return true;
    });

    return $token;
}

it('lets an academic admin invite a trainer who then sets a password and must enrol MFA', function () {
    signIn(RoleCode::AcademicAdmin);
    $organization = makeOrganization();

    $this->post('/admin/pengguna', [
        'name' => 'Trainer Baru', 'email' => 'Trainer.Baru@Contoh.Test', 'role' => 'trainer', 'organization_id' => $organization->id,
    ])->assertRedirect();

    $invited = User::query()->where('email', 'trainer.baru@contoh.test')->firstOrFail();
    expect($invited->status)->toBe('pending_verification')
        ->and($invited->password)->toBeNull()
        ->and($invited->hasRole(RoleCode::Trainer, $organization->id))->toBeTrue();

    $token = invitationToken($invited);
    expect(DB::table('one_time_tokens')->where('user_id', $invited->id)->value('token_hash'))->not->toBe($token);

    $this->post('/keluar');
    $password = freshPassword();
    $this->get('/undangan/'.$token)->assertOk()->assertSee('Atur Kata Sandi');
    $this->post('/undangan/'.$token, [
        'password' => $password, 'password_confirmation' => $password,
        'accept_terms' => '1', 'accept_privacy' => '1',
    ])->assertRedirect(route('login'));
    $this->assertGuest();

    // Sekali pakai.
    $this->get('/undangan/'.$token)->assertOk()->assertSee(InvitationController::INVALID);

    $this->post('/masuk', ['email' => $invited->email, 'password' => $password]);
    nextRequest();
    $this->get('/trainer')->assertRedirect(route('mfa.setup'));
})->group('FR-USER-003', 'SEC-AUTH-10');

it('expires invitations after 72 hours', function () {
    signIn(RoleCode::AcademicAdmin);
    $this->post('/admin/pengguna', ['name' => 'Peserta Undangan', 'email' => 'undangan@contoh.test', 'role' => 'participant']);
    $token = invitationToken(User::query()->where('email', 'undangan@contoh.test')->firstOrFail());

    $this->post('/keluar');
    $this->travel(73)->hours();
    $this->get('/undangan/'.$token)->assertOk()->assertSee(InvitationController::INVALID);
})->group('FR-USER-003');

it('never allows assigning super admin or roles above the actor', function () {
    $actor = signIn(RoleCode::AcademicAdmin);
    $target = makeUser(RoleCode::Trainer);
    confirmAccess();

    $this->post("/admin/pengguna/{$target->id}/peran", ['role' => 'super_admin'])->assertSessionHasErrors('role');
    $this->post("/admin/pengguna/{$target->id}/peran", ['role' => 'finance_admin'])->assertSessionHasErrors('role');
    $this->post('/admin/pengguna', ['name' => 'Calon Admin', 'email' => 'calon@contoh.test', 'role' => 'academic_admin'])->assertSessionHasErrors('role');

    expect($target->fresh()->roleCodes())->toBe([RoleCode::Trainer])
        ->and(User::query()->where('email', 'calon@contoh.test')->exists())->toBeFalse();
})->group('FR-USER-002', 'SEC-AUTHZ-18');

it('forbids an academic admin from managing platform admins', function () {
    signIn(RoleCode::AcademicAdmin);
    $superAdmin = makeUser(RoleCode::SuperAdmin);
    $peer = makeUser(RoleCode::AcademicAdmin);
    confirmAccess();

    $this->post("/admin/pengguna/{$superAdmin->id}/status", ['reason' => 'uji eskalasi'])->assertForbidden();
    $this->post("/admin/pengguna/{$peer->id}/status", ['reason' => 'uji eskalasi'])->assertForbidden();
    expect($superAdmin->fresh()->status)->toBe('active');
})->group('SEC-AUTHZ-18');

it('forbids changing your own status or roles', function () {
    $actor = signIn(RoleCode::SuperAdmin);
    confirmAccess();

    $this->post("/admin/pengguna/{$actor->id}/status", ['reason' => 'nonaktifkan diri'])->assertForbidden();
    $this->post("/admin/pengguna/{$actor->id}/peran", ['role' => 'trainer'])->assertForbidden();
})->group('SEC-AUTHZ-18');

it('requires re-authentication for role changes and deactivation', function () {
    signIn(RoleCode::SuperAdmin);
    $target = makeUser(RoleCode::Trainer);
    $this->get("/admin/pengguna/{$target->id}")->assertOk();

    $this->post("/admin/pengguna/{$target->id}/status", ['reason' => 'offboarding'])->assertRedirect(route('password.confirm'));
    $this->post("/admin/pengguna/{$target->id}/peran", ['role' => 'participant'])->assertRedirect(route('password.confirm'));
    expect($target->fresh()->status)->toBe('active');
})->group('FR-AUTH-014');

it('deactivating a user ends their live session immediately', function () {
    $target = makeUser(RoleCode::Participant);
    loginAs($target);
    nextRequest();
    $this->get('/peserta')->assertOk();
    $targetSession = session()->all();

    $this->flushSession();
    nextRequest();
    signIn(RoleCode::AcademicAdmin);
    confirmAccess();
    $this->post("/admin/pengguna/{$target->id}/status", ['reason' => 'akun disalahgunakan'])->assertRedirect();
    expect($target->fresh()->status)->toBe('deactivated');

    $this->flushSession();
    nextRequest();
    $this->withSession($targetSession)->get('/peserta')->assertRedirect(route('login'));
    $this->assertGuest();
})->group('SEC-AUTH-19', 'FR-USER-001');

it('rejects combining a platform admin role with participant', function () {
    signIn(RoleCode::SuperAdmin);
    $participant = makeUser(RoleCode::Participant);
    confirmAccess();

    $this->post("/admin/pengguna/{$participant->id}/peran", ['role' => 'finance_admin'])->assertSessionHasErrors('role');
    expect($participant->fresh()->roleCodes())->toBe([RoleCode::Participant]);
})->group('FR-USER-002');

it('lets support admins reset MFA only with a documented verification', function () {
    signIn(RoleCode::SupportAdmin);
    $trainer = makeUser(RoleCode::Trainer);
    enrollTotp($trainer);
    confirmAccess();

    $this->post("/admin/pengguna/{$trainer->id}/reset-mfa", ['ticket_reference' => 'SUP-1001', 'verification_method' => 'video_call'])
        ->assertSessionHasErrors('confirm_identity');
    expect($trainer->fresh()->hasConfirmedMfa())->toBeTrue();

    $this->post("/admin/pengguna/{$trainer->id}/reset-mfa", [
        'ticket_reference' => 'SUP-1001', 'verification_method' => 'video_call', 'confirm_identity' => '1',
    ])->assertRedirect();

    expect($trainer->fresh()->hasConfirmedMfa())->toBeFalse()
        ->and(DB::table('audit_logs')->where('action', 'user.mfa_reset')->where('subject_id', $trainer->id)->value('reason'))->toContain('SUP-1001');
    Notification::assertSentTo($trainer, MfaResetNotification::class);
})->group('FR-USER-006');

it('hides the user admin from organization admins and participants', function (RoleCode $role) {
    signIn($role);

    $this->get('/admin/pengguna')->assertNotFound();
})->with([RoleCode::OrgAdmin, RoleCode::Participant, RoleCode::Trainer])->group('SEC-AUTHZ-03');

it('lets finance admins into the admin area but not the user list', function () {
    signIn(RoleCode::FinanceAdmin);

    $this->get('/admin/pengguna')->assertForbidden();
})->group('SEC-AUTHZ-01');

it('masks emails in the user list', function () {
    signIn(RoleCode::SuperAdmin);
    $target = makeUser(RoleCode::Participant, attributes: ['email' => 'rahasia.peserta@contoh.test']);

    $this->get('/admin/pengguna')->assertOk()->assertSee('r***@contoh.test')->assertDontSee('rahasia.peserta@contoh.test');
    $this->get("/admin/pengguna/{$target->id}")->assertOk()->assertSee('rahasia.peserta@contoh.test');
})->group('SEC-PRIV');

it('returns 404 for malformed identifiers instead of a database error', function () {
    signIn(RoleCode::SuperAdmin);

    $this->get('/admin/pengguna/bukan-uuid')->assertNotFound();
    $this->get('/admin/organisasi/1%20OR%201=1')->assertNotFound();
})->group('SEC-INPUT');
