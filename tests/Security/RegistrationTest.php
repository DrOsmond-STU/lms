<?php

declare(strict_types=1);

use App\Modules\Access\RoleCode;
use App\Modules\Identity\Http\Controllers\RegistrationController;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\AccountAlreadyExistsNotification;
use App\Modules\Identity\Notifications\RegistrationCodeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    cache()->flush();
    Notification::fake();
});

function registrationPayload(array $overrides = []): array
{
    $password = freshPassword();

    return array_merge([
        'name' => 'Sinta Maharani',
        'email' => 'sinta.'.Str::lower(Str::random(6)).'@contoh.test',
        'password' => $password,
        'password_confirmation' => $password,
        'accept_terms' => '1',
        'accept_privacy' => '1',
    ], $overrides);
}

/** Mengambil kode OTP terakhir yang dikirim ke pengguna dari notifikasi palsu. */
function sentRegistrationCode(User $user): string
{
    $code = null;
    Notification::assertSentTo($user, RegistrationCodeNotification::class, function (RegistrationCodeNotification $notification) use ($user, &$code) {
        foreach ($notification->toMail($user)->introLines as $line) {
            if (preg_match('/\*\*(\d{6})\*\*/', (string) $line, $match) === 1) {
                $code = $match[1];
            }
        }

        return true;
    });

    return (string) $code;
}

function registeredUser(string $email): User
{
    return User::query()->where('email', $email)->firstOrFail();
}

it('registers a participant, verifies the email OTP and signs in', function () {
    $payload = registrationPayload(['email' => 'Sinta.Baru@Contoh.Test']);

    $this->post('/daftar', $payload)
        ->assertRedirect(route('register.verify'))
        ->assertSessionHas('status', RegistrationController::SENT_MESSAGE);

    $user = registeredUser('sinta.baru@contoh.test');
    expect($user->status)->toBe('pending_verification')
        ->and($user->roles)->toBeEmpty()
        ->and(DB::table('consents')->where('user_id', $user->id)->pluck('document')->sort()->values()->all())->toBe(['privacy', 'terms']);

    $this->get('/verifikasi-email')->assertOk()->assertSee('s***@contoh.test')->assertDontSee('sinta.baru@contoh.test');

    $this->post('/verifikasi-email', ['code' => sentRegistrationCode($user)])->assertRedirect(route('participant.dashboard'));

    $user->refresh();
    $this->assertAuthenticatedAs($user);
    expect($user->status)->toBe('active')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->roleCodes())->toBe([RoleCode::Participant])
        ->and(DB::table('audit_logs')->where('action', 'user.registered')->where('subject_id', $user->id)->exists())->toBeTrue();
})->group('FR-AUTH-001', 'FR-AUTH-002');

it('responds identically for an existing account and notifies its owner instead', function () {
    $existing = makeUser();
    $countBefore = User::query()->count();

    $this->post('/daftar', registrationPayload(['email' => strtoupper($existing->email)]))
        ->assertRedirect(route('register.verify'))
        ->assertSessionHas('status', RegistrationController::SENT_MESSAGE);

    expect(User::query()->count())->toBe($countBefore);
    Notification::assertSentTo($existing, AccountAlreadyExistsNotification::class);
    Notification::assertNotSentTo($existing, RegistrationCodeNotification::class);
    // Verifikasi dengan kode apa pun tidak pernah berhasil untuk akun yang sudah aktif.
    $this->post('/verifikasi-email', ['code' => '000000'])->assertSessionHasErrors(['code' => RegistrationController::INVALID_CODE]);
    $this->assertGuest();
})->group('SEC-AUTH-06');

it('never stores the OTP in plain text', function () {
    $payload = registrationPayload();
    $this->post('/daftar', $payload);
    $user = registeredUser($payload['email']);
    $code = sentRegistrationCode($user);

    $row = DB::table('one_time_tokens')->where('user_id', $user->id)->first();
    expect($row->token_hash)->toHaveLength(64)
        ->and($row->token_hash)->not->toContain($code)
        ->and(json_encode($row))->not->toContain('"'.$code.'"');
})->group('SEC-AUTH-09');

it('locks the code after five wrong attempts', function () {
    $payload = registrationPayload();
    $this->post('/daftar', $payload);
    $user = registeredUser($payload['email']);
    $code = sentRegistrationCode($user);
    $wrong = $code === '111111' ? '222222' : '111111';

    foreach (range(1, 5) as $attempt) {
        $this->post('/verifikasi-email', ['code' => $wrong])->assertSessionHasErrors('code');
    }
    $this->post('/verifikasi-email', ['code' => $code])->assertSessionHasErrors(['code' => RegistrationController::INVALID_CODE]);
    expect($user->fresh()->status)->toBe('pending_verification');
})->group('FR-AUTH-002', 'SEC-AUTH-09');

it('expires the code after ten minutes', function () {
    $payload = registrationPayload();
    $this->post('/daftar', $payload);
    $user = registeredUser($payload['email']);
    $code = sentRegistrationCode($user);

    $this->travel(11)->minutes();
    $this->post('/verifikasi-email', ['code' => $code])->assertSessionHasErrors('code');
    expect($user->fresh()->status)->toBe('pending_verification');
})->group('FR-AUTH-002');

it('limits OTP sends to three per hour and invalidates older codes', function () {
    $payload = registrationPayload();
    $this->post('/daftar', $payload);
    $user = registeredUser($payload['email']);
    $first = sentRegistrationCode($user);

    foreach (range(1, 4) as $attempt) {
        $this->post('/verifikasi-email/kirim-ulang')->assertSessionHas('status');
    }

    Notification::assertSentToTimes($user, RegistrationCodeNotification::class, 3);
    expect(DB::table('one_time_tokens')->where('user_id', $user->id)->whereNull('consumed_at')->count())->toBe(1);
    $latest = sentRegistrationCode($user);
    if ($latest !== $first) {
        $this->post('/verifikasi-email', ['code' => $first])->assertSessionHasErrors('code');
    }
})->group('FR-AUTH-002');

it('activates organization membership only when the email domain is verified for it', function () {
    $organization = makeOrganization();
    asSystem(fn () => DB::table('organization_domains')->insert([
        'id' => (string) Str::uuid7(), 'organization_id' => $organization->id, 'domain' => 'kampus-uji.ac.id',
        'verified_at' => now(), 'method' => 'admin', 'created_at' => now(), 'updated_at' => now(),
    ]));

    // Domain cocok → anggota aktif & peran peserta terikat organisasi.
    $matching = registrationPayload(['email' => 'mhs@kampus-uji.ac.id', 'organization_code' => strtolower($organization->code)]);
    $this->post('/daftar', $matching);
    $member = registeredUser('mhs@kampus-uji.ac.id');
    $this->post('/verifikasi-email', ['code' => sentRegistrationCode($member)]);
    $member->refresh();
    expect($member->primary_organization_id)->toBe($organization->id)
        ->and($member->hasRole(RoleCode::Participant, $organization->id))->toBeTrue();

    // Domain lain → keanggotaan tertunda, peran tanpa organisasi.
    $this->post('/keluar');
    $other = registrationPayload(['organization_code' => $organization->code]);
    $this->post('/daftar', $other);
    $pending = registeredUser($other['email']);
    $this->post('/verifikasi-email', ['code' => sentRegistrationCode($pending)])->assertSessionHas('status', fn ($status) => str_contains($status, 'menunggu persetujuan'));
    $pending->refresh();
    expect($pending->primary_organization_id)->toBeNull()
        ->and($pending->hasRole(RoleCode::Participant, $organization->id))->toBeFalse()
        ->and(asSystem(fn () => DB::table('organization_members')->where('user_id', $pending->id)->value('status')))->toBe('pending');
})->group('FR-AUTH-003');

it('rejects unknown or archived organization codes', function () {
    $archived = makeOrganization();
    asSystem(fn () => $archived->forceFill(['status' => 'inactive'])->save());

    $this->post('/daftar', registrationPayload(['organization_code' => 'ZZZZZZ']))->assertSessionHasErrors('organization_code');
    $this->post('/daftar', registrationPayload(['organization_code' => $archived->code]))->assertSessionHasErrors('organization_code');
})->group('FR-AUTH-003');

it('requires separate consent to the terms and privacy policy', function () {
    $this->post('/daftar', registrationPayload(['accept_terms' => null]))->assertSessionHasErrors('accept_terms');
    $this->post('/daftar', registrationPayload(['accept_privacy' => null]))->assertSessionHasErrors('accept_privacy');
})->group('FR-AUTH-001');

it('throttles registrations per IP address', function () {
    foreach (range(1, 10) as $attempt) {
        $this->post('/daftar', registrationPayload());
    }
    $this->post('/daftar', registrationPayload())->assertSessionHasErrors('email');
})->group('SEC-AUTH-09');

it('hides registration entirely when disabled', function () {
    config(['security.registration.enabled' => false]);

    $this->get('/daftar')->assertNotFound();
    $this->post('/daftar', registrationPayload())->assertNotFound();
})->group('FR-AUTH-001');

it('prunes unverified self-registrations after the retention window', function () {
    $payload = registrationPayload();
    $this->post('/daftar', $payload);
    $user = registeredUser($payload['email']);

    $this->travel(8)->days();
    $this->artisan('stu:prune-unverified')->assertSuccessful();

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse();
})->group('SEC-PRIV');
