<?php

declare(strict_types=1);

use App\Modules\Access\Models\ApprovalRequest;
use App\Modules\Access\RoleCode;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Payment\Models\PaymentTransaction;
use App\Modules\Payment\Services\PaymentService;
use App\Modules\Settings\Services\SystemSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
 * Pembayaran transfer manual tercatat (FR-PAY tahap A): checkout → bukti → verifikasi
 * (maker–checker di atas ambang) → enrollment aktif + invoice; kedaluwarsa & pembatalan.
 */

function configurePayments(int $threshold = 1_000_000): void
{
    DB::table('system_settings')->upsert([
        ['key' => 'payment.bank_name', 'value' => json_encode('Bank Uji'), 'updated_at' => now()],
        ['key' => 'payment.bank_account_number', 'value' => json_encode('1234567890'), 'updated_at' => now()],
        ['key' => 'payment.bank_account_name', 'value' => json_encode('PT Uji Sejahtera'), 'updated_at' => now()],
        ['key' => 'payment.manual_settle_threshold', 'value' => json_encode($threshold), 'updated_at' => now()],
    ], ['key'], ['value', 'updated_at']);
    Cache::forget('system_settings.v1');
    SystemSettings::applyToConfig();
}

function proofImage(): UploadedFile
{
    $image = imagecreatetruecolor(300, 200);
    imagefill($image, 0, 0, (int) imagecolorallocate($image, 240, 240, 240));
    ob_start();
    imagepng($image);

    return UploadedFile::fake()->createWithContent('bukti.png', (string) ob_get_clean());
}

/** @return array{participant: User, transaction: PaymentTransaction, class: CourseClass} */
function checkoutPaid(int $price = 500_000): array
{
    configurePayments();
    Storage::fake('local');
    $course = makeCourse(['price' => $price]);
    $participant = signIn(RoleCode::Participant);
    test()->post(route('payments.checkout', $course['class']))->assertSessionHasNoErrors()->assertRedirect();
    $transaction = asSystem(fn () => PaymentTransaction::query()->where('user_id', $participant->id)->firstOrFail());

    return ['participant' => $participant, 'transaction' => $transaction, 'class' => $course['class']];
}

it('reserves a seat and opens a pending bill when a participant joins a paid program', function () {
    ['participant' => $participant, 'transaction' => $transaction, 'class' => $class] = checkoutPaid();

    $enrollment = asSystem(fn () => Enrollment::query()->whereKey($transaction->enrollment_id)->firstOrFail());
    expect($enrollment->status)->toBe('awaiting_payment')->and($enrollment->source)->toBe('payment')->and($enrollment->enrolled_at)->toBeNull()
        ->and($transaction->status)->toBe('pending')->and($transaction->gross_amount)->toBe(500_000)
        ->and($transaction->order_id)->toStartWith('STU-')->and($transaction->expires_at->isFuture())->toBeTrue()
        ->and(asSystem(fn () => (int) DB::table('course_classes')->where('id', $class->id)->value('enrolled_count')))->toBe(1)
        ->and(DB::table('notifications')->where('user_id', $participant->id)->where('category', 'payment')->count())->toBe(1);

    // Halaman tagihan menampilkan rekening & batas waktu; katalog mengarahkan ke pembayaran.
    $this->get(route('payments.show', $transaction))->assertOk()->assertSee('1234567890')->assertSee('Rp500.000')->assertSee('Unggah bukti transfer');
    $this->get(route('catalog.participant.show', $class->program->slug))->assertOk()->assertSee('Selesaikan Pembayaran');
    $this->get(route('learning.index'))->assertOk()->assertSee('Selesaikan Pembayaran');
    $this->get(route('payments.mine'))->assertOk()->assertSee($transaction->order_id);

    // Pendaftaran gratis biasa ditolak untuk program berbayar; checkout ganda ditolak (satu enrollment aktif per program).
    $this->post(route('catalog.enroll', $class))->assertSessionHasErrors('class');
    $this->post(route('payments.checkout', $class))->assertSessionHasErrors('class');
})->group('FR-PAY', 'FR-ENR');

it('refuses checkout when the bank account is not configured', function () {
    $course = makeCourse(['price' => 250_000]);
    signIn(RoleCode::Participant);

    $this->post(route('payments.checkout', $course['class']))->assertSessionHasErrors('class');
    $this->get(route('catalog.participant.show', $course['program']->slug))->assertOk()->assertSee('Pembayaran belum dibuka');
    expect(asSystem(fn () => PaymentTransaction::query()->count()))->toBe(0);
})->group('FR-PAY');

it('validates the proof file, keeps it private to the owner, and lets finance settle below the threshold with an invoice', function () {
    ['participant' => $participant, 'transaction' => $transaction] = checkoutPaid();

    $this->post(route('payments.proof', $transaction), ['proof' => UploadedFile::fake()->createWithContent('bukti.txt', 'bukan gambar')])->assertSessionHasErrors('proof');
    $this->post(route('payments.proof', $transaction), ['proof' => proofImage(), 'note' => 'Transfer BCA a.n. Peserta', 'billing_name' => 'PT Peserta Jaya', 'billing_tax_id' => '01.234.567.8-901.000'])
        ->assertSessionHasNoErrors()->assertRedirect(route('payments.show', $transaction));
    $transaction = asSystem(fn () => $transaction->fresh());
    expect($transaction->needs_review)->toBeTrue()->and($transaction->proof_media_asset_id)->not->toBeNull()
        ->and($transaction->billingTaxId())->toBe('01.234.567.8-901.000')
        ->and((string) asSystem(fn () => DB::table('payment_transactions')->where('id', $transaction->id)->value('billing_tax_id_encrypted')))->not->toContain('01.234');
    $this->get(route('payments.proof.view', $transaction))->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->get(route('payments.show', $transaction))->assertOk()->assertSee('sedang diverifikasi');

    // Peserta lain tidak melihat tagihan maupun buktinya (RLS + kepemilikan).
    $this->post('/keluar');
    nextRequest();
    signIn(RoleCode::Participant);
    $this->get(route('payments.show', $transaction))->assertNotFound();
    $this->get(route('payments.proof.view', $transaction))->assertNotFound();
    $this->get(route('payments.invoice', $transaction))->assertNotFound();
    $this->post('/keluar');
    nextRequest();

    // Admin Keuangan: antrean verifikasi → konfirmasi lunas (di bawah ambang → langsung).
    $finance = signIn(RoleCode::FinanceAdmin);
    confirmAccess();
    $this->get(route('admin.payments.index', ['tinjau' => 1]))->assertOk()->assertSee($transaction->order_id)->assertSee('perlu verifikasi');
    $this->get(route('admin.payments.show', $transaction))->assertOk()->assertSee('Konfirmasi Lunas');
    $this->get(route('admin.payments.proof', $transaction))->assertOk();
    $this->post(route('admin.payments.settle', $transaction), ['note' => 'Mutasi 25/09 ref 8812'])->assertSessionHasNoErrors()->assertRedirect(route('admin.payments.show', $transaction));

    $transaction = asSystem(fn () => $transaction->fresh());
    $enrollment = asSystem(fn () => Enrollment::query()->whereKey($transaction->enrollment_id)->firstOrFail());
    expect($transaction->status)->toBe('settled')->and($transaction->settled_by)->toBe($finance->id)
        ->and($transaction->invoice_number)->toMatch('/^INV\/\d{4}\/\d{2}\/00001$/')
        ->and($enrollment->status)->toBe('enrolled')->and($enrollment->enrolled_at)->not->toBeNull()
        ->and(DB::table('audit_logs')->where('action', 'payment.settled')->count())->toBe(1)
        ->and(DB::table('payment_events')->where('payment_transaction_id', $transaction->id)->pluck('event')->all())->toBe(['created', 'proof_submitted', 'settled']);
    $this->get(route('admin.payments.invoice', $transaction))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->post('/keluar');
    nextRequest();

    // Peserta: invoice PDF dan akses kelas.
    loginAs($participant);
    nextRequest();
    $response = $this->get(route('payments.invoice', $transaction))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');
    $this->get(route('payments.mine'))->assertOk()->assertSee('Lunas')->assertSee($transaction->invoice_number);
    $this->get(route('learning.classroom', $enrollment))->assertOk();
    $this->post(route('payments.proof', $transaction), ['proof' => proofImage()])->assertSessionHasErrors('proof');
})->group('FR-PAY', 'SEC-AUTHZ');

it('requires a second admin above the threshold and blocks the requester from deciding', function () {
    ['participant' => $participant, 'transaction' => $transaction] = checkoutPaid(2_000_000);
    $this->post(route('payments.proof', $transaction), ['proof' => proofImage()])->assertSessionHasNoErrors();
    $this->post('/keluar');
    nextRequest();

    $maker = signIn(RoleCode::FinanceAdmin);
    confirmAccess();
    $this->post(route('admin.payments.settle', $transaction), ['note' => 'Dana masuk 25/09'])->assertSessionHasNoErrors();
    $transaction = asSystem(fn () => $transaction->fresh());
    $approval = ApprovalRequest::query()->where('action', 'payment.settle_manual')->where('subject_id', $transaction->id)->firstOrFail();
    expect($transaction->status)->toBe('pending')->and($transaction->approval_request_id)->toBe($approval->id);
    $this->get(route('admin.payments.show', $transaction))->assertOk()->assertSee('Menunggu keputusan admin kedua');

    // Pengaju tidak boleh memutus; tombol konfirmasi ulang ditolak.
    $this->post(route('admin.second-approvals.decide', $approval), ['decision' => 'approve'])->assertSessionHasErrors();
    $this->post('/keluar');
    nextRequest();

    $checker = signIn(RoleCode::FinanceAdmin);
    confirmAccess();
    $this->post(route('admin.second-approvals.decide', $approval), ['decision' => 'approve', 'reason' => 'Mutasi cocok'])->assertSessionHasNoErrors();
    $transaction = asSystem(fn () => $transaction->fresh());
    expect($transaction->status)->toBe('settled')->and($transaction->settled_by)->toBe($checker->id)->and($transaction->settled_by)->not->toBe($maker->id)
        ->and(asSystem(fn () => Enrollment::query()->whereKey($transaction->enrollment_id)->value('status')))->toBe('enrolled')
        ->and(DB::table('notifications')->where('user_id', $participant->id)->where('title', 'Pembayaran dikonfirmasi')->count())->toBe(1);
})->group('FR-PAY', 'SEC-AUTHZ');

it('lets finance reject a proof for re-upload or cancel the bill, releasing the seat', function () {
    ['transaction' => $transaction, 'class' => $class] = checkoutPaid();
    $this->post(route('payments.proof', $transaction), ['proof' => proofImage()])->assertSessionHasNoErrors();
    $this->post('/keluar');
    nextRequest();

    signIn(RoleCode::FinanceAdmin);
    confirmAccess();
    $this->post(route('admin.payments.reject-proof', $transaction), ['reason' => 'abc'])->assertSessionHasErrors('reason');
    $this->post(route('admin.payments.reject-proof', $transaction), ['reason' => 'Nominal transfer kurang Rp50.000'])->assertSessionHasNoErrors();
    $transaction = asSystem(fn () => $transaction->fresh());
    expect($transaction->status)->toBe('pending')->and($transaction->needs_review)->toBeFalse()->and($transaction->review_note)->toContain('kurang');
    // Tanpa bukti yang menunggu, konfirmasi lunas tetap mungkin (bukti lama masih ada) tetapi penolakan ulang tidak.
    $this->post(route('admin.payments.reject-proof', $transaction), ['reason' => 'Coba lagi saja'])->assertSessionHasErrors('reason');

    $this->post(route('admin.payments.fail', $transaction), ['reason' => 'Peserta mengundurkan diri lewat telepon'])->assertSessionHasNoErrors();
    $transaction = asSystem(fn () => $transaction->fresh());
    expect($transaction->status)->toBe('failed')
        ->and(asSystem(fn () => Enrollment::query()->whereKey($transaction->enrollment_id)->value('status')))->toBe('cancelled')
        ->and(asSystem(fn () => (int) DB::table('course_classes')->where('id', $class->id)->value('enrolled_count')))->toBe(0);
    $this->post(route('admin.payments.settle', $transaction))->assertSessionHasErrors();
})->group('FR-PAY');

it('expires unpaid bills after the deadline but keeps bills awaiting review, and closes the bill when the participant cancels', function () {
    ['participant' => $participant, 'transaction' => $transaction, 'class' => $class] = checkoutPaid();
    asSystem(fn () => DB::table('payment_transactions')->where('id', $transaction->id)->update(['expires_at' => now()->subHour()]));

    $this->artisan('stu:payments-expire')->assertSuccessful()->expectsOutputToContain('Tagihan kedaluwarsa: 1');
    $transaction = asSystem(fn () => $transaction->fresh());
    expect($transaction->status)->toBe('expired')
        ->and(asSystem(fn () => Enrollment::query()->whereKey($transaction->enrollment_id)->value('status')))->toBe('cancelled')
        ->and(asSystem(fn () => (int) DB::table('course_classes')->where('id', $class->id)->value('enrolled_count')))->toBe(0);
    $this->get(route('payments.show', $transaction))->assertOk()->assertSee('Kedaluwarsa');
    $this->post(route('payments.proof', $transaction), ['proof' => proofImage()])->assertSessionHasErrors('proof');

    // Daftar ulang, unggah bukti, lalu lewat batas waktu: TIDAK kedaluwarsa karena menunggu verifikasi.
    $this->post(route('payments.checkout', $class))->assertSessionHasNoErrors();
    $second = asSystem(fn () => PaymentTransaction::query()->where('user_id', $participant->id)->where('status', 'pending')->firstOrFail());
    $this->post(route('payments.proof', $second), ['proof' => proofImage()])->assertSessionHasNoErrors();
    asSystem(fn () => DB::table('payment_transactions')->where('id', $second->id)->update(['expires_at' => now()->subHour()]));
    $this->artisan('stu:payments-expire')->assertSuccessful()->expectsOutputToContain('Tagihan kedaluwarsa: 0');
    expect(asSystem(fn () => $second->fresh()->status))->toBe('pending');

    // Peserta membatalkan pendaftaran yang menunggu pembayaran → tagihan ditutup.
    $this->post(route('learning.cancel', $second->enrollment_id))->assertSessionHasNoErrors();
    expect(asSystem(fn () => $second->fresh()->status))->toBe('failed')
        ->and(DB::table('payment_events')->where('payment_transaction_id', $second->id)->where('event', 'failed')->count())->toBe(1);
})->group('FR-PAY', 'FR-ENR');

it('exposes the payment settings tab with validation', function () {
    signIn(RoleCode::SuperAdmin);
    confirmAccess();

    $this->get(route('admin.settings.tab', 'pembayaran'))->assertOk()->assertSee('Rekening tujuan transfer');
    $this->put(route('admin.settings.update', 'pembayaran'), ['payment__bank_account_number' => 'abc', 'payment__deadline_hours' => 72, 'payment__manual_settle_threshold' => 1000000])
        ->assertSessionHasErrors('payment__bank_account_number');
    $this->put(route('admin.settings.update', 'pembayaran'), [
        'payment__bank_name' => 'Bank Uji', 'payment__bank_account_number' => '1234567890', 'payment__bank_account_name' => 'PT Uji',
        'payment__instructions' => 'Transfer tepat.', 'payment__deadline_hours' => 48, 'payment__manual_settle_threshold' => 750000,
        'invoice__tax_id' => '', 'invoice__footer_note' => 'Catatan.',
        'referral__enabled' => '1', 'referral__commission_percent' => 10, 'referral__max_commission' => 0, 'referral__validity_months' => 12, 'referral__terms' => 'Ketentuan.',
    ])->assertSessionHasNoErrors();
    expect(config('lms.payment_deadline_hours'))->toBe(48)->and(PaymentService::isConfigured())->toBeTrue();
})->group('FR-PAY', 'FR-SET-001');
