<?php

declare(strict_types=1);

use App\Support\Security\Base32;
use App\Support\Security\Totp;

// Vektor uji RFC 6238 Lampiran B (SHA1, secret ASCII "12345678901234567890"), 6 digit terakhir.
$secret = Base32::encode('12345678901234567890');

it('matches RFC 6238 test vectors', function (int $time, string $expected) use ($secret) {
    expect(Totp::codeAt($secret, intdiv($time, 30)))->toBe($expected);
})->with([
    [59, '287082'],
    [1111111109, '081804'],
    [1111111111, '050471'],
    [1234567890, '005924'],
    [2000000000, '279037'],
    [20000000000, '353130'],
])->group('SEC-AUTH-13');

it('accepts ±1 time-step and rejects replayed or older steps', function () use ($secret) {
    $now = 1_700_000_000;
    $step = Totp::currentStep($now);
    $code = Totp::codeAt($secret, $step);

    expect(Totp::verify($secret, $code, null, $now))->toBe($step)
        ->and(Totp::verify($secret, Totp::codeAt($secret, $step - 1), null, $now))->toBe($step - 1)
        ->and(Totp::verify($secret, Totp::codeAt($secret, $step - 2), null, $now))->toBeNull()
        ->and(Totp::verify($secret, $code, $step, $now))->toBeNull() // replay
        ->and(Totp::verify($secret, 'abc123', null, $now))->toBeNull();
})->group('SEC-AUTH-13');

it('generates 160-bit base32 secrets and round-trips base32', function () {
    $secret = Totp::generateSecret();
    expect($secret)->toMatch('/^[A-Z2-7]{32}$/')
        ->and(strlen(Base32::decode($secret)))->toBe(20)
        ->and(Base32::decode(Base32::encode('halo dunia')))->toBe('halo dunia');
});
