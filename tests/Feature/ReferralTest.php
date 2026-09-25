<?php

declare(strict_types=1);

use App\Modules\Access\RoleCode;
use App\Modules\Access\Services\RoleAssigner;
use App\Modules\Identity\Models\User;
use App\Modules\Payment\Models\PaymentTransaction;
use App\Modules\Referral\Models\ReferralCommission;
use App\Modules\Referral\Models\ReferralProfile;
use App\Modules\Referral\Services\ReferralService;
use App\Modules\Settings\Services\SystemSettings;
use Database\Factories\UserFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/*
 * Program referral: kode per peserta, atribusi saat registrasi (first-touch), komisi hanya
 * dari pembayaran lunas, pencairan oleh Admin Keuangan (SoD), laporan admin.
 */

function referralSettings(array $overrides = []): void
{
    $values = ['payment.bank_name' => 'Bank Uji', 'payment.bank_account_number' => '1234567890', 'payment.bank_account_name' => 'PT Uji',
        'payment.manual_settle_threshold' => 100_000_000, 'referral.commission_percent' => 10, 'referral.max_commission' => 0, 'referral.validity_months' => 12, 'referral.enabled' => true];
    $values = $overrides + $values;
    DB::table('system_settings')->upsert(collect($values)->map(fn ($v, $k) => ['key' => $k, 'value' => json_encode($v), 'updated_at' => now()])->values()->all(), ['key'], ['value', 'updated_at']);
    Cache::forget('system_settings.v1');
    SystemSettings::applyToConfig();
}

/** Peserta baru mendaftar (via formulir) dengan kode referral, lalu diaktifkan. */
function registerReferred(string $code, string $email = 'referred@contoh.test'): User
{
    Notification::fake();
    test()->post('/daftar', ['name' => 'Peserta Referral', 'email' => $email, 'password' => UserFactory::PASSWORD, 'password_confirmation' => UserFactory::PASSWORD,
        'accept_terms' => '1', 'accept_privacy' => '1', 'referral_code' => $code])->assertSessionHasNoErrors();
    $user = User::query()->where('email', $email)->firstOrFail();
    asSystem(function () use ($user): void {
        $user->forceFill(['status' => 'active', 'email_verified_at' => now()])->save();
        app(RoleAssigner::class)->assign($user, RoleCode::Participant, null, null);
    });

    return $user;
}

it('gives each participant a referral code and link, and attributes new registrations once', function () {
    referralSettings();
    $referrer = signIn(RoleCode::Participant);
    $page = $this->get(route('referral.mine'))->assertOk();
    $code = (string) asSystem(fn () => ReferralProfile::query()->whereKey($referrer->id)->value('code'));
    expect($code)->toMatch('/^[A-Z2-9]{8}$/');
    $page->assertSee($code)->assertSee('/daftar?ref='.$code);
    $this->post('/keluar');
    nextRequest();

    // Tautan referral sebagai tamu: cookie diset, kunjungan dihitung, formulir terisi otomatis.
    $this->get('/daftar?ref='.strtolower($code))->assertOk()->assertCookie(ReferralService::COOKIE, $code)->assertSee('value="'.$code.'"', false);
    expect(asSystem(fn () => (int) DB::table('referral_profiles')->where('user_id', $referrer->id)->value('visits')))->toBe(1);
    $this->get('/?ref=BUKANKODE!')->assertOk()->assertCookieMissing(ReferralService::COOKIE);

    $referred = registerReferred($code);
    expect($referred->referred_by)->toBe($referrer->id)->and($referred->referred_at)->not->toBeNull()
        ->and(DB::table('notifications')->where('user_id', $referrer->id)->where('category', 'referral')->count())->toBe(1);

    // Kode salah / referral diri sendiri / akun sudah terkait → tidak dikaitkan.
    $service = app(ReferralService::class);
    expect($service->attribute($referred, 'ZZZZZZZZ'))->toBeFalse()->and($service->attribute($referrer, $code))->toBeFalse()
        ->and($referred->fresh()->referred_by)->toBe($referrer->id);
})->group('FR-REF');

it('earns commission only when a referred participant\'s payment is settled, with rate, cap and validity', function () {
    referralSettings(['referral.max_commission' => 40_000]);
    Storage::fake('local');
    $referrer = signIn(RoleCode::Participant);
    $this->get(route('referral.mine'))->assertOk();
    $code = (string) asSystem(fn () => ReferralProfile::query()->whereKey($referrer->id)->value('code'));
    $this->post('/keluar');
    nextRequest();

    $referred = registerReferred($code);
    $course = makeCourse(['price' => 500_000]);
    loginAs($referred);
    nextRequest();
    $this->post(route('payments.checkout', $course['class']))->assertSessionHasNoErrors();
    $transaction = asSystem(fn () => PaymentTransaction::query()->where('user_id', $referred->id)->firstOrFail());
    $this->post(route('payments.proof', $transaction), ['proof' => UploadedFile::fake()->image('bukti.png', 300, 200)])->assertSessionHasNoErrors();
    expect(asSystem(fn () => ReferralCommission::query()->count()))->toBe(0); // belum lunas → belum ada komisi
    $this->post('/keluar');
    nextRequest();

    $finance = signIn(RoleCode::FinanceAdmin);
    confirmAccess();
    $this->post(route('admin.payments.settle', $transaction))->assertSessionHasNoErrors();
    $commission = asSystem(fn () => ReferralCommission::query()->where('referrer_id', $referrer->id)->firstOrFail());
    expect($commission->amount)->toBe(40_000) // 10% × 500.000 = 50.000, dibatasi 40.000
        ->and($commission->rate_percent)->toBe(10)->and($commission->status)->toBe('pending')->and($commission->referred_user_id)->toBe($referred->id)
        ->and(DB::table('notifications')->where('user_id', $referrer->id)->where('title', 'Komisi referral diterima')->count())->toBe(1);

    // Laporan referral & detail + ekspor CSV.
    $this->get(route('admin.reports.referral'))->assertOk()->assertSee($code)->assertSee('Rp40.000');
    $this->get(route('admin.reports.referral.show', $referrer))->assertOk()->assertSee('Rp40.000')->assertSee('Tandai Dibayar');
    $csv = $this->get(route('admin.reports.referral.export'))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect((string) $csv->streamedContent())->toContain($code)->toContain('40000');

    // Pencairan: pengaju ≠ penerima, butuh rekening, tercatat sebagai satu batch.
    $this->post(route('admin.reports.referral.payout', $referrer), ['reference' => 'TRF-2026-0001', 'note' => 'Transfer BCA'])->assertSessionHasNoErrors();
    $commission = asSystem(fn () => $commission->fresh());
    expect($commission->status)->toBe('paid')->and($commission->payout_id)->not->toBeNull()
        ->and(asSystem(fn () => (int) DB::table('referral_payouts')->where('referrer_id', $referrer->id)->value('amount')))->toBe(40_000)
        ->and(DB::table('audit_logs')->where('action', 'referral.payout_recorded')->count())->toBe(1);
    $this->post(route('admin.reports.referral.payout', $referrer), ['reference' => 'TRF-2026-0002'])->assertSessionHasErrors('reference');
    $this->post('/keluar');
    nextRequest();

    // Referrer melihat komisi & statusnya; peserta lain tidak melihat apa pun (RLS).
    loginAs($referrer);
    nextRequest();
    $this->get(route('referral.mine'))->assertOk()->assertSee('Rp40.000')->assertSee('Sudah Dibayar')->assertSee('TRF-2026-0001');
    $this->post(route('referral.account'), ['bank_name' => 'Bank Uji', 'bank_account' => '9876543210', 'bank_account_name' => 'Referrer Uji'])->assertSessionHasNoErrors();
    expect(asSystem(fn () => ReferralProfile::query()->whereKey($referrer->id)->firstOrFail()->bankAccountMasked()))->toBe('••••••3210');
    $this->post('/keluar');
    nextRequest();
    signIn(RoleCode::Participant);
    expect(ReferralCommission::query()->count())->toBe(0);
})->group('FR-REF', 'FR-PAY');

it('does not pay commission when the program is disabled, the referral has expired, or for self-settlement by the referrer', function () {
    referralSettings(['referral.enabled' => false]);
    Storage::fake('local');
    $referrer = signIn(RoleCode::Participant);
    $this->get(route('referral.mine'))->assertOk()->assertSee('tidak aktif');
    $code = (string) asSystem(fn () => ReferralProfile::query()->whereKey($referrer->id)->value('code'));
    $this->post('/keluar');
    nextRequest();

    // Program nonaktif: registrasi dengan kode tidak dikaitkan.
    $first = registerReferred($code, 'satu@contoh.test');
    expect($first->referred_by)->toBeNull();

    referralSettings(['referral.validity_months' => 1]);
    $second = registerReferred($code, 'dua@contoh.test');
    expect($second->referred_by)->toBe($referrer->id);
    asSystem(fn () => $second->forceFill(['referred_at' => now()->subMonths(2)])->save()); // referral kedaluwarsa

    $course = makeCourse(['price' => 300_000]);
    loginAs($second);
    nextRequest();
    $this->post(route('payments.checkout', $course['class']))->assertSessionHasNoErrors();
    $transaction = asSystem(fn () => PaymentTransaction::query()->where('user_id', $second->id)->firstOrFail());
    $this->post(route('payments.proof', $transaction), ['proof' => UploadedFile::fake()->image('bukti.png', 300, 200)])->assertSessionHasNoErrors();
    $this->post('/keluar');
    nextRequest();
    signIn(RoleCode::FinanceAdmin);
    confirmAccess();
    $this->post(route('admin.payments.settle', $transaction))->assertSessionHasNoErrors();
    expect(asSystem(fn () => ReferralCommission::query()->count()))->toBe(0);

    // Referrer tidak boleh mencairkan komisinya sendiri walau punya hak (SoD di layanan & basis data).
    $service = app(ReferralService::class);
    $superAdmin = makeUser(RoleCode::SuperAdmin);
    expect(fn () => $service->payout($superAdmin, $superAdmin, 'REF', null))->toThrow(ValidationException::class);
})->group('FR-REF', 'SEC-AUTHZ');

it('reports per organization with enrollments, pass rate, certificates and revenue for a period', function () {
    Storage::fake('local');
    $this->artisan('stu:certificate-defaults')->assertSuccessful();
    $this->artisan('stu:demo-content')->assertSuccessful();
    $this->artisan('stu:simulate')->assertSuccessful();
    $organization = makeOrganization();
    // Kaitkan sebagian enrollment simulasi ke organisasi agar muncul di laporan.
    asSystem(fn () => DB::table('enrollments')->whereIn('status', ['passed', 'cancelled'])->update(['organization_id' => $organization->id]));

    signIn(RoleCode::AcademicAdmin);
    $index = $this->get(route('admin.reports.organizations'))->assertOk()->assertSee($organization->name);
    $index->assertSee('100%'); // 5 lulus, 0 gagal
    $this->get(route('admin.reports.organizations.show', $organization))->assertOk()->assertSee('Rina Kartika')->assertSee('Lulus');
    $csv = $this->get(route('admin.reports.organizations.export', ['dari' => now()->subYear()->toDateString(), 'sampai' => now()->toDateString()]))->assertOk();
    $content = (string) $csv->streamedContent();
    expect($content)->toContain($organization->code)->toContain('Tingkat Kelulusan');
    // Periode tanpa data → nol.
    $this->get(route('admin.reports.organizations', ['dari' => '2020-01-01', 'sampai' => '2020-01-31']))->assertOk()->assertDontSee('100%');
    // Tanpa hak platform → ditolak.
    $this->post('/keluar');
    nextRequest();
    signIn(RoleCode::Participant);
    $this->get(route('admin.reports.organizations'))->assertNotFound();
})->group('FR-RPT');
