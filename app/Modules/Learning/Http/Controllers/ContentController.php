<?php

declare(strict_types=1);

namespace App\Modules\Learning\Http\Controllers;

use App\Modules\Assessment\Models\Assessment;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\Chapter;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\Module;
use App\Modules\Learning\Services\ClassAccess;
use App\Modules\Learning\Services\MediaStorage;
use App\Support\Content\RichText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Struktur konten Modul → Bab → Lesson (FR-CNT-001/002). Urutan diatur dengan tombol
 * naik/turun (aksesibel, tanpa drag & drop berbasis skrip inline). Objek bersarang selalu
 * diperiksa milik kelas di URL.
 */
final class ContentController
{
    public function __construct(
        private readonly ClassAccess $access,
        private readonly AuditLogger $audit,
    ) {}

    public function storeModule(Request $request, CourseClass $class): RedirectResponse
    {
        $user = $this->authorize($request, $class);
        $data = $request->validate(['title' => ['required', 'string', 'min:2', 'max:200']]);
        $module = new Module;
        $module->forceFill(['course_class_id' => $class->id, 'title' => trim($data['title']), 'position' => (int) Module::query()->where('course_class_id', $class->id)->max('position') + 1])->save();
        $this->audit->record('content.module_created', $user, 'course_class', $class->id, ['module_id' => $module->id, 'title' => $module->title]);

        return back()->with('status', 'Modul ditambahkan.');
    }

    public function updateModule(Request $request, CourseClass $class, Module $module): RedirectResponse
    {
        $this->authorize($request, $class);
        $this->owns($class, $module->course_class_id);
        $data = $request->validate(['title' => ['required', 'string', 'min:2', 'max:200']]);
        $module->forceFill(['title' => trim($data['title'])])->save();

        return back()->with('status', 'Modul diperbarui.');
    }

    public function destroyModule(Request $request, CourseClass $class, Module $module): RedirectResponse
    {
        $user = $this->authorize($request, $class);
        $this->owns($class, $module->course_class_id);
        $this->assertNoProgress(Lesson::query()->whereIn('chapter_id', Chapter::query()->where('module_id', $module->id)->select('id'))->pluck('id'));
        $module->delete();
        $this->audit->record('content.module_deleted', $user, 'course_class', $class->id, ['module_id' => $module->id]);

        return back()->with('status', 'Modul dihapus.');
    }

    public function moveModule(Request $request, CourseClass $class, Module $module): RedirectResponse
    {
        $this->authorize($request, $class);
        $this->owns($class, $module->course_class_id);
        $this->swap($module, Module::query()->where('course_class_id', $class->id), (string) $request->input('direction'));

        return back();
    }

    public function storeChapter(Request $request, CourseClass $class, Module $module): RedirectResponse
    {
        $this->authorize($request, $class);
        $this->owns($class, $module->course_class_id);
        $data = $request->validate(['title' => ['required', 'string', 'min:2', 'max:200']]);
        $chapter = new Chapter;
        $chapter->forceFill(['module_id' => $module->id, 'title' => trim($data['title']), 'position' => (int) Chapter::query()->where('module_id', $module->id)->max('position') + 1])->save();

        return back()->with('status', 'Bab ditambahkan.');
    }

    public function updateChapter(Request $request, CourseClass $class, Chapter $chapter): RedirectResponse
    {
        $this->authorize($request, $class);
        $this->owns($class, $chapter->module->course_class_id);
        $data = $request->validate(['title' => ['required', 'string', 'min:2', 'max:200']]);
        $chapter->forceFill(['title' => trim($data['title'])])->save();

        return back()->with('status', 'Bab diperbarui.');
    }

    public function destroyChapter(Request $request, CourseClass $class, Chapter $chapter): RedirectResponse
    {
        $this->authorize($request, $class);
        $this->owns($class, $chapter->module->course_class_id);
        $this->assertNoProgress(Lesson::query()->where('chapter_id', $chapter->id)->pluck('id'));
        $chapter->delete();

        return back()->with('status', 'Bab dihapus.');
    }

    public function moveChapter(Request $request, CourseClass $class, Chapter $chapter): RedirectResponse
    {
        $this->authorize($request, $class);
        $this->owns($class, $chapter->module->course_class_id);
        $this->swap($chapter, Chapter::query()->where('module_id', $chapter->module_id), (string) $request->input('direction'));

        return back();
    }

    public function createLesson(Request $request, CourseClass $class, Chapter $chapter): View
    {
        $user = $this->authorize($request, $class);
        $this->owns($class, $chapter->module->course_class_id);
        $type = array_key_exists((string) $request->query('tipe'), Lesson::TYPES) ? (string) $request->query('tipe') : 'text';

        return view('classes.lesson-form', [
            'class' => $class, 'chapter' => $chapter, 'lesson' => new Lesson(['type' => $type, 'is_required' => true]),
            'workspace' => $this->access->workspaceFor($user), 'quizzes' => $this->quizzes($class),
        ]);
    }

    public function storeLesson(Request $request, CourseClass $class, Chapter $chapter, MediaStorage $media): RedirectResponse
    {
        $user = $this->authorize($request, $class);
        $this->owns($class, $chapter->module->course_class_id);
        $type = (string) $request->input('type');
        $data = $this->validateLesson($request, $class, $type, creating: true);

        $lesson = DB::transaction(function () use ($data, $chapter, $type, $request, $media, $user): Lesson {
            $lesson = new Lesson;
            $lesson->forceFill($this->lessonAttributes($data, $type) + [
                'chapter_id' => $chapter->id,
                'type' => $type,
                'position' => (int) Lesson::query()->where('chapter_id', $chapter->id)->max('position') + 1,
                'published_at' => now(),
            ]);
            if (in_array($type, ['video', 'pdf'], true)) {
                $file = $request->file('file');
                if (! $file instanceof UploadedFile) {
                    throw ValidationException::withMessages(['file' => 'Berkas wajib diunggah.']);
                }
                $lesson->media_asset_id = $media->store($file, $type, $user)->id;
            }
            $lesson->save();

            return $lesson;
        });
        $this->audit->record('content.lesson_created', $user, 'course_class', $class->id, ['lesson_id' => $lesson->id, 'type' => $type]);

        return redirect()->route('classes.manage', $class)->with('status', 'Lesson "'.$lesson->title.'" ditambahkan.');
    }

    public function editLesson(Request $request, CourseClass $class, Lesson $lesson): View
    {
        $user = $this->authorize($request, $class);
        $this->owns($class, $lesson->courseClassId());

        return view('classes.lesson-form', [
            'class' => $class, 'chapter' => $lesson->chapter, 'lesson' => $lesson,
            'workspace' => $this->access->workspaceFor($user), 'quizzes' => $this->quizzes($class),
        ]);
    }

    public function updateLesson(Request $request, CourseClass $class, Lesson $lesson, MediaStorage $media): RedirectResponse
    {
        $user = $this->authorize($request, $class);
        $this->owns($class, $lesson->courseClassId());
        $data = $this->validateLesson($request, $class, $lesson->type, creating: false);

        DB::transaction(function () use ($lesson, $data, $request, $media, $user): void {
            $lesson->forceFill($this->lessonAttributes($data, $lesson->type) + ['version' => $lesson->version + 1]);
            $file = $request->file('file');
            if (in_array($lesson->type, ['video', 'pdf'], true) && $file instanceof UploadedFile) {
                $lesson->media_asset_id = $media->store($file, $lesson->type, $user)->id;
            }
            $lesson->save();
        });
        $this->audit->record('content.lesson_updated', $user, 'course_class', $class->id, ['lesson_id' => $lesson->id, 'version' => $lesson->version]);

        return redirect()->route('classes.manage', $class)->with('status', 'Lesson diperbarui.');
    }

    public function destroyLesson(Request $request, CourseClass $class, Lesson $lesson): RedirectResponse
    {
        $user = $this->authorize($request, $class);
        $this->owns($class, $lesson->courseClassId());
        $this->assertNoProgress(collect([$lesson->id]));
        $lesson->delete();
        $this->audit->record('content.lesson_deleted', $user, 'course_class', $class->id, ['lesson_id' => $lesson->id]);

        return back()->with('status', 'Lesson dihapus.');
    }

    public function moveLesson(Request $request, CourseClass $class, Lesson $lesson): RedirectResponse
    {
        $this->authorize($request, $class);
        $this->owns($class, $lesson->courseClassId());
        $this->swap($lesson, Lesson::query()->where('chapter_id', $lesson->chapter_id), (string) $request->input('direction'));

        return back();
    }

    private function authorize(Request $request, CourseClass $class): User
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($this->access->canView($user, $class), 404);
        abort_unless($this->access->canManageContent($user, $class), 403);

        return $user;
    }

    private function owns(CourseClass $class, string $classId): void
    {
        abort_unless($class->id === $classId, 404);
    }

    /** @param Collection<int, mixed> $lessonIds */
    private function assertNoProgress(Collection $lessonIds): void
    {
        if ($lessonIds->isNotEmpty() && DB::table('lesson_progress')->whereIn('lesson_id', $lessonIds)->exists()) {
            throw ValidationException::withMessages(['content' => 'Konten sudah dipelajari peserta sehingga tidak dapat dihapus. Jadikan lesson "tidak wajib" sebagai gantinya.']);
        }
    }

    /**
     * @template TModel of Model
     *
     * @param  TModel  $item
     * @param  Builder<TModel>  $siblings
     */
    private function swap(Model $item, Builder $siblings, string $direction): void
    {
        $position = (int) $item->getAttribute('position');
        $neighbor = $direction === 'up'
            ? (clone $siblings)->where('position', '<', $position)->orderByDesc('position')->first()
            : (clone $siblings)->where('position', '>', $position)->orderBy('position')->first();
        if ($neighbor === null) {
            return;
        }

        DB::transaction(function () use ($item, $neighbor, $position): void {
            $item->forceFill(['position' => (int) $neighbor->getAttribute('position')])->save();
            $neighbor->forceFill(['position' => $position])->save();
        });
    }

    /** @return array<string, mixed> */
    private function validateLesson(Request $request, CourseClass $class, string $type, bool $creating): array
    {
        $rules = [
            'type' => [$creating ? 'required' : 'nullable', Rule::in(array_keys(Lesson::TYPES))],
            'title' => ['required', 'string', 'min:2', 'max:200'],
            'is_required' => ['nullable', 'boolean'],
        ];
        $rules += match ($type) {
            'video' => [
                'file' => [$creating ? 'required' : 'nullable', 'file'],
                'duration_minutes' => ['required', 'integer', 'between:0,600'],
                'duration_seconds_part' => ['required', 'integer', 'between:0,59'],
                'allow_download' => ['nullable', 'boolean'],
            ],
            'pdf' => ['file' => [$creating ? 'required' : 'nullable', 'file'], 'allow_download' => ['nullable', 'boolean']],
            'text' => ['body_md' => ['required', 'string', 'max:50000']],
            'link' => ['external_url' => ['required', 'string', 'max:500', 'url:https']],
            'quiz' => ['assessment_id' => ['required', 'uuid', Rule::exists('assessments', 'id')->where('course_class_id', $class->id)->where('kind', 'quiz')]],
            default => throw ValidationException::withMessages(['type' => 'Tipe lesson tidak valid.']),
        };

        $data = $request->validate($rules, [
            'external_url.url' => 'Tautan harus diawali https://.',
            'assessment_id.exists' => 'Pilih kuis dari kelas ini.',
        ]);

        if ($type === 'link' && ! self::linkAllowed((string) $data['external_url'])) {
            throw ValidationException::withMessages(['external_url' => 'Domain tautan tidak termasuk daftar yang diizinkan.']);
        }
        if ($type === 'video' && (int) $data['duration_minutes'] * 60 + (int) $data['duration_seconds_part'] <= 0) {
            throw ValidationException::withMessages(['duration_minutes' => 'Durasi video wajib diisi.']);
        }

        return $data;
    }

    public static function linkAllowed(string $url): bool
    {
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return false;
        }
        foreach (config('media.link_allowlist', []) as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function lessonAttributes(array $data, string $type): array
    {
        return [
            'title' => trim((string) $data['title']),
            'is_required' => (bool) ($data['is_required'] ?? false),
            'body_md' => $type === 'text' ? $data['body_md'] : null,
            'body_html' => $type === 'text' ? RichText::toHtml((string) $data['body_md']) : null,
            'external_url' => $type === 'link' ? $data['external_url'] : null,
            'assessment_id' => $type === 'quiz' ? $data['assessment_id'] : null,
            'allow_download' => in_array($type, ['video', 'pdf'], true) && (bool) ($data['allow_download'] ?? false),
            'duration_seconds' => $type === 'video' ? (int) $data['duration_minutes'] * 60 + (int) $data['duration_seconds_part'] : null,
        ];
    }

    /** @return Collection<int, Assessment> */
    private function quizzes(CourseClass $class): Collection
    {
        return Assessment::query()->where('course_class_id', $class->id)->where('kind', 'quiz')->orderBy('title')->get(['id', 'title']);
    }
}
