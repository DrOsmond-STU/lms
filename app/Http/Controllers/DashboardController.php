<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Communication\Services\AnnouncementFeed;
use App\Modules\Identity\Models\User;
use App\Modules\Reporting\Services\DashboardStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard per area/peran (FR-RPT-001..003).
 */
final class DashboardController
{
    public function redirect(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        return match ($user->defaultWorkspace()) {
            'participant' => redirect()->route('participant.dashboard'),
            'trainer' => redirect()->route('trainer.dashboard'),
            'organization' => redirect()->route('organization.dashboard'),
            'admin' => redirect()->route('admin.dashboard'),
            default => abort(403, 'Akun Anda belum memiliki peran. Hubungi administrator.'),
        };
    }

    public function show(Request $request, string $workspace, DashboardStats $stats, AnnouncementFeed $feed): View
    {
        /** @var User $user */
        $user = $request->user();
        [$view, $data] = match ($workspace) {
            'participant' => ['dashboards.participant', $stats->participant($user)],
            'trainer' => ['dashboards.trainer', $stats->trainer($user)],
            'organization' => ['dashboards.organization', $stats->organization($user)],
            default => ['dashboards.admin', $stats->admin($user)],
        };

        return view($view, $data + ['workspace' => $workspace, 'user' => $user, 'announcements' => $feed->for($user, 4)]);
    }
}
