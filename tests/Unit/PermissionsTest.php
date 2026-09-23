<?php

declare(strict_types=1);

use App\Modules\Access\Permissions;
use App\Modules\Access\RoleCode;

it('maps every role only to permissions that exist in the catalog', function () {
    foreach (Permissions::ROLE_MAP as $role => $entries) {
        expect(RoleCode::tryFrom($role))->not->toBeNull("Peran {$role} tidak dikenal");
        foreach (Permissions::forRole($role) as $code) {
            expect(Permissions::exists($code))->toBeTrue("Izin {$code} ({$role}) tidak ada di katalog");
        }
    }
    expect(array_keys(Permissions::ROLE_MAP))->toEqualCanonicalizing(array_map(fn ($r) => $r->value, RoleCode::cases()));
});

it('enforces segregation of duties in the role map', function () {
    // Trainer tidak boleh menyetujui/mencabut sertifikat (docs/07 §3).
    expect(Permissions::forRole('trainer'))->not->toContain('certificate.approve')
        ->not->toContain('certificate.revoke');
    // Admin Keuangan tidak boleh mengubah status kelulusan; Admin Akademik tidak boleh refund.
    expect(Permissions::forRole('finance_admin'))->not->toContain('enrollment.override_status')
        ->not->toContain('certificate.approve');
    expect(Permissions::forRole('academic_admin'))->not->toContain('payment.refund_approve')
        ->not->toContain('payment.mark_paid_manual');
    // Admin tidak mengerjakan ujian; peserta tidak melihat kunci jawaban.
    expect(Permissions::forRole('super_admin'))->not->toContain('assessment.attempt');
    expect(Permissions::forRole('participant'))->not->toContain('assessment.view_answer_key');
})->group('SEC-AUTHZ-14');

it('requires MFA for every role except participant', function () {
    foreach (RoleCode::cases() as $role) {
        expect($role->requiresMfa())->toBe($role !== RoleCode::Participant, $role->value);
    }
})->group('SEC-AUTH-10');
