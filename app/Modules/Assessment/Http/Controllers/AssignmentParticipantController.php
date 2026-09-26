<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Http\Controllers;

use App\Modules\Assessment\Models\Assignment;
use App\Modules\Assessment\Models\AssignmentSubmission;
use App\Modules\Assessment\Services\AssignmentService;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Services\MediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

/** Halaman tugas peserta: instruksi, pengumpulan (teks/berkas), nilai & umpan balik. */
final class AssignmentParticipantController
{
    public function __construct(private readonly AssignmentService $assignments) {}

    public function show(Request $request, Enrollment $enrollment, Assignment $assignment, MediaStorage $media): View
    {
        $user = $this->own($request, $enrollment);
        abort_unless($enrollment->canAccessContent() && $assignment->course_class_id === $enrollment->course_class_id, 404);
        $submission = AssignmentSubmission::query()->with('media')->where('assignment_id', $assignment->id)->where('enrollment_id', $enrollment->id)->first();
        $fileUrl = $submission?->media !== null && $submission->media->isServable() ? $media->signedUrl($submission->media, $user, 'submission:'.$submission->id) : null;

        return view('learning.assignment', ['enrollment' => $enrollment->load('program'), 'assignment' => $assignment, 'submission' => $submission, 'fileUrl' => $fileUrl,
            'canSubmit' => $enrollment->isActive() && $assignment->acceptsSubmissions() && $submission?->status !== 'graded']);
    }

    public function submit(Request $request, Enrollment $enrollment, Assignment $assignment): RedirectResponse
    {
        $this->own($request, $enrollment);
        abort_unless($assignment->course_class_id === $enrollment->course_class_id, 404);
        $data = $request->validate(['text_answer' => ['nullable', 'string', 'max:20000'], 'file' => ['nullable', 'file']]);
        $file = $request->file('file');
        $submission = $this->assignments->submit($enrollment, $assignment, $data['text_answer'] ?? null, $file instanceof UploadedFile ? $file : null);

        return back()->with('status', 'Tugas dikumpulkan (versi '.$submission->version.')'.($submission->is_late ? ' — terlambat' : '').'.');
    }

    private function own(Request $request, Enrollment $enrollment): User
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($enrollment->user_id === $user->id, 404);

        return $user;
    }
}
