<?php

declare(strict_types=1);

use App\Modules\Access\RoleCode;
use App\Modules\Identity\Http\Controllers\PasswordResetController;
use App\Modules\Identity\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(fn () => cache()->flush());

it('responds identically for registered and unknown emails', function () {
    Notification::fake();
    $user = makeUser();

    $this->post('/lupa-kata-sandi', ['email' => $user->email])->assertSessionHas('status', PasswordResetController::GENERIC_SENT);
    $this->post('/lupa-kata-sandi', ['email' => 'tidak-ada@example.test'])->assertSessionHas('status', PasswordResetController::GENERIC_SENT);

    Notification::assertSentTo($user, ResetPasswordNotification::class);
    Notification::assertSentTimes(ResetPasswordNotification::class, 1);
})->group('SEC-AUTH-06');

it('builds reset links from APP_URL even when the Host header is forged', function () {
    Notification::fake();
    $user = makeUser();

    $this->withHeaders(['Host' => 'evil.example'])->post('/lupa-kata-sandi', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;

        return str_starts_with($url, rtrim((string) config('app.url'), '/').'/reset-kata-sandi/') && ! str_contains($url, 'evil');
    });
})->group('SEC-AUTH-22');

it('resets the password once, revokes sessions and does not auto-login', function () {
    $user = makeUser();
    $token = asSystem(fn () => Password::createToken($user));
    $versionBefore = $user->session_version;

    $this->post('/reset-kata-sandi', ['token' => $token, 'email' => $user->email, 'password' => 'frasa-sandi-baru-yang-panjang', 'password_confirmation' => 'frasa-sandi-baru-yang-panjang'])
        ->assertRedirect(route('login'));
    $this->assertGuest();
    expect($user->fresh()->session_version)->toBe($versionBefore + 1);

    // Token sekali pakai.
    $this->post('/reset-kata-sandi', ['token' => $token, 'email' => $user->email, 'password' => 'frasa-lain-yang-panjang-1', 'password_confirmation' => 'frasa-lain-yang-panjang-1'])
        ->assertSessionHasErrors('email');
    expect(DB::table('audit_logs')->where('action', 'user.password_reset')->where('subject_id', $user->id)->count())->toBe(1);
})->group('SEC-AUTH-21', 'SEC-AUTH-23');

it('requires at least 12 characters for privileged roles', function () {
    $trainer = makeUser(RoleCode::Trainer);
    $token = asSystem(fn () => Password::createToken($trainer));

    $this->post('/reset-kata-sandi', ['token' => $token, 'email' => $trainer->email, 'password' => 'pendek12', 'password_confirmation' => 'pendek12'])
        ->assertSessionHasErrors('password');
})->group('SEC-AUTH-02');
