<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Catalog\Models\Program;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Siklus hidup program (FR-CAT-002): draft → in_review → published → archived.
 * Penerbitan harus oleh pengguna yang berbeda dari pengaju review (SoD; dijaga juga
 * constraint DB programs_review_sod_check).
 */
final class ProgramLifecycle
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function submit(Program $program, User $actor): void
    {
        $this->transition($program, 'draft', 'in_review', $actor, ['submitted_by' => $actor->id, 'reviewed_by' => null], 'program.submitted_for_review');
    }

    /** @throws ValidationException */
    public function publish(Program $program, User $reviewer): void
    {
        if ($program->submitted_by === $reviewer->id) {
            throw ValidationException::withMessages(['status' => 'Program harus direview & diterbitkan oleh pengguna yang berbeda dari pengaju.']);
        }

        $this->transition($program, 'in_review', 'published', $reviewer, ['reviewed_by' => $reviewer->id, 'published_at' => $program->published_at ?? now()], 'program.published');
    }

    public function returnToDraft(Program $program, User $reviewer, string $reason): void
    {
        $this->transition($program, 'in_review', 'draft', $reviewer, ['reviewed_by' => null], 'program.review_rejected', $reason);
    }

    public function archive(Program $program, User $actor, string $reason): void
    {
        $this->transition($program, 'published', 'archived', $actor, [], 'program.archived', $reason);
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    private function transition(Program $program, string $from, string $to, User $actor, array $attributes, string $action, ?string $reason = null): void
    {
        DB::transaction(function () use ($program, $from, $to, $actor, $attributes, $action, $reason): void {
            $updated = Program::query()->whereKey($program->id)->where('status', $from)->update($attributes + ['status' => $to, 'updated_at' => now()]);
            if ($updated !== 1) {
                throw ValidationException::withMessages(['status' => 'Status program sudah berubah. Muat ulang halaman.']);
            }
            $this->audit->record($action, $actor, 'program', $program->id, ['status' => ['from' => $from, 'to' => $to]], $reason);
        });

        $program->refresh();
    }
}
