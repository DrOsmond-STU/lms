<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Controllers;

use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Organization;
use App\Modules\Payment\Models\PaymentTransaction;
use App\Modules\Referral\Models\ReferralCommission;
use App\Modules\Referral\Services\ReferralService;
use App\Modules\Reporting\Services\ReportService;
use App\Support\Export\TableExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Admin → Laporan: per organisasi & referral (tampilan + ekspor CSV), pencairan komisi. */
final class ReportController
{
    public function __construct(private readonly ReportService $reports, private readonly AuditLogger $audit) {}

    public function organizations(Request $request): View
    {
        $period = ReportService::period($request->query('dari'), $request->query('sampai'));
        $search = trim((string) $request->query('q', ''));

        return view('reports.organizations', [
            'rows' => $this->reports->organizations($period['from'], $period['to'], $search),
            'period' => $period,
            'search' => $search,
        ]);
    }

    public function organizationShow(Request $request, Organization $organization): View
    {
        $period = ReportService::period($request->query('dari'), $request->query('sampai'));
        $summary = $this->reports->organizations($period['from'], $period['to'], $organization->code)->firstWhere('id', $organization->id);

        return view('reports.organization-show', [
            'organization' => $organization,
            'summary' => $summary,
            'enrollments' => $this->reports->organizationEnrollments($organization, $period['from'], $period['to']),
            'period' => $period,
        ]);
    }

    public function organizationsExport(Request $request): StreamedResponse
    {
        $period = ReportService::period($request->query('dari'), $request->query('sampai'));
        $rows = $this->reports->organizations($period['from'], $period['to'], trim((string) $request->query('q', '')));
        $this->audit->record('report.exported', $request->user(), 'report', null, ['report' => 'organizations', 'from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString()]);

        return TableExport::download(TableExport::format($request->query('format')), 'laporan-organisasi-'.$period['from']->format('Ymd').'-'.$period['to']->format('Ymd'), 'Laporan per Organisasi',
            ['Organisasi', 'Kode', 'Jenis', 'Kota', 'Status', 'Anggota Aktif', 'Enrollment', 'Aktif', 'Lulus', 'Tidak Lulus', 'Dibatalkan', 'Tingkat Kelulusan (%)', 'Sertifikat', 'Pembayaran Lunas', 'Pendapatan (Rp)'],
            $rows->map(fn (array $r): array => [$r['name'], $r['code'], $r['type'], $r['city'] ?? '', $r['status'], $r['members'], $r['enrollments'], $r['active'], $r['passed'], $r['failed'], $r['cancelled'], $r['pass_rate'] ?? '', $r['certificates'], $r['payments'], $r['revenue']]),
            'periode '.$period['from']->translatedFormat('d M Y').' – '.$period['to']->translatedFormat('d M Y'),
        );
    }

    public function referral(Request $request): View
    {
        $period = ReportService::period($request->query('dari'), $request->query('sampai'));
        $search = trim((string) $request->query('q', ''));
        $rows = $this->reports->referral($period['from'], $period['to'], $search);

        return view('reports.referral', [
            'rows' => $rows,
            'period' => $period,
            'search' => $search,
            'totals' => ['registered' => (int) $rows->sum('registered'), 'transactions' => (int) $rows->sum('transactions'), 'pending' => (int) $rows->sum('pending'), 'paid' => (int) $rows->sum('paid')],
            'enabled' => ReferralService::isEnabled(),
        ]);
    }

    public function referralShow(User $user, ReferralService $referrals): View
    {
        $profile = $user->referralProfile;
        abort_if($profile === null, 404);

        return view('reports.referral-show', [
            'referrer' => $user,
            'profile' => $profile,
            'stats' => $referrals->stats($user),
            'commissions' => $this->reports->commissionsOf($user->id),
            'payouts' => $this->reports->payoutsOf($user->id),
        ]);
    }

    public function referralExport(Request $request): StreamedResponse
    {
        $period = ReportService::period($request->query('dari'), $request->query('sampai'));
        $rows = $this->reports->referral($period['from'], $period['to'], trim((string) $request->query('q', '')));
        $this->audit->record('report.exported', $request->user(), 'report', null, ['report' => 'referral', 'from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString()]);

        return $this->csv('laporan-referral-'.$period['from']->format('Ymd').'-'.$period['to']->format('Ymd').'.csv',
            ['Referrer', 'Email', 'Kode', 'Kunjungan Tautan', 'Akun Terdaftar', 'Transaksi Berkomisi', 'Komisi Tertunda (Rp)', 'Komisi Dibayar (Rp)', 'Komisi Dibatalkan (Rp)', 'Tertunda Seluruh Waktu (Rp)'],
            $rows->map(fn (array $r): array => [$r['name'], $r['email'], $r['code'], $r['visits'], $r['registered'], $r['transactions'], $r['pending'], $r['paid'], $r['void'], $r['pending_all_time']]),
        );
    }

    public function payout(Request $request, User $user, ReferralService $referrals): RedirectResponse
    {
        $data = $request->validate(['reference' => ['required', 'string', 'min:3', 'max:120'], 'note' => ['nullable', 'string', 'max:300']],
            ['reference.required' => 'Isi referensi transfer (nomor mutasi/bukti).']);
        /** @var User $actor */
        $actor = $request->user();
        $payout = $referrals->payout($user, $actor, $data['reference'], $data['note'] ?? null);

        return redirect()->route('admin.reports.referral.show', $user)->with('status', 'Pencairan '.PaymentTransaction::rupiah($payout->amount).' ('.$payout->commission_count.' komisi) dicatat.');
    }

    public function voidCommission(Request $request, ReferralCommission $commission, ReferralService $referrals): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        /** @var User $actor */
        $actor = $request->user();
        $referrals->void($commission, $actor, $data['reason']);

        return redirect()->route('admin.reports.referral.show', $commission->referrer_id)->with('status', 'Komisi dibatalkan.');
    }

    /**
     * @param  list<string>  $header
     * @param  iterable<array<int, mixed>>  $rows
     */
    private function csv(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ';');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn (mixed $value): string => self::neutralize(is_scalar($value) ? (string) $value : ''), $row), ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** Cegah injeksi formula spreadsheet (keamanan/09). */
    private static function neutralize(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
