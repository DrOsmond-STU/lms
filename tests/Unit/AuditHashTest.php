<?php

declare(strict_types=1);

use App\Modules\Audit\Services\AuditLogger;

it('redacts secrets recursively before they reach the audit log', function () {
    $redacted = AuditLogger::redact(['name' => 'Raka', 'password' => 'x', 'nested' => ['token' => 't', 'ok' => 1]]);

    expect($redacted)->toBe(['name' => 'Raka', 'password' => '[REDACTED]', 'nested' => ['token' => '[REDACTED]', 'ok' => 1]]);
})->group('SEC-LOG-05');

it('produces a hash that changes when any field or the previous hash changes', function () {
    $entry = ['id' => 'a', 'occurred_at' => '2026-01-01T00:00:00.000000Z', 'action' => 'x', 'changes' => ['b' => 1, 'a' => 2]];
    $hash = AuditLogger::computeHash(AuditLogger::GENESIS_HASH, $entry);

    expect(AuditLogger::computeHash(AuditLogger::GENESIS_HASH, $entry))->toBe($hash)
        // urutan kunci jsonb tidak memengaruhi hash (kanonikalisasi)
        ->and(AuditLogger::computeHash(AuditLogger::GENESIS_HASH, [...$entry, 'changes' => ['a' => 2, 'b' => 1]]))->toBe($hash)
        ->and(AuditLogger::computeHash(AuditLogger::GENESIS_HASH, [...$entry, 'action' => 'y']))->not->toBe($hash)
        ->and(AuditLogger::computeHash(str_repeat('1', 64), $entry))->not->toBe($hash);
})->group('SEC-LOG-11');
