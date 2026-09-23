<?php

declare(strict_types=1);

use App\Modules\Audit\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

it('chains audit entries and verifies the chain', function () {
    $audit = app(AuditLogger::class);
    $audit->record('uji.satu', changes: ['password' => 'rahasia', 'nilai' => 1]);
    $audit->record('uji.dua');

    $rows = DB::table('audit_logs')->orderByDesc('seq')->limit(2)->get()->reverse()->values();
    expect($rows[1]->prev_hash)->toBe($rows[0]->hash)
        ->and($rows[0]->changes)->toContain('[REDACTED]')->not->toContain('rahasia');

    expect(Artisan::call('stu:audit-verify'))->toBe(0);
})->group('SEC-LOG-11', 'SEC-LOG-13');

it('rejects updates and deletes on the audit log', function (string $sql) {
    app(AuditLogger::class)->record('uji.immutable');

    expect(fn () => DB::transaction(fn () => DB::statement($sql)))->toThrow(QueryException::class);
})->with([
    'update' => "update audit_logs set action = 'diubah'",
    'delete' => 'delete from audit_logs',
    'truncate' => 'truncate audit_logs',
])->group('SEC-LOG-10');
