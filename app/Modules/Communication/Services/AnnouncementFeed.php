<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Models\Announcement;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Audiens pengumuman: platform → semua pengguna; organisasi → anggota aktif, admin & supervisor
 * organisasi; kelas → peserta terdaftar & trainer pengampu. Hanya yang sudah terbit dan belum
 * kedaluwarsa. Disematkan tampil lebih dulu.
 */
final class AnnouncementFeed
{
    /** @return Collection<int, Announcement> */
    public function for(User $user, ?int $limit = null): Collection
    {
        [$organizationIds, $classIds] = $this->audienceOf($user);

        return Announcement::query()->with('author:id,name', 'organization:id,name', 'courseClass:id,batch_name,program_id', 'courseClass.program:id,name')
            ->where('publish_at', '<=', now())->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn ($q) => $q->where('scope', 'platform')
                ->orWhere(fn ($w) => $w->where('scope', 'organization')->whereIn('organization_id', $organizationIds))
                ->orWhere(fn ($w) => $w->where('scope', 'class')->whereIn('course_class_id', $classIds)))
            ->orderByDesc('is_pinned')->orderByDesc('publish_at')
            ->when($limit !== null, fn ($q) => $q->limit((int) $limit))
            ->get();
    }

    /**
     * @return array{0: list<string>, 1: list<string>} [organizationIds, classIds]
     */
    public function audienceOf(User $user): array
    {
        /** @var list<string> $memberOrgs */
        $memberOrgs = DB::table('organization_members')->where('user_id', $user->id)->where('status', 'active')->pluck('organization_id')->all();
        $organizationIds = array_values(array_unique(array_filter(array_merge($memberOrgs, $user->tenantOrganizationIds(), [$user->primary_organization_id]))));
        /** @var list<string> $enrolled */
        $enrolled = DB::table('enrollments')->where('user_id', $user->id)->whereIn('status', ['enrolled', 'in_progress', 'pending_approval', 'passed'])->pluck('course_class_id')->all();
        /** @var list<string> $taught */
        $taught = DB::table('class_trainers')->where('user_id', $user->id)->pluck('course_class_id')->all();

        return [$organizationIds, array_values(array_unique(array_merge($enrolled, $taught)))];
    }
}
