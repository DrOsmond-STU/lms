<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard sementara per area (Fase 0). Konten penuh dibangun di Fase 1.
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

    public function show(Request $request, string $workspace): View
    {
        return view('dashboard', ['workspace' => $workspace, 'user' => $request->user()]);
    }
}
