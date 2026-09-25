<?php

declare(strict_types=1);

namespace App\Modules\Enrollment\Services;

use App\Modules\Access\RoleCode;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Notification\Services\Notifier;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Enrollment & mesin status (FR-ENR-001/002/004, FR-CLS-002). Kuota dikurangi dengan
 * UPDATE bersyarat dalam transaksi yang sama dengan pembuatan enrollment → tidak pernah
 * terlampaui walau pendaftaran bersamaan; CHECK di basis data sebagai jaring pengaman.
 */
final class EnrollmentService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly Notifier $notifier,
        private readonly TenantContext $tenant,
    ) {}

    /** @throws ValidationException */
    public function enrollSelf(User $user, CourseClass $class): Enrollment
    {
        $program = $class->program;
        if (! $user->hasRole(RoleCode::Participant)) {
            throw ValidationException::withMessages(['class' => 'Hanya akun peserta yang dapat mendaftar pelatihan.']);
        }
        if (! $program->isPublished() || ! $class->isEnrollmentOpen()) {
            throw ValidationException::withMessages(['class' => 'Pendaftaran kelas ini sedang tidak dibuka.']);
        }
        if (! $program->isFree()) {
            throw ValidationException::withMessages(['class' => 'Program berbayar didaftarkan lewat halaman pembayaran.']);
        }
        $this->assertOrganizationAllowed($user, $class);

        return $this->create($user, $class, 'self', null, null);
    }

    /**
     * Pendaftaran mandiri ke program berbayar: kursi dipesan, enrollment menunggu pembayaran.
     * Hanya dipanggil PaymentService (yang membuat tagihannya dalam transaksi yang sama).
     */
    public function enrollAwaitingPayment(User $user, CourseClass $class): Enrollment
    {
        $program = $class->program;
        if (! $user->hasRole(RoleCode::Participant)) {
            throw ValidationException::withMessages(['class' => 'Hanya akun peserta yang dapat mendaftar pelatihan.']);
        }
        if (! $program->isPublished() || ! $class->isEnrollmentOpen()) {
            throw ValidationException::withMessages(['class' => 'Pendaftaran kelas ini sedang tidak dibuka.']);
        }
        if ($program->isFree()) {
            throw ValidationException::withMessages(['class' => 'Program ini gratis; gunakan pendaftaran biasa.']);
        }
        $this->assertOrganizationAllowed($user, $class);

        return $this->create($user, $class, 'payment', null, null, 'awaiting_payment');
    }

    /** Pendaftaran oleh admin (mis. program ditanggung organisasi). */
    public function enrollByAdmin(User $actor, User $user, CourseClass $class, string $reason): Enrollment
    {
        if (! $user->hasRole(RoleCode::Participant) || ! $user->isActive()) {
            throw ValidationException::withMessages(['email' => 'Pengguna harus akun peserta yang aktif.']);
        }
        if (! in_array($class->status, ['open', 'running'], true) || ! $class->program->isPublished()) {
            throw ValidationException::withMessages(['email' => 'Kelas harus berstatus dibuka/berjalan dan programnya terbit.']);
        }
        $this->assertOrganizationAllowed($user, $class);

        return $this->create($user, $class, 'admin', $actor, $reason);
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    public function transition(Enrollment $enrollment, string $to, ?User $actor, ?string $reason = null, array $attributes = []): void
    {
        $from = $enrollment->status;
        if (! in_array($to, Enrollment::TRANSITIONS[$from] ?? [], true)) {
            throw new InvalidArgumentException("Transisi enrollment {$from} → {$to} tidak sah.");
        }

        DB::transaction(function () use ($enrollment, $from, $to, $actor, $reason, $attributes): void {
            $updated = Enrollment::query()->whereKey($enrollment->id)->where('status', $from)
                ->update($attributes + ['status' => $to, 'updated_at' => now()]);
            if ($updated !== 1) {
                throw ValidationException::withMessages(['status' => 'Status enrollment sudah berubah. Muat ulang halaman.']);
            }
            $this->history($enrollment->id, $from, $to, $actor, $reason);

            if (in_array($to, ['cancelled'], true)) {
                CourseClass::query()->whereKey($enrollment->course_class_id)->where('enrolled_count', '>', 0)->decrement('enrolled_count');
            }
        });

        $enrollment->refresh();
    }

    public function cancel(Enrollment $enrollment, User $actor, string $reason): void
    {
        $this->transition($enrollment, 'cancelled', $actor, $reason);
        $this->failPendingPayment($enrollment, $actor, $reason);
        $this->audit->record('enrollment.cancelled', $actor, 'enrollment', $enrollment->id, ['status' => 'cancelled'], $reason, $enrollment->organization_id);
        $by = $actor->id === $enrollment->user_id ? 'atas permintaan Anda' : 'oleh admin';
        $this->notifier->send($enrollment->user, 'enrollment', 'Enrollment dibatalkan', 'Pendaftaran Anda pada '.$enrollment->program->name.' dibatalkan '.$by.'.', '/peserta/pembelajaran', email: true);
    }

    private function create(User $user, CourseClass $class, string $source, ?User $actor, ?string $reason, string $status = 'enrolled'): Enrollment
    {
        $organizationId = $this->activeOrganizationId($user);

        try {
            $enrollment = DB::transaction(function () use ($user, $class, $source, $actor, $reason, $organizationId, $status): Enrollment {
                // Kursi diambil atomik; gagal bila kuota penuh (FR-CLS-002).
                $seat = CourseClass::query()->whereKey($class->id)
                    ->whereColumn('enrolled_count', '<', 'quota')
                    ->increment('enrolled_count');
                if ($seat !== 1) {
                    throw ValidationException::withMessages(['class' => 'Kuota kelas sudah penuh.']);
                }

                $enrollment = new Enrollment;
                $enrollment->forceFill([
                    'id' => (string) Str::uuid7(),
                    'organization_id' => $organizationId,
                    'user_id' => $user->id,
                    'course_class_id' => $class->id,
                    'program_id' => $class->program_id,
                    'status' => $status,
                    'source' => $source,
                    'enrolled_at' => $status === 'enrolled' ? now() : null,
                ])->save();
                $this->history($enrollment->id, null, $status, $actor ?? $user, $reason);
                $this->audit->record('enrollment.created', $actor ?? $user, 'enrollment', $enrollment->id, [
                    'course_class_id' => $class->id, 'source' => $source, 'participant_id' => $user->id,
                ], $reason, $organizationId);

                return $enrollment;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['class' => 'Anda sudah memiliki enrollment aktif untuk program ini.']);
        }

        if ($status !== 'enrolled') {
            return $enrollment; // pemberitahuan tagihan dikirim PaymentService
        }

        $this->notifier->send($user, 'enrollment', 'Pendaftaran berhasil', 'Anda terdaftar di '.$class->program->name.' ('.$class->batch_name.'). Selamat belajar!', '/peserta/kelas/'.$enrollment->id, email: true);

        return $enrollment;
    }

    private function assertOrganizationAllowed(User $user, CourseClass $class): void
    {
        if ($class->restricted_organization_id !== null && $this->activeOrganizationId($user) !== $class->restricted_organization_id) {
            throw ValidationException::withMessages(['class' => 'Kelas ini khusus untuk anggota organisasi tertentu.']);
        }
    }

    /** Organisasi peserta saat mendaftar (immutable pada enrollment): hanya keanggotaan aktif. */
    private function activeOrganizationId(User $user): ?string
    {
        if ($user->primary_organization_id === null) {
            return null;
        }

        $active = $this->tenant->runAsSystem(fn (): bool => DB::table('organization_members')
            ->where('user_id', $user->id)
            ->where('organization_id', $user->primary_organization_id)
            ->where('status', 'active')
            ->exists());

        return $active ? $user->primary_organization_id : null;
    }

    /** Enrollment dibatalkan saat tagihan transfer manual masih menunggu → tagihan ditutup (failed). */
    private function failPendingPayment(Enrollment $enrollment, User $actor, string $reason): void
    {
        $transactionId = DB::table('payment_transactions')->where('enrollment_id', $enrollment->id)->where('status', 'pending')->value('id');
        if (! is_string($transactionId)) {
            return;
        }
        DB::table('payment_transactions')->where('id', $transactionId)->where('status', 'pending')->update([
            'status' => 'failed', 'needs_review' => false, 'review_note' => 'Pendaftaran dibatalkan: '.$reason, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('payment_events')->insert([
            'id' => (string) Str::uuid7(), 'payment_transaction_id' => $transactionId, 'source' => $actor->id === $enrollment->user_id ? 'participant' : 'admin',
            'event' => 'failed', 'payload' => json_encode(['reason' => 'Pendaftaran dibatalkan: '.$reason]), 'actor_id' => $actor->id, 'created_at' => now(),
        ]);
        $this->audit->record('payment.failed', $actor, 'payment_transaction', $transactionId, ['cause' => 'enrollment_cancelled'], $reason, $enrollment->organization_id);
    }

    private function history(string $enrollmentId, ?string $from, string $to, ?User $actor, ?string $reason): void
    {
        DB::table('enrollment_status_histories')->insert([
            'id' => (string) Str::uuid7(),
            'enrollment_id' => $enrollmentId,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor?->id,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
