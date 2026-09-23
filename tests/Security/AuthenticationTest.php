<?php

declare(strict_types=1);

use App\Modules\Access\RoleCode;
use App\Modules\Identity\Services\LoginService;
use Database\Factories\UserFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(fn () => cache()->flush());

it('logs a participant in and redirects to the participant area', function () {
    $user = makeUser(RoleCode::Participant);

    $this->post('/masuk', ['email' => $user->email, 'password' => UserFactory::PASSWORD])
        ->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    $this->get('/dasbor')->assertRedirect(route('participant.dashboard'));
    $this->get('/peserta')->assertOk()->assertSee('Dashboard Peserta');
})->group('FR-AUTH-004');

it('returns an identical generic error for wrong password, unknown and inactive accounts', function () {
    $active = makeUser();
    $inactive = makeUser(attributes: ['status' => 'deactivated']);

    $cases = [
        [$active->email, 'kata-sandi-salah'],
        ['tidak-ada@example.test', UserFactory::PASSWORD],
        [$inactive->email, UserFactory::PASSWORD],
    ];

    foreach ($cases as [$email, $password]) {
        $this->post('/masuk', ['email' => $email, 'password' => $password])
            ->assertSessionHasErrors(['email' => LoginService::GENERIC_ERROR]);
        $this->assertGuest();
    }
})->group('SEC-AUTH-06');

it('throttles repeated failures per account', function () {
    $user = makeUser();

    foreach (range(1, 5) as $_) {
        $this->post('/masuk', ['email' => $user->email, 'password' => 'salah'])->assertSessionHasErrors('email');
    }

    // Percobaan ke-6 — bahkan dengan kata sandi benar — ditolak.
    $this->post('/masuk', ['email' => $user->email, 'password' => UserFactory::PASSWORD])
        ->assertSessionHasErrors(['email' => LoginService::THROTTLED_ERROR]);
    $this->assertGuest();
    expect(DB::table('security_events')->where('type', 'rate_limit_exceeded')->exists())->toBeTrue();
})->group('SEC-AUTH-03');

it('ignores client supplied role parameters and records a security event', function () {
    $user = makeUser(RoleCode::Participant);

    $this->post('/masuk', ['email' => $user->email, 'password' => UserFactory::PASSWORD, 'role' => 'super_admin']);

    $this->get('/admin')->assertNotFound();
    expect(DB::table('security_events')->where('type', 'authz_suspicious_parameter')->exists())->toBeTrue();
})->group('SEC-INPUT-10', 'PROTO-01');

it('regenerates the session id on login (no session fixation)', function () {
    $user = makeUser();
    $this->get('/masuk');
    $before = session()->getId();

    $this->post('/masuk', ['email' => $user->email, 'password' => UserFactory::PASSWORD]);

    expect(session()->getId())->not->toBe($before);
})->group('SEC-AUTH-17');

it('logs out via POST only and invalidates the session', function () {
    $user = makeUser();
    loginAs($user);

    $this->get('/keluar')->assertStatus(405);
    $this->post('/keluar')->assertRedirect(route('login'));
    $this->assertGuest();
    $this->get('/peserta')->assertRedirect(route('login'));
})->group('SEC-AUTH-19');

it('rehashes passwords with Argon2id', function () {
    $user = makeUser();
    expect($user->password)->toStartWith('$argon2id$');
})->group('SEC-AUTH-01');

it('stores emails in lowercase and accepts any letter case at login', function () {
    $user = makeUser(attributes: ['email' => '  Raka.Prasetya@Example.TEST ']);

    expect($user->email)->toBe('raka.prasetya@example.test');

    $this->post('/masuk', ['email' => 'RAKA.PRASETYA@example.test', 'password' => UserFactory::PASSWORD])
        ->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
})->group('FR-AUTH-004');

it('rejects mixed-case emails written directly to the database', function () {
    expect(fn () => DB::transaction(fn () => DB::table('users')->insert([
        'id' => (string) Str::uuid7(), 'name' => 'X', 'email' => 'Huruf@Besar.test',
        'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ])))->toThrow(QueryException::class, 'users_email_lowercase');
});
