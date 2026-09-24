<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tampilan jejak audit dengan filter sesuai lingkup peran (FR-AUD-002): staf platform
 * melihat semua; Admin Organisasi hanya entri organisasinya. Ekspor juga diaudit.
 */
final class AuditLogController
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $entries = $this->query($request, $user)->orderByDesc('seq')->paginate(50)->withQueryString();
        $actors = User::query()->whereIn('id', collect($entries->items())->pluck('actor_id')->filter()->unique())->pluck('name', 'id');

        return view('audit.index', [
            'entries' => $entries,
            'actors' => $actors,
            'filters' => $request->only(['aktor', 'aksi', 'objek', 'dari', 'sampai']),
            'workspace' => $user->isPlatformStaff() ? 'admin' : 'organization',
        ]);
    }

    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        $query = $this->query($request, $user)->orderBy('seq');
        $audit->record('audit_log.exported', $user, 'audit_log', null, ['filters' => $request->only(['aktor', 'aksi', 'objek', 'dari', 'sampai'])]);

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['Waktu (UTC)', 'Aktor', 'Peran', 'Aksi', 'Objek', 'ID Objek', 'Perubahan', 'Alasan', 'IP', 'Request ID', 'Hash']);
            $query->chunk(1000, function ($rows) use ($out): void {
                foreach ($rows as $row) {
                    fputcsv($out, array_map(fn ($value): string => self::neutralize((string) $value), [
                        $row->occurred_at, $row->actor_id, $row->actor_role, $row->action, $row->subject_type, $row->subject_id,
                        $row->changes, $row->reason, $row->ip, $row->request_id, $row->hash,
                    ]));
                }
            });
            fclose($out);
        }, 'audit-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function query(Request $request, User $user): Builder
    {
        $query = DB::table('audit_logs');
        if (! $user->isPlatformStaff()) {
            $query->whereIn('organization_id', $user->tenantOrganizationIds() ?: ['00000000-0000-0000-0000-000000000000']);
        }

        $actorEmail = trim((string) $request->query('aktor', ''));
        if ($actorEmail !== '') {
            $query->where('actor_id', User::query()->where('email', User::normalizeEmail($actorEmail))->value('id') ?? '00000000-0000-0000-0000-000000000000');
        }
        $action = Str::limit(preg_replace('/[^a-z_.]/', '', (string) $request->query('aksi', '')) ?? '', 64, '');
        if ($action !== '') {
            $query->where('action', 'like', $action.'%');
        }
        $subject = Str::limit(preg_replace('/[^a-z_]/', '', (string) $request->query('objek', '')) ?? '', 64, '');
        if ($subject !== '') {
            $query->where('subject_type', $subject);
        }
        foreach (['dari' => '>=', 'sampai' => '<='] as $key => $operator) {
            $value = (string) $request->query($key, '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
                $query->where('occurred_at', $operator, $key === 'dari' ? $value.' 00:00:00+07' : $value.' 23:59:59+07');
            }
        }

        return $query;
    }

    private static function neutralize(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
