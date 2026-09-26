<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Controllers;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Security\Models\Backup;
use App\Modules\Security\Models\PrivacyRequest;
use App\Modules\Security\Services\BackupService;
use App\Modules\Security\Services\PrivacyService;
use App\Modules\Security\Services\SecurityMonitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Operasi keamanan admin: pemantauan aktivitas mencurigakan, backup, permintaan privasi. */
final class SecurityOpsController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function monitor(Request $request, SecurityMonitor $monitor): View
    {
        $type = trim((string) $request->query('jenis', ''));
        $severity = in_array($request->query('tingkat'), ['info', 'warning', 'high', 'critical'], true) ? (string) $request->query('tingkat') : null;
        $events = DB::table('security_events')->orderByDesc('occurred_at')
            ->when($type !== '', fn ($q) => $q->where('type', $type))
            ->when($severity !== null, fn ($q) => $q->where('severity', $severity))
            ->paginate(40)->withQueryString();
        $userIds = collect($events->items())->pluck('user_id')->filter()->unique()->values();
        $users = User::query()->whereIn('id', $userIds)->pluck('name', 'id');

        return view('security.monitor', $monitor->summary() + ['events' => $events, 'users' => $users, 'type' => $type, 'severity' => $severity, 'alertTypes' => SecurityMonitor::ALERT_TYPES]);
    }

    public function scan(Request $request, SecurityMonitor $monitor): RedirectResponse
    {
        $created = $monitor->scan();
        $this->audit->record('security.scan_run', $request->user(), 'security', null, $created);

        return back()->with('status', 'Pemindaian selesai: '.array_sum($created).' peringatan baru.');
    }

    public function backups(): View
    {
        return view('security.backups', [
            'backups' => Backup::query()->orderByDesc('started_at')->paginate(30),
            'encrypted' => BackupService::encryptionKey() !== null,
            'keepDays' => (int) setting('backup.keep_days'),
            'enabled' => (bool) setting('backup.enabled'),
        ]);
    }

    public function runBackup(Request $request, BackupService $backups): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $backup = $request->input('kind') === 'media' ? $backups->media($user) : $backups->database($user);
        $backups->prune((int) setting('backup.keep_days'));

        return back()->with('status', $backup->status === 'ok' ? 'Backup selesai: '.$backup->filename.' ('.$backup->humanSize().').' : 'Backup gagal: '.$backup->error);
    }

    public function downloadBackup(Request $request, Backup $backup): BinaryFileResponse
    {
        abort_unless($backup->status === 'ok' && is_file(BackupService::path($backup)), 404);
        $this->audit->record('backup.downloaded', $request->user(), 'backup', $backup->id, ['filename' => $backup->filename]);

        return response()->download(BackupService::path($backup), $backup->filename, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function privacyRequests(Request $request): View
    {
        $status = array_key_exists((string) $request->query('status'), PrivacyRequest::STATUSES) ? (string) $request->query('status') : 'pending';
        $requests = PrivacyRequest::query()->with('user:id,name,email,status')->where('status', $status)->orderBy('created_at')->paginate(30)->withQueryString();

        return view('security.privacy-requests', ['requests' => $requests, 'status' => $status]);
    }

    public function decidePrivacy(Request $request, PrivacyRequest $privacyRequest, PrivacyService $privacy): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate(['decision' => ['required', 'in:process,reject'], 'decision_note' => ['required', 'string', 'min:5', 'max:500']]);
        $data['decision'] === 'process' ? $privacy->process($user, $privacyRequest, $data['decision_note']) : $privacy->reject($user, $privacyRequest, $data['decision_note']);

        return back()->with('status', $data['decision'] === 'process' ? 'Akun dianonimkan sesuai permintaan.' : 'Permintaan ditolak dan pengguna diberi tahu.');
    }
}
