<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Services;

use App\Modules\Assessment\Models\Assignment;
use App\Modules\Assessment\Models\AssignmentSubmission;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Services\ProgressService;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Services\MediaStorage;
use App\Modules\Notification\Services\Notifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Pengumpulan & penilaian tugas. */
final class AssignmentService
{
    public function __construct(
        private readonly MediaStorage $media,
        private readonly Notifier $notifier,
        private readonly AuditLogger $audit,
        private readonly ProgressService $progress,
    ) {}

    /** @throws ValidationException */
    public function submit(Enrollment $enrollment, Assignment $assignment, ?string $text, ?UploadedFile $file): AssignmentSubmission
    {
        if (! $enrollment->isActive() || $assignment->course_class_id !== $enrollment->course_class_id) {
            throw ValidationException::withMessages(['submission' => 'Tugas tidak tersedia untuk enrollment ini.']);
        }
        if (! $assignment->acceptsSubmissions()) {
            throw ValidationException::withMessages(['submission' => 'Tenggat tugas sudah lewat dan pengumpulan terlambat tidak diizinkan.']);
        }
        $existing = AssignmentSubmission::query()->where('assignment_id', $assignment->id)->where('enrollment_id', $enrollment->id)->first();
        if ($existing !== null && $existing->status === 'graded') {
            throw ValidationException::withMessages(['submission' => 'Tugas sudah dinilai dan tidak dapat dikumpulkan ulang.']);
        }
        $text = trim((string) $text);
        if ($text === '' && $file === null && $existing?->media_asset_id === null) {
            throw ValidationException::withMessages(['submission' => 'Isi jawaban teks atau unggah berkas.']);
        }
        if ($file !== null && ! $assignment->allow_file) {
            throw ValidationException::withMessages(['file' => 'Tugas ini tidak menerima berkas.']);
        }
        if ($text !== '' && ! $assignment->allow_text) {
            throw ValidationException::withMessages(['text_answer' => 'Tugas ini hanya menerima berkas.']);
        }

        $asset = $file !== null ? $this->media->store($file, 'submission', $enrollment->user, 'file') : null;

        return DB::transaction(function () use ($existing, $enrollment, $assignment, $text, $asset): AssignmentSubmission {
            $submission = $existing ?? new AssignmentSubmission;
            $submission->forceFill([
                'organization_id' => $enrollment->organization_id,
                'course_class_id' => $enrollment->course_class_id,
                'assignment_id' => $assignment->id,
                'enrollment_id' => $enrollment->id,
                'user_id' => $enrollment->user_id,
                'media_asset_id' => $asset !== null ? $asset->id : $existing?->media_asset_id,
                'text_answer' => $text !== '' ? mb_substr($text, 0, 20000) : ($existing?->text_answer),
                'submitted_at' => now(),
                'is_late' => $assignment->isOverdue(),
                'status' => 'submitted',
                'score' => null, 'feedback' => $existing?->status === 'returned' ? $existing->feedback : null, 'rubric_scores' => null, 'graded_by' => null, 'graded_at' => null,
                'version' => $existing === null ? 1 : $existing->version + 1,
            ])->save();

            $this->audit->record('assignment.submitted', $enrollment->user, 'assignment_submission', $submission->id, ['assignment_id' => $assignment->id, 'version' => $submission->version, 'late' => $submission->is_late], organizationId: $enrollment->organization_id);

            return $submission;
        });
    }

    /**
     * @param  array<string, float>|null  $rubricScores  nama kriteria => skor
     */
    public function grade(AssignmentSubmission $submission, User $grader, ?float $score, ?string $feedback, ?array $rubricScores, bool $returnForRevision = false): void
    {
        if ($submission->user_id === $grader->id) {
            throw ValidationException::withMessages(['score' => 'Anda tidak dapat menilai tugas milik sendiri.']);
        }
        $assignment = $submission->assignment;
        $max = (float) $assignment->max_score;

        if ($rubricScores !== null && $assignment->rubric !== null && $assignment->rubric !== []) {
            $sum = 0.0;
            $clean = [];
            foreach ($assignment->rubric as $criterion) {
                $value = max(0.0, min((float) $criterion['max'], (float) ($rubricScores[$criterion['name']] ?? 0)));
                $clean[$criterion['name']] = $value;
                $sum += $value;
            }
            $rubricMax = $assignment->rubricMax();
            $score = $rubricMax > 0 ? round($sum * $max / $rubricMax, 2) : 0.0;
            $rubricScores = $clean;
        }
        if (! $returnForRevision && $score === null) {
            throw ValidationException::withMessages(['score' => 'Isi skor.']);
        }
        $score = $score === null ? null : max(0.0, min($max, $score));

        DB::transaction(function () use ($submission, $grader, $score, $feedback, $rubricScores, $returnForRevision): void {
            $submission->forceFill([
                'status' => $returnForRevision ? 'returned' : 'graded',
                'score' => $returnForRevision ? null : $score,
                'feedback' => $feedback !== null && trim($feedback) !== '' ? mb_substr(trim($feedback), 0, 5000) : null,
                'rubric_scores' => $returnForRevision ? null : $rubricScores,
                'graded_by' => $grader->id,
                'graded_at' => now(),
            ])->save();
            $this->audit->record($returnForRevision ? 'assignment.returned' : 'assignment.graded', $grader, 'assignment_submission', $submission->id, ['score' => $score], organizationId: $submission->organization_id);
        });

        $enrollment = $submission->enrollment->load('user');
        $this->notifier->send($enrollment->user, 'grading', $returnForRevision ? 'Tugas dikembalikan: '.$submission->assignment->title : 'Nilai tugas: '.$submission->assignment->title,
            $returnForRevision ? 'Trainer meminta revisi. Baca umpan balik lalu kumpulkan ulang.' : 'Skor '.fmt_score($score).' dari '.fmt_score($submission->assignment->max_score).'.',
            '/peserta/kelas/'.$enrollment->id.'/tugas/'.$submission->assignment_id, email: true);

        if (! $returnForRevision) {
            $this->progress->recalculate($enrollment);
        }
    }

    /**
     * Status tugas wajib untuk syarat kelulusan: [terpenuhi, total wajib].
     *
     * @return array{0: int, 1: int}
     */
    public static function requiredCounts(Enrollment $enrollment): array
    {
        $required = DB::table('assignments')->where('course_class_id', $enrollment->course_class_id)->where('is_required', true)->get(['id', 'passing_score']);
        if ($required->isEmpty()) {
            return [0, 0];
        }
        $graded = DB::table('assignment_submissions')->where('enrollment_id', $enrollment->id)->where('status', 'graded')->whereIn('assignment_id', $required->pluck('id'))->pluck('score', 'assignment_id');
        $met = 0;
        foreach ($required as $assignment) {
            if (! $graded->has($assignment->id)) {
                continue;
            }
            if ($assignment->passing_score === null || (float) $graded->get($assignment->id) >= (float) $assignment->passing_score) {
                $met++;
            }
        }

        return [$met, $required->count()];
    }
}
