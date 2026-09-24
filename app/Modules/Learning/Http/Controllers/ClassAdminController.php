<?php

declare(strict_types=1);

namespace App\Modules\Learning\Http\Controllers;

use App\Modules\Access\RoleCode;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Catalog\Models\Program;
use App\Modules\Enrollment\Services\EnrollmentService;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Notification\Services\Notifier;
use App\Modules\Organization\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Pengaturan kelas/batch oleh staf platform (FR-CLS-001, docs/08 ADM-05): periode,
 * kuota, jendela pendaftaran, trainer, organisasi terbatas, syarat kelulusan, status.
 */
final class ClassAdminController
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function create(Program $program): View
    {
        abort_if($program->status === 'archived', 409, 'Program diarsipkan.');

        return view('admin.classes.form', ['program' => $program, 'class' => new CourseClass(['quota' => 30, 'mode' => $program->default_mode]), 'organizations' => $this->organizations()]);
    }

    public function store(Request $request, Program $program): RedirectResponse
    {
        abort_if($program->status === 'archived', 409, 'Program diarsipkan.');
        $data = $this->validated($request);
        /** @var User $actor */
        $actor = $request->user();

        $class = DB::transaction(function () use ($data, $program, $actor): CourseClass {
            $class = new CourseClass;
            $class->forceFill($this->attributes($data) + ['program_id' => $program->id, 'status' => 'draft', 'created_by' => $actor->id])->save();
            $this->audit->record('course_class.created', $actor, 'course_class', $class->id, ['program_id' => $program->id, 'batch_name' => $class->batch_name]);

            return $class;
        });

        return redirect()->route('classes.manage', $class)->with('status', 'Kelas dibuat sebagai draf. Tambahkan trainer, materi, dan asesmen sebelum membuka pendaftaran.');
    }

    public function edit(CourseClass $class): View
    {
        return view('admin.classes.form', ['program' => $class->program, 'class' => $class, 'organizations' => $this->organizations()]);
    }

    public function update(Request $request, CourseClass $class): RedirectResponse
    {
        $data = $this->validated($request, $class);
        /** @var User $actor */
        $actor = $request->user();
        $class->forceFill($this->attributes($data));
        $changes = $class->getDirty();
        DB::transaction(function () use ($class, $actor, $changes): void {
            $class->save();
            $this->audit->record('course_class.updated', $actor, 'course_class', $class->id, $changes);
        });

        return redirect()->route('classes.manage', $class)->with('status', 'Pengaturan kelas disimpan.');
    }

    public function changeStatus(Request $request, CourseClass $class): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(CourseClass::STATUSES))]]);
        if (! in_array($data['status'], CourseClass::TRANSITIONS[$class->status], true)) {
            throw ValidationException::withMessages(['status' => 'Perubahan status tidak sah.']);
        }
        if ($data['status'] === 'open' && ! $class->program->isPublished()) {
            throw ValidationException::withMessages(['status' => 'Program harus terbit sebelum pendaftaran kelas dibuka.']);
        }

        /** @var User $actor */
        $actor = $request->user();
        $from = $class->status;
        DB::transaction(function () use ($class, $data, $actor, $from): void {
            $class->forceFill(['status' => $data['status']])->save();
            $this->audit->record('course_class.status_changed', $actor, 'course_class', $class->id, ['status' => ['from' => $from, 'to' => $data['status']]]);
        });

        return back()->with('status', 'Status kelas: '.CourseClass::STATUSES[$data['status']].'.');
    }

    public function addTrainer(Request $request, CourseClass $class, Notifier $notifier): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:254'], 'role' => ['required', Rule::in(['lead', 'assistant'])]]);
        /** @var User|null $trainer */
        $trainer = User::query()->where('email', User::normalizeEmail($data['email']))->first();
        if ($trainer === null || ! $trainer->hasRole(RoleCode::Trainer)) {
            throw ValidationException::withMessages(['email' => 'Pengguna dengan peran Trainer tidak ditemukan. Undang lewat menu Pengguna terlebih dahulu.']);
        }

        /** @var User $actor */
        $actor = $request->user();
        DB::transaction(function () use ($class, $trainer, $data, $actor): void {
            DB::table('class_trainers')->upsert([['course_class_id' => $class->id, 'user_id' => $trainer->id, 'role' => $data['role'], 'created_at' => now()]], ['course_class_id', 'user_id'], ['role']);
            $this->audit->record('course_class.trainer_assigned', $actor, 'course_class', $class->id, ['trainer_id' => $trainer->id, 'role' => $data['role']]);
        });
        $notifier->send($trainer, 'content', 'Ditugaskan mengampu kelas', 'Anda ditugaskan mengampu '.$class->label().'.', '/kelola/kelas/'.$class->id);

        return back()->with('status', $trainer->name.' ditambahkan sebagai trainer.');
    }

    public function removeTrainer(Request $request, CourseClass $class, User $user): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        DB::transaction(function () use ($class, $user, $actor): void {
            DB::table('class_trainers')->where('course_class_id', $class->id)->where('user_id', $user->id)->delete();
            $this->audit->record('course_class.trainer_removed', $actor, 'course_class', $class->id, ['trainer_id' => $user->id]);
        });

        return back()->with('status', 'Trainer dilepas dari kelas.');
    }

    public function enrollParticipant(Request $request, CourseClass $class, EnrollmentService $enrollments): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:254'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        /** @var User|null $participant */
        $participant = User::query()->where('email', User::normalizeEmail($data['email']))->first();
        if ($participant === null) {
            throw ValidationException::withMessages(['email' => 'Peserta tidak ditemukan.']);
        }

        /** @var User $actor */
        $actor = $request->user();
        $enrollments->enrollByAdmin($actor, $participant, $class, $data['reason']);

        return back()->with('status', $participant->name.' didaftarkan ke kelas ini.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?CourseClass $class = null): array
    {
        $minQuota = max(1, $class === null ? 1 : $class->enrolled_count);

        return $request->validate([
            'batch_name' => ['required', 'string', 'min:2', 'max:120'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'enroll_opens_at' => ['nullable', 'date'],
            'enroll_closes_at' => ['nullable', 'date', 'after:enroll_opens_at'],
            'quota' => ['required', 'integer', 'min:'.$minQuota, 'max:5000'],
            'mode' => ['required', Rule::in(['online', 'offline', 'hybrid'])],
            'location' => ['nullable', 'string', 'max:200'],
            'restricted_organization_id' => ['nullable', 'uuid', Rule::exists('organizations', 'id')],
            'min_final_score' => ['nullable', 'numeric', 'between:0,100'],
            'require_final_exam' => ['nullable', 'boolean'],
        ], ['quota.min' => 'Kuota tidak boleh lebih kecil dari jumlah peserta terdaftar ('.$minQuota.').']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'batch_name' => trim((string) $data['batch_name']),
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'enroll_opens_at' => $data['enroll_opens_at'] ?? null,
            'enroll_closes_at' => $data['enroll_closes_at'] ?? null,
            'quota' => (int) $data['quota'],
            'mode' => $data['mode'],
            'location' => $data['location'] ?? null,
            'restricted_organization_id' => $data['restricted_organization_id'] ?? null,
            'completion_rules' => array_filter([
                'min_final_score' => isset($data['min_final_score']) && $data['min_final_score'] !== '' ? (float) $data['min_final_score'] : null,
                'require_final_exam' => (bool) ($data['require_final_exam'] ?? false),
            ], fn ($value) => $value !== null),
        ];
    }

    /** @return Collection<int, Organization> */
    private function organizations(): Collection
    {
        return Organization::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']);
    }
}
