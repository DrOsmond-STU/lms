<?php

declare(strict_types=1);

use App\Modules\Access\RoleCode;
use App\Modules\Identity\Notifications\PasswordChangedNotification;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    cache()->flush();
    Notification::fake();
});

it('requires the current password to change it', function () {
    $user = signIn(RoleCode::Participant);

    $password = freshPassword();
    $this->post('/akun/keamanan/kata-sandi', [
        'current_password' => freshPassword(),
        'password' => $password, 'password_confirmation' => $password,
    ])->assertSessionHasErrors('current_password');

    expect(Hash::check(UserFactory::PASSWORD, (string) $user->fresh()->password))->toBeTrue()
        ->and(DB::table('security_events')->where('type', 'authn_password_change_fail')->where('user_id', $user->id)->exists())->toBeTrue();
})->group('FR-AUTH-008');

it('changes the password, keeps this session and revokes the others', function () {
    $user = signIn(RoleCode::Participant);
    $versionBefore = $user->fresh()->session_version;

    $password = freshPassword();
    $this->post('/akun/keamanan/kata-sandi', [
        'current_password' => UserFactory::PASSWORD,
        'password' => $password, 'password_confirmation' => $password,
    ])->assertRedirect(route('account.security'));

    nextRequest();
    $this->get('/akun/keamanan')->assertOk();

    $fresh = $user->fresh();
    expect($fresh->session_version)->toBe($versionBefore + 1)
        ->and(Hash::check($password, (string) $fresh->password))->toBeTrue()
        ->and(DB::table('audit_logs')->where('action', 'user.password_changed')->where('subject_id', $user->id)->exists())->toBeTrue();
    Notification::assertSentTo($user, PasswordChangedNotification::class);
})->group('FR-AUTH-008', 'FR-AUTH-010', 'SEC-AUTH-19');

it('enforces the privileged minimum length on password change', function () {
    signIn(RoleCode::Trainer);

    $this->post('/akun/keamanan/kata-sandi', [
        'current_password' => UserFactory::PASSWORD,
        'password' => 'Pendek-11ch', 'password_confirmation' => 'Pendek-11ch',
    ])->assertSessionHasErrors('password');
})->group('SEC-AUTH-02');

it('requires recent re-authentication to regenerate recovery codes', function () {
    signIn(RoleCode::Trainer);
    $this->get('/akun/keamanan');

    $this->post('/akun/mfa/kode-pemulihan')->assertRedirect(route('password.confirm'));

    confirmAccess();
    $this->post('/akun/mfa/kode-pemulihan')->assertRedirect(route('mfa.recovery-codes'));
    $this->get('/akun/mfa/kode-pemulihan')->assertOk();
})->group('FR-AUTH-014', 'SEC-AUTH-25');

it('returns to the originating page after re-authentication, never replaying the POST', function () {
    signIn(RoleCode::Trainer);
    $this->get('/akun/keamanan');

    $this->post('/akun/mfa/kode-pemulihan')->assertRedirect(route('password.confirm'));
    expect(session('url.intended'))->toBe(url('/akun/keamanan'));
})->group('SEC-AUTH-25');
