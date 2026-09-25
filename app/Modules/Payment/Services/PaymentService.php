<?php

declare(strict_types=1);

namespace App\Modules\Payment\Services;

use App\Modules\Access\RoleCode;
use App\Modules\Access\Services\ApprovalWorkflow;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Services\EnrollmentService;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Learning\Models\MediaAsset;
use App\Modules\Learning\Services\MediaStorage;
use App\Modules\Notification\Services\Notifier;
use App\Modules\Payment\Models\PaymentEvent;
use App\Modules\Payment\Models\PaymentTransaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Pembayaran transfer manual tercatat (FR-PAY tahap A; docs/02 §22.3):
 * checkout → tagihan `pending` + enrollment `awaiting_payment` (kursi dipesan) → peserta unggah
 * bukti → Admin Keuangan konfirmasi lunas (di atas ambang: persetujuan admin kedua) →
 * enrollment `enrolled` + nomor invoice. Tanpa bukti sampai batas waktu → kedaluwarsa, kursi dilepas.
 * Status hanya berubah lewat layanan ini; setiap langkah dicatat di payment_events & audit.
 */
final class PaymentService
{
    public function __construct(
        private readonly EnrollmentService $enrollments,
        private readonly MediaStorage $media,
        private readonly AuditLogger $audit,
        private readonly Notifier $notifier,
        private readonly ApprovalWorkflow $approvals,
    ) {}

    /** Rekening tujuan sudah diisi di Pengaturan Sistem → Pembayaran. */
    public static function isConfigured(): bool
    {
        return trim((string) setting('payment.bank_name')) !== '' && trim((string) setting('payment.bank_account_number')) !== '';
    }

    /** @return array{bank_name: string, account_number: string, account_name: string, instructions: string} */
    public static function bankAccount(): array
    {
        return [
            'bank_name' => (string) setting('payment.bank_name'),
            'account_number' => (string) setting('payment.bank_account_number'),
            'account_name' => (string) setting('payment.bank_account_name'),
            'instructions' => (string) setting('payment.instructions'),
        ];
    }

    public static function settleThreshold(): int
    {
        return max(0, (int) config('lms.payment_manual_settle_threshold'));
    }

    /** Daftar ke kelas berbayar: enrollment menunggu pembayaran + tagihan. */
    public function checkout(User $user, CourseClass $class): PaymentTransaction
    {
        $program = $class->program;
        if ($program->isFree()) {
            throw ValidationException::withMessages(['class' => 'Program ini gratis; gunakan pendaftaran biasa.']);
        }
        if (! self::isConfigured()) {
            throw ValidationException::withMessages(['class' => 'Pembayaran belum dibuka. Hubungi admin atau organisasi Anda untuk didaftarkan.']);
        }

        $hours = max(6, (int) config('lms.payment_deadline_hours'));
        $transaction = DB::transaction(function () use ($user, $class, $program, $hours): PaymentTransaction {
            $enrollment = $this->enrollments->enrollAwaitingPayment($user, $class);

            $transaction = new PaymentTransaction;
            $transaction->forceFill([
                'id' => (string) Str::uuid7(),
                'organization_id' => $enrollment->organization_id,
                'user_id' => $user->id,
                'program_id' => $program->id,
                'course_class_id' => $class->id,
                'enrollment_id' => $enrollment->id,
                'order_id' => 'STU-'.Str::ulid(),
                'list_price' => $program->price,
                'discount_amount' => 0,
                'gross_amount' => $program->price,
                'status' => 'pending',
                'expires_at' => now()->addHours($hours),
            ])->save();
            $this->event($transaction, 'participant', 'created', $user, ['amount' => $program->price, 'expires_at' => $transaction->expires_at->toIso8601String()]);
            $this->audit->record('payment.created', $user, 'payment_transaction', $transaction->id, [
                'order_id' => $transaction->order_id, 'amount' => $program->price, 'enrollment_id' => $enrollment->id,
            ], null, $enrollment->organization_id);

            return $transaction;
        });

        $this->notifier->send($user, 'payment', 'Selesaikan pembayaran', 'Tagihan '.$transaction->amountLabel().' untuk '.$program->name.' menunggu transfer sebelum '
            .$transaction->expires_at->timezone(display_tz())->translatedFormat('d M Y H:i').' '.tz_label().'.', '/peserta/pembayaran/'.$transaction->id, true);

        return $transaction;
    }

    /**
     * Peserta mengunggah bukti transfer (gambar/PDF ≤ 5 MB; dipindai) beserta data invoice opsional.
     *
     * @param  array{billing_name?: string|null, billing_tax_id?: string|null, billing_address?: string|null}  $billing
     */
    public function submitProof(PaymentTransaction $transaction, User $user, UploadedFile $file, ?string $note, array $billing = []): void
    {
        if ($transaction->user_id !== $user->id || ! $transaction->acceptsProof()) {
            throw ValidationException::withMessages(['proof' => 'Tagihan ini tidak menerima bukti transfer lagi.']);
        }

        $asset = $this->media->store($file, 'attachment', $user, 'proof');
        $previous = $transaction->proof_media_asset_id;

        DB::transaction(function () use ($transaction, $user, $asset, $note, $billing): void {
            $updated = PaymentTransaction::query()->whereKey($transaction->id)->where('status', 'pending')->update([
                'proof_media_asset_id' => $asset->id,
                'proof_note' => $note,
                'proof_submitted_at' => now(),
                'needs_review' => true,
                'review_note' => null,
                'billing_name' => self::blank($billing['billing_name'] ?? null),
                'billing_tax_id_encrypted' => ($tax = self::blank($billing['billing_tax_id'] ?? null)) === null ? null : Crypt::encryptString($tax),
                'billing_address_encrypted' => ($address = self::blank($billing['billing_address'] ?? null)) === null ? null : Crypt::encryptString($address),
                'updated_at' => now(),
            ]);
            if ($updated !== 1) {
                throw ValidationException::withMessages(['proof' => 'Status tagihan sudah berubah. Muat ulang halaman.']);
            }
            $this->event($transaction, 'participant', 'proof_submitted', $user, ['media_asset_id' => $asset->id, 'sha256' => $asset->sha256]);
            $this->audit->record('payment.proof_submitted', $user, 'payment_transaction', $transaction->id, ['media_asset_id' => $asset->id], null, $transaction->organization_id);
        });
        $this->forgetAsset($previous);
        $transaction->refresh();

        $this->notifier->send($user, 'payment', 'Bukti transfer diterima', 'Bukti untuk tagihan '.$transaction->order_id.' sedang diverifikasi Admin Keuangan.', '/peserta/pembayaran/'.$transaction->id);
        foreach ($this->financeAdmins() as $admin) {
            $this->notifier->send($admin, 'payment', 'Bukti transfer perlu diverifikasi', $user->name.' mengunggah bukti untuk '.$transaction->amountLabel().' ('.$transaction->order_id.').', '/admin/pembayaran/'.$transaction->id);
        }
    }

    /** Admin menolak bukti (mis. nominal/tujuan salah); peserta dapat mengunggah ulang selama belum kedaluwarsa. */
    public function rejectProof(PaymentTransaction $transaction, User $actor, string $reason): void
    {
        $this->assertStaffAction($transaction, $actor);
        if (! $transaction->needs_review) {
            throw ValidationException::withMessages(['reason' => 'Tidak ada bukti yang menunggu verifikasi.']);
        }

        DB::transaction(function () use ($transaction, $actor, $reason): void {
            PaymentTransaction::query()->whereKey($transaction->id)->where('status', 'pending')->update([
                'needs_review' => false, 'review_note' => $reason, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'updated_at' => now(),
            ]);
            $this->event($transaction, 'admin', 'proof_rejected', $actor, ['reason' => $reason]);
            $this->audit->record('payment.proof_rejected', $actor, 'payment_transaction', $transaction->id, [], $reason, $transaction->organization_id);
        });
        $transaction->refresh();

        $this->notifier->send($transaction->user, 'payment', 'Bukti transfer perlu diperbaiki', 'Bukti untuk tagihan '.$transaction->order_id.' ditolak: '.$reason.' Unggah ulang sebelum '
            .$transaction->expires_at->timezone(display_tz())->translatedFormat('d M Y H:i').' '.tz_label().'.', '/peserta/pembayaran/'.$transaction->id, true);
    }

    /**
     * Konfirmasi lunas oleh Admin Keuangan. Di atas ambang → permintaan persetujuan admin kedua
     * (maker–checker, FR-PAY-003); di bawah ambang → langsung lunas.
     *
     * @return 'settled'|'approval_requested'
     */
    public function settle(PaymentTransaction $transaction, User $actor, ?string $note): string
    {
        $this->assertStaffAction($transaction, $actor);
        if ($transaction->proof_media_asset_id === null) {
            throw ValidationException::withMessages(['note' => 'Belum ada bukti transfer yang dapat dikonfirmasi.']);
        }

        if ($transaction->gross_amount > self::settleThreshold()) {
            $request = $this->approvals->request('payment.settle_manual', 'payment_transaction', $transaction->id, [
                'order_id' => $transaction->order_id, 'amount' => $transaction->gross_amount, 'participant' => $transaction->user->name,
            ], $note !== null && $note !== '' ? $note : 'Konfirmasi pembayaran '.$transaction->amountLabel(), $actor);
            PaymentTransaction::query()->whereKey($transaction->id)->update(['approval_request_id' => $request->id, 'updated_at' => now()]);
            $this->event($transaction, 'admin', 'approval_requested', $actor, ['approval_request_id' => $request->id]);
            $transaction->refresh();

            return 'approval_requested';
        }

        $this->finalizeSettlement($transaction, $actor, $note);

        return 'settled';
    }

    /** Eksekusi lunas (langsung, atau oleh pemutus persetujuan kedua). */
    public function finalizeSettlement(PaymentTransaction $transaction, User $settler, ?string $note, ?User $requester = null): void
    {
        if ($settler->id === $transaction->user_id) {
            throw ValidationException::withMessages(['note' => 'Peserta tidak dapat mengonfirmasi pembayarannya sendiri.']);
        }

        DB::transaction(function () use ($transaction, $settler, $note, $requester): void {
            $locked = PaymentTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isPending()) {
                throw ValidationException::withMessages(['note' => 'Tagihan sudah '.$locked->statusLabel().'.']);
            }
            $invoice = $this->nextInvoiceNumber();
            PaymentTransaction::query()->whereKey($transaction->id)->update([
                'status' => 'settled', 'settled_by' => $settler->id, 'settled_at' => now(), 'invoice_number' => $invoice,
                'needs_review' => false, 'review_note' => self::blank($note), 'reviewed_by' => $settler->id, 'reviewed_at' => now(), 'updated_at' => now(),
            ]);

            $enrollment = Enrollment::query()->whereKey($transaction->enrollment_id)->firstOrFail();
            $this->enrollments->transition($enrollment, 'enrolled', $settler, 'Pembayaran dikonfirmasi ('.$invoice.')', ['enrolled_at' => now()]);

            $this->event($transaction, $requester === null ? 'admin' : 'approval', 'settled', $settler, ['invoice_number' => $invoice, 'requested_by' => $requester?->id]);
            $this->audit->record('payment.settled', $settler, 'payment_transaction', $transaction->id, [
                'order_id' => $transaction->order_id, 'invoice_number' => $invoice, 'amount' => $transaction->gross_amount, 'requested_by' => $requester?->id,
            ], self::blank($note), $transaction->organization_id);
        });
        $transaction->refresh();

        $this->notifier->send($transaction->user, 'payment', 'Pembayaran dikonfirmasi', 'Pembayaran '.$transaction->amountLabel().' untuk '.$transaction->program->name
            .' telah diterima (invoice '.$transaction->invoice_number.'). Selamat belajar!', '/peserta/kelas/'.$transaction->enrollment_id, true);
    }

    /** Admin membatalkan tagihan (mis. peserta batal / bukti palsu): enrollment dibatalkan, kursi dilepas. */
    public function fail(PaymentTransaction $transaction, User $actor, string $reason): void
    {
        $this->assertStaffAction($transaction, $actor);

        DB::transaction(function () use ($transaction, $actor, $reason): void {
            $updated = PaymentTransaction::query()->whereKey($transaction->id)->where('status', 'pending')->update([
                'status' => 'failed', 'needs_review' => false, 'review_note' => $reason, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'updated_at' => now(),
            ]);
            if ($updated !== 1) {
                throw ValidationException::withMessages(['reason' => 'Tagihan sudah '.$transaction->fresh()?->statusLabel().'.']);
            }
            $this->event($transaction, 'admin', 'failed', $actor, ['reason' => $reason]);
            $this->audit->record('payment.failed', $actor, 'payment_transaction', $transaction->id, ['order_id' => $transaction->order_id], $reason, $transaction->organization_id);

            $enrollment = Enrollment::query()->whereKey($transaction->enrollment_id)->firstOrFail();
            if ($enrollment->status === 'awaiting_payment') {
                $this->enrollments->cancel($enrollment, $actor, 'Tagihan ditolak: '.$reason);
            }
        });
        $transaction->refresh();
    }

    /** Tagihan tanpa bukti yang melewati batas waktu → kedaluwarsa; enrollment dibatalkan (kursi dilepas). */
    public function expire(): int
    {
        $count = 0;
        PaymentTransaction::query()->where('status', 'pending')->where('needs_review', false)->where('expires_at', '<', now())
            ->orderBy('expires_at')->limit(500)->get()
            ->each(function (PaymentTransaction $transaction) use (&$count): void {
                DB::transaction(function () use ($transaction): void {
                    $updated = PaymentTransaction::query()->whereKey($transaction->id)->where('status', 'pending')->where('needs_review', false)
                        ->update(['status' => 'expired', 'updated_at' => now()]);
                    if ($updated !== 1) {
                        return;
                    }
                    $this->event($transaction, 'system', 'expired', null, ['expires_at' => $transaction->expires_at->toIso8601String()]);
                    $this->audit->record('payment.expired', null, 'payment_transaction', $transaction->id, ['order_id' => $transaction->order_id], null, $transaction->organization_id);

                    $enrollment = Enrollment::query()->whereKey($transaction->enrollment_id)->first();
                    if ($enrollment !== null && $enrollment->status === 'awaiting_payment') {
                        $this->enrollments->transition($enrollment, 'cancelled', null, 'Batas waktu pembayaran terlewati');
                        $this->audit->record('enrollment.cancelled', null, 'enrollment', $enrollment->id, ['status' => 'cancelled', 'cause' => 'payment_expired'], null, $enrollment->organization_id);
                    }
                });
                $this->notifier->send($transaction->user, 'payment', 'Tagihan kedaluwarsa', 'Tagihan '.$transaction->order_id.' melewati batas waktu tanpa bukti transfer. Silakan daftar ulang bila masih berminat.', '/peserta/program', true);
                $count++;
            });

        return $count;
    }

    /** Nomor invoice INV/TAHUN/BULAN/URUT5, atomik per bulan. */
    private function nextInvoiceNumber(): string
    {
        $now = now()->timezone(display_tz());
        DB::table('invoice_sequences')->insertOrIgnore(['year' => (int) $now->format('Y'), 'month' => (int) $now->format('n'), 'last_value' => 0]);
        $row = DB::selectOne('UPDATE invoice_sequences SET last_value = last_value + 1 WHERE year = ? AND month = ? RETURNING last_value', [(int) $now->format('Y'), (int) $now->format('n')]);

        return sprintf('INV/%s/%s/%05d', $now->format('Y'), $now->format('m'), (int) $row->last_value);
    }

    /** @param  array<string, mixed>  $payload */
    private function event(PaymentTransaction $transaction, string $source, string $event, ?User $actor, array $payload): void
    {
        $record = new PaymentEvent;
        $record->forceFill([
            'id' => (string) Str::uuid7(),
            'payment_transaction_id' => $transaction->id,
            'source' => $source,
            'event' => $event,
            'payload' => $payload,
            'actor_id' => $actor?->id,
            'created_at' => now(),
        ])->save();
    }

    private function assertStaffAction(PaymentTransaction $transaction, User $actor): void
    {
        if (! $actor->hasPermission('payment.mark_paid_manual') || $actor->id === $transaction->user_id) {
            throw ValidationException::withMessages(['reason' => 'Anda tidak berwenang memproses tagihan ini.']);
        }
        if (! $transaction->isPending()) {
            throw ValidationException::withMessages(['reason' => 'Tagihan sudah '.$transaction->statusLabel().'.']);
        }
    }

    /** @return list<User> */
    private function financeAdmins(): array
    {
        return array_values(User::query()->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->whereIn('code', [RoleCode::FinanceAdmin->value, RoleCode::SuperAdmin->value]))
            ->limit(20)->get()->all());
    }

    private function forgetAsset(?string $assetId): void
    {
        if ($assetId === null) {
            return;
        }
        $asset = MediaAsset::query()->whereKey($assetId)->first();
        if ($asset !== null) {
            Storage::disk((string) config('media.disk'))->delete($asset->storage_key);
            $asset->delete();
        }
    }

    private static function blank(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
