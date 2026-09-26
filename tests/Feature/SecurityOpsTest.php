<?php

declare(strict_types=1);

use App\Modules\Access\RoleCode;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Security\Models\Backup;
use App\Modules\Security\Models\PrivacyRequest;
use App\Modules\Security\Services\BackupService;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Keamanan operasional: backup otomatis (pg_dump/fallback, enkripsi), pemantauan aktivitas
 * mencurigakan, retensi data, dan permintaan privasi (ekspor & anonimisasi).
 */

it('creates database backups, lists and downloads them, and prunes old ones', function () {
    $admin = signIn(RoleCode::SuperAdmin);
    confirmAccess();
    $this->post(route('admin.backups.run'), ['kind' => 'db'])->assertRedirect();
    $backup = asSystem(fn () => Backup::query()->where('kind', 'db')->latest('started_at')->firstOrFail());
    expect($backup->status)->toBe('ok')->and($backup->size_bytes)->toBeGreaterThan(100)->and(is_file(BackupService::path($backup)))->toBeTrue()
        ->and(hash_file('sha256', BackupService::path($backup)))->toBe($backup->checksum);
    $this->get(route('admin.backups.index'))->assertOk()->assertSee($backup->filename);
    confirmAccess();
    $this->get(route('admin.backups.download', $backup))->assertOk()->assertDownload($backup->filename);

    asSystem(fn () => $backup->forceFill(['started_at' => now()->subDays(40)])->save());
    expect(app(BackupService::class)->prune(14))->toBe(1)->and(is_file(BackupService::path($backup)))->toBeFalse();
    expect(asSystem(fn () => DB::table('audit_logs')->whereIn('action', ['backup.database', 'backup.downloaded'])->count()))->toBe(2);
    $this->post('/keluar');
    nextRequest();

    signIn(RoleCode::AcademicAdmin);
    $this->get(route('admin.backups.index'))->assertForbidden();
});

it('encrypts and decrypts backup files with libsodium', function () {
    $key = sodium_crypto_secretstream_xchacha20poly1305_keygen();
    $source = tempnam(sys_get_temp_dir(), 'src');
    file_put_contents($source, str_repeat('data rahasia ', 200000));
    $encrypted = $source.'.enc';
    $restored = $source.'.dec';
    $method = new ReflectionMethod(BackupService::class, 'encryptFile');
    $method->invoke(app(BackupService::class), $source, $encrypted, $key);
    expect(file_get_contents($encrypted))->not->toContain('data rahasia');
    BackupService::decryptFile($encrypted, $restored, $key);
    expect(hash_file('sha256', $restored))->toBe(hash_file('sha256', $source));
    expect(fn () => BackupService::decryptFile($encrypted, $restored, sodium_crypto_secretstream_xchacha20poly1305_keygen()))->toThrow(RuntimeException::class);
    unlink($source);
    unlink($encrypted);
    unlink($restored);
});

it('detects brute force and mass exports, notifies admins, and prunes by retention', function () {
    $admin = signIn(RoleCode::SuperAdmin);
    $victim = makeUser(RoleCode::Participant);
    asSystem(function () use ($victim, $admin): void {
        foreach (range(1, 12) as $i) {
            DB::table('security_events')->insert(['id' => (string) Str::uuid7(), 'occurred_at' => now()->subMinutes($i), 'type' => 'authn_login_fail', 'severity' => 'info', 'user_id' => $i <= 6 ? $victim->id : null, 'ip' => '203.0.113.9', 'details' => '{}']);
        }
        foreach (range(1, 25) as $i) {
            app(AuditLogger::class)->record('report.exported', $admin, 'report', null, ['n' => $i]);
        }
        DB::table('notifications')->insert(['id' => (string) Str::uuid7(), 'user_id' => $victim->id, 'category' => 'system', 'title' => 'Lama', 'body' => 'x', 'created_at' => now()->subDays(400)]);
    });

    $this->artisan('stu:security-scan')->assertSuccessful();
    $this->artisan('stu:security-scan')->assertSuccessful(); // idempoten dalam jendela dedup
    $alerts = asSystem(fn () => DB::table('security_events')->whereIn('type', ['brute_force_suspected', 'account_targeted', 'mass_export'])->pluck('type'));
    expect($alerts->countBy()->all())->toEqualCanonicalizing(['account_targeted' => 1, 'brute_force_suspected' => 1, 'mass_export' => 1]);
    expect(asSystem(fn () => DB::table('notifications')->where('user_id', $admin->id)->where('category', 'security')->count()))->toBeGreaterThanOrEqual(2);
    expect(asSystem(fn () => DB::table('notifications')->where('user_id', $victim->id)->where('title', 'Percobaan masuk mencurigakan')->exists()))->toBeTrue();

    $this->get(route('admin.security.monitor'))->assertOk()->assertSee('brute_force_suspected')->assertSee('203.0.113.9');
    $this->get(route('admin.security.monitor', ['tingkat' => 'high']))->assertOk()->assertSee('mass_export');

    $this->artisan('stu:retention-prune')->assertSuccessful();
    expect(asSystem(fn () => DB::table('notifications')->where('title', 'Lama')->exists()))->toBeFalse();
});

it('lets participants export their data and request deletion which admins anonymize', function () {
    $course = makeCourse(['final' => false]);
    $participant = signIn(RoleCode::Participant);
    enrollVia($course['class'], $participant);
    $export = $this->get(route('account.privacy.export'))->assertOk()->assertHeader('content-disposition', 'attachment; filename="data-saya-'.now()->format('Ymd').'.json"')->json();
    expect($export['profile']['email'])->toBe($participant->email)->and($export['enrollments'])->toHaveCount(1);

    $this->get(route('account.privacy'))->assertOk()->assertSee('Ajukan penghapusan akun');
    $this->post(route('account.privacy.delete'), ['note' => 'Tidak lagi memakai layanan'])->assertSessionHasErrors('confirm');
    $this->post(route('account.privacy.delete'), ['note' => 'Tidak lagi memakai layanan', 'confirm' => '1'])->assertSessionHasNoErrors();
    $this->post(route('account.privacy.delete'), ['confirm' => '1'])->assertSessionHasErrors('note');
    $request = asSystem(fn () => PrivacyRequest::query()->where('user_id', $participant->id)->firstOrFail());
    $this->get(route('account.privacy'))->assertOk()->assertSee('sedang ditinjau admin');
    $this->post('/keluar');
    nextRequest();

    $admin = signIn(RoleCode::SuperAdmin);
    $this->get(route('admin.privacy.index'))->assertOk()->assertSee('Tidak lagi memakai layanan');
    confirmAccess();
    $this->post(route('admin.privacy.decide', $request), ['decision' => 'process', 'decision_note' => 'Diproses sesuai permintaan'])->assertSessionHasNoErrors();
    $fresh = asSystem(fn () => User::query()->findOrFail($participant->id));
    expect($fresh->status)->toBe('anonymized')->and($fresh->name)->toBe('Pengguna Dihapus')->and($fresh->email)->toEndWith('@anonymized.invalid')
        ->and(asSystem(fn () => DB::table('role_user')->where('user_id', $participant->id)->count()))->toBe(0)
        ->and(asSystem(fn () => DB::table('enrollments')->where('user_id', $participant->id)->count()))->toBe(1);
    expect(asSystem(fn () => $request->fresh()->status))->toBe('processed');
    $this->post('/keluar');
    nextRequest();

    // Akun teranonimkan tidak dapat masuk lagi.
    $this->post('/masuk', ['email' => $participant->email, 'password' => UserFactory::PASSWORD])->assertSessionHasErrors();
});
