<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Models\Program;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Services\EnrollmentService;
use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Learning\Models\Module;
use App\Modules\Payment\Services\PaymentService;
use App\Support\Database\Like;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Katalog program publik & peserta (FR-CAT-003/004, docs/08 PST-02/03, BARU-13/14).
 * Hanya program `published`; program lain → 404. Silabus hanya judul modul/bab.
 */
final class CatalogController
{
    public function publicIndex(Request $request): View
    {
        return view('catalog.public-index', $this->listing($request));
    }

    public function publicShow(string $slug): View
    {
        return view('catalog.public-show', $this->detail($slug));
    }

    public function participantIndex(Request $request): View
    {
        return view('catalog.participant-index', $this->listing($request));
    }

    public function participantShow(Request $request, string $slug): View
    {
        /** @var User $user */
        $user = $request->user();
        $detail = $this->detail($slug);
        $active = Enrollment::query()->with('courseClass:id,batch_name')->where('user_id', $user->id)->where('program_id', $detail['program']->id)
            ->whereNotIn('status', ['failed', 'cancelled'])->first();

        return view('catalog.participant-show', $detail + [
            'activeEnrollment' => $active,
            'pendingPayment' => $active?->status === 'awaiting_payment' ? $active->payment : null,
            'paymentOpen' => PaymentService::isConfigured(),
        ]);
    }

    public function enroll(Request $request, CourseClass $class, EnrollmentService $enrollments): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $enrollment = $enrollments->enrollSelf($user, $class->load('program'));
        if ($enrollment->status === 'applied') {
            return redirect()->route('learning.index')->with('status', 'Pendaftaran diajukan. Anda akan diberi tahu setelah disetujui trainer/admin.');
        }

        return redirect()->route('learning.classroom', $enrollment)->with('status', 'Pendaftaran berhasil. Selamat belajar!');
    }

    /** @return array<string, mixed> */
    private function listing(Request $request): array
    {
        $search = Str::limit(trim((string) $request->query('q', '')), 100, '');
        $category = array_key_exists((string) $request->query('kategori'), Program::CATEGORIES) ? (string) $request->query('kategori') : null;
        $level = array_key_exists((string) $request->query('level'), Program::LEVELS) ? (string) $request->query('level') : null;
        $mode = array_key_exists((string) $request->query('mode'), Program::MODES) ? (string) $request->query('mode') : null;
        $price = in_array($request->query('harga'), ['gratis', 'berbayar'], true) ? (string) $request->query('harga') : null;

        /** @var LengthAwarePaginator<int, Program> $programs */
        $programs = Program::query()->where('status', 'published')
            ->when($search !== '', fn ($query) => $query->where(fn ($where) => $where->where('name', 'ilike', Like::contains($search))->orWhere('provider_name', 'ilike', Like::contains($search))))
            ->when($category !== null, fn ($query) => $query->where('category', $category))
            ->when($level !== null, fn ($query) => $query->where('level', $level))
            ->when($mode !== null, fn ($query) => $query->where('default_mode', $mode))
            ->when($price === 'gratis', fn ($query) => $query->where('price', 0))
            ->when($price === 'berbayar', fn ($query) => $query->where('price', '>', 0))
            ->withCount(['classes as open_classes_count' => fn ($query) => $query->where('status', 'open')])
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return compact('programs', 'search', 'category', 'level', 'mode', 'price');
    }

    /** @return array{program: Program, classes: Collection<int, CourseClass>, syllabus: Collection<int, Module>} */
    private function detail(string $slug): array
    {
        /** @var Program $program */
        $program = Program::query()->where('slug', $slug)->where('status', 'published')->firstOrFail();
        $classes = CourseClass::query()->with('trainers:id,name')->where('program_id', $program->id)
            ->whereIn('status', ['open', 'running'])->orderBy('starts_on')->get();
        $syllabusClass = $classes->first();
        $syllabus = $syllabusClass === null ? collect() : Module::query()->with(['chapters' => fn ($query) => $query->select(['id', 'module_id', 'title', 'position'])->orderBy('position')])
            ->where('course_class_id', $syllabusClass->id)->orderBy('position')->get(['id', 'title', 'position']);

        return ['program' => $program, 'classes' => $classes, 'syllabus' => $syllabus];
    }
}
