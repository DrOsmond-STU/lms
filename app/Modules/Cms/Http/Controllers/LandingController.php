<?php

declare(strict_types=1);

namespace App\Modules\Cms\Http\Controllers;

use App\Modules\Access\RoleCode;
use App\Modules\Catalog\Models\Program;
use App\Modules\Cms\Models\LandingPartner;
use App\Modules\Cms\Models\LandingSlide;
use App\Modules\Cms\Models\LandingTestimonial;
use App\Modules\Cms\Models\SiteProfile;
use App\Modules\Cms\Support\ProgramFilter;
use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Beranda publik: slide, program yang dapat difilter & langsung diikuti, testimoni,
 * mitra pengguna, dan informasi pemilik situs.
 */
final class LandingController
{
    /** Batas kartu program di beranda; katalog lengkap ada di /program. */
    private const PROGRAM_LIMIT = 60;

    public function show(Request $request): View
    {
        $programs = Program::query()->where('status', 'published')
            ->withCount(['classes as open_classes_count' => fn ($query) => $query->where('status', 'open')])
            ->withMin(['classes as next_start' => fn ($query) => $query->where('status', 'open')], 'starts_on')
            ->orderByDesc('open_classes_count')->orderBy('name')
            ->limit(self::PROGRAM_LIMIT)
            ->get(['id', 'name', 'slug', 'category', 'level', 'default_mode', 'provider_name', 'duration_hours', 'price']);

        $tags = [];
        foreach (DB::table('program_tags')->whereIn('program_id', $programs->pluck('id'))->orderBy('tag')->get(['program_id', 'tag']) as $row) {
            $tags[(string) $row->program_id][] = (string) $row->tag;
        }
        $topics = array_values(array_unique(array_merge([], ...array_values($tags))));
        sort($topics);
        $filter = ProgramFilter::fromRequest($request, $topics);

        $cards = $programs->map(function (Program $program) use ($tags, $filter) {
            $next = $program->getAttribute('next_start') !== null ? Carbon::parse((string) $program->getAttribute('next_start')) : null;
            $buckets = [
                'jenis' => $program->category,
                'topik' => $tags[$program->id] ?? [],
                'harga' => ProgramFilter::priceBucket((int) $program->price),
                'jadwal' => ProgramFilter::monthOffset($next),
                'durasi' => ProgramFilter::durationBucket((int) $program->duration_hours),
            ];

            return ['program' => $program, 'next' => $next, 'buckets' => $buckets, 'visible' => $filter->matches($buckets)];
        });

        /** @var User|null $user */
        $user = $request->user();

        return view('welcome', [
            'slides' => LandingSlide::query()->where('is_active', true)->orderBy('position')->limit(6)->get(),
            'cards' => $cards,
            'topics' => $topics,
            'filter' => $filter,
            'joinAsParticipant' => $user === null || $user->hasRole(RoleCode::Participant),
            'testimonials' => LandingTestimonial::query()->where('is_published', true)->orderBy('position')->limit(9)->get(),
            'partners' => LandingPartner::query()->where('is_active', true)->orderBy('position')->orderBy('name')->limit(40)->get(),
            'profile' => SiteProfile::current(),
            'stats' => [
                'programs' => $programs->count(),
                'openClasses' => (int) $programs->sum('open_classes_count'),
                'certificates' => DB::table('certificates')->where('status', 'active')->count(),
            ],
        ]);
    }
}
