<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Catalog\Models\Program;
use App\Modules\Catalog\Services\ProgramLifecycle;
use App\Modules\Identity\Models\User;
use App\Support\Content\RichText;
use App\Support\Database\Like;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Master program (FR-CAT-001/002, docs/08 ADM-04). Program yang sedang direview terkunci;
 * penerbitan oleh reviewer berbeda.
 */
final class ProgramAdminController
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ProgramLifecycle $lifecycle,
    ) {}

    public function index(Request $request): View
    {
        $search = Str::limit(trim((string) $request->query('q', '')), 100, '');
        $status = array_key_exists((string) $request->query('status'), Program::STATUSES) ? (string) $request->query('status') : null;
        $category = array_key_exists((string) $request->query('kategori'), Program::CATEGORIES) ? (string) $request->query('kategori') : null;

        $programs = Program::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($where) => $where->where('name', 'ilike', Like::contains($search))->orWhere('short_code', strtoupper($search))))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($category !== null, fn ($query) => $query->where('category', $category))
            ->withCount('classes')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.programs.index', compact('programs', 'search', 'status', 'category'));
    }

    public function create(): View
    {
        return view('admin.programs.form', ['program' => new Program(['passing_score' => 70]), 'tags' => '']);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['short_code' => strtoupper(trim((string) $request->input('short_code')))]);
        $data = $request->validate($this->rules() + [
            'short_code' => ['required', 'string', 'regex:/^[A-Z0-9][A-Z0-9-]{1,15}$/', Rule::unique('programs', 'short_code')],
        ], $this->messages());

        /** @var User $actor */
        $actor = $request->user();
        $program = DB::transaction(function () use ($data, $actor): Program {
            $program = new Program;
            $program->forceFill($this->attributes($data) + [
                'short_code' => $data['short_code'],
                'slug' => $this->uniqueSlug($data['name']),
                'status' => 'draft',
                'created_by' => $actor->id,
            ])->save();
            $program->syncTags(self::parseTags($data['tags'] ?? ''));
            $this->audit->record('program.created', $actor, 'program', $program->id, ['name' => $program->name, 'short_code' => $program->short_code]);

            return $program;
        });

        return redirect()->route('admin.programs.show', $program)->with('status', 'Program dibuat sebagai draf.');
    }

    public function show(Program $program): View
    {
        $classes = $program->classes()->orderByDesc('starts_on')->get();
        $banks = DB::table('question_banks')->where('program_id', $program->id)->orderBy('name')->get(['id', 'name']);
        $people = User::query()->whereIn('id', array_filter([$program->created_by, $program->submitted_by, $program->reviewed_by]))->pluck('name', 'id');

        return view('admin.programs.show', compact('program', 'classes', 'banks', 'people') + ['tags' => $program->tags()]);
    }

    public function edit(Program $program): View
    {
        abort_if($program->status === 'in_review' || $program->status === 'archived', 409, 'Program terkunci.');

        return view('admin.programs.form', ['program' => $program, 'tags' => implode(', ', $program->tags())]);
    }

    public function update(Request $request, Program $program): RedirectResponse
    {
        abort_if($program->status === 'in_review' || $program->status === 'archived', 409, 'Program terkunci.');
        $data = $request->validate($this->rules(), $this->messages());

        /** @var User $actor */
        $actor = $request->user();
        $program->forceFill($this->attributes($data));
        $changes = $program->getDirty();
        DB::transaction(function () use ($program, $data, $actor, $changes): void {
            $program->save();
            $program->syncTags(self::parseTags($data['tags'] ?? ''));
            unset($changes['description_html']);
            $this->audit->record('program.updated', $actor, 'program', $program->id, $changes);
        });

        return redirect()->route('admin.programs.show', $program)->with('status', 'Perubahan program disimpan.');
    }

    public function submit(Request $request, Program $program): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->lifecycle->submit($program, $actor);

        return back()->with('status', 'Program diajukan untuk review. Penerbitan dilakukan oleh admin lain.');
    }

    public function publish(Request $request, Program $program): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->lifecycle->publish($program, $actor);

        return back()->with('status', 'Program diterbitkan dan tampil di katalog.');
    }

    public function reject(Request $request, Program $program): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        /** @var User $actor */
        $actor = $request->user();
        $this->lifecycle->returnToDraft($program, $actor, $data['reason']);

        return back()->with('status', 'Program dikembalikan ke draf.');
    }

    public function archive(Request $request, Program $program): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        /** @var User $actor */
        $actor = $request->user();
        $this->lifecycle->archive($program, $actor, $data['reason']);

        return back()->with('status', 'Program diarsipkan: tidak menerima pendaftaran baru.');
    }

    /** @return array<string, list<mixed>> */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:200'],
            'category' => ['required', Rule::in(array_keys(Program::CATEGORIES))],
            'provider_name' => ['required', 'string', 'max:200'],
            'scheme_code' => ['nullable', 'string', 'max:60'],
            'level' => ['nullable', Rule::in(array_keys(Program::LEVELS))],
            'duration_hours' => ['required', 'integer', 'between:0,2000'],
            'language' => ['required', Rule::in(['id', 'en'])],
            'default_mode' => ['required', Rule::in(array_keys(Program::MODES))],
            'description_md' => ['nullable', 'string', 'max:20000'],
            'passing_score' => ['required', 'numeric', 'between:0,100'],
            'certificate_validity_months' => ['required', 'integer', 'between:0,240'],
            'price' => ['required', 'integer', 'between:0,1000000000'],
            'tags' => ['nullable', 'string', 'max:400'],
        ];
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return ['short_code.regex' => 'Kode singkat 2–16 karakter: huruf besar, angka, tanda hubung.', 'short_code.unique' => 'Kode singkat sudah dipakai.'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'name' => trim((string) $data['name']),
            'category' => $data['category'],
            'provider_name' => trim((string) $data['provider_name']),
            'scheme_code' => $data['scheme_code'] ?? null,
            'level' => $data['level'] ?? null,
            'duration_hours' => (int) $data['duration_hours'],
            'language' => $data['language'],
            'default_mode' => $data['default_mode'],
            'description_md' => $data['description_md'] ?? null,
            'description_html' => RichText::toHtml($data['description_md'] ?? null),
            'passing_score' => $data['passing_score'],
            'certificate_validity_months' => (int) $data['certificate_validity_months'],
            'price' => (int) $data['price'],
        ];
    }

    /** @return list<string> */
    private static function parseTags(string $raw): array
    {
        return array_slice(array_unique(array_filter(array_map(
            fn (string $tag): string => Str::limit(preg_replace('/[^a-z0-9 -]/', '', mb_strtolower(trim($tag))) ?? '', 40, ''),
            explode(',', $raw),
        ))), 0, 10);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'program';
        $slug = $base;
        for ($i = 2; Program::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
