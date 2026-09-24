<?php

declare(strict_types=1);

namespace App\Modules\Access\Http\Controllers;

use App\Modules\Access\Models\ApprovalRequest;
use App\Modules\Access\Services\ApprovalWorkflow;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Kotak "Persetujuan Kedua" (docs/08 §7, maker–checker). Pengaju tidak dapat memutus
 * permintaannya sendiri; keputusan memerlukan re-autentikasi.
 */
final class ApprovalRequestController
{
    public function __construct(private readonly ApprovalWorkflow $workflow) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $open = ApprovalRequest::query()->with('requester:id,name')->whereNull('decision')->where('expires_at', '>', now())->orderBy('requested_at')->get();
        $recent = ApprovalRequest::query()->with('requester:id,name')->whereNotNull('decision')->orderByDesc('decided_at')->limit(20)->get();

        return view('approvals.index', [
            'open' => $open,
            'recent' => $recent,
            'decidable' => $open->mapWithKeys(fn (ApprovalRequest $item) => [$item->id => $this->workflow->canDecide($item, $user)]),
        ]);
    }

    public function decide(Request $request, ApprovalRequest $approval): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'reason' => ['nullable', 'string', 'max:500']]);
        /** @var User $user */
        $user = $request->user();
        $this->workflow->decide($approval, $user, $data['decision'] === 'approve', $data['reason'] ?? null);

        return back()->with('status', $data['decision'] === 'approve' ? 'Permintaan disetujui dan dieksekusi.' : 'Permintaan ditolak.');
    }
}
