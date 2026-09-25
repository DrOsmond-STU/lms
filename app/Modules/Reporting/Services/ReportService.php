<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Services;

use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Organization\Models\Organization;
use App\Modules\Referral\Models\ReferralCommission;
use App\Modules\Referral\Models\ReferralPayout;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Laporan platform (Admin → Laporan): rekap per organisasi dan program referral untuk satu
 * periode. Hanya agregasi; tanpa data pribadi selain nama & email tersamar di tampilan.
 */
final class ReportService
{
    /** @return array{from: Carbon, to: Carbon} */
    public static function period(?string $from, ?string $to): array
    {
        $tz = display_tz();
        $start = self::parseDate($from) ?? now($tz)->subMonths(11)->startOfMonth();
        $end = self::parseDate($to) ?? now($tz);
        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        return ['from' => $start->startOfDay(), 'to' => $end->endOfDay()];
    }

    /**
     * Rekap per organisasi: anggota aktif, enrollment periode (total/aktif/lulus/gagal/batal),
     * tingkat kelulusan, sertifikat aktif, pembayaran lunas.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function organizations(Carbon $from, Carbon $to, string $search = ''): Collection
    {
        $bindings = [$from, $to];

        $rows = DB::table('organizations as o')
            ->select('o.id', 'o.name', 'o.code', 'o.type', 'o.city', 'o.status')
            ->selectSub(DB::table('organization_members')->selectRaw('count(*)')->whereColumn('organization_id', 'o.id')->where('status', 'active'), 'members')
            ->selectSub(DB::table('enrollments')->selectRaw('count(*)')->whereColumn('organization_id', 'o.id')->whereBetween('created_at', $bindings), 'enrollments')
            ->selectSub(DB::table('enrollments')->selectRaw('count(*)')->whereColumn('organization_id', 'o.id')->whereBetween('created_at', $bindings)->whereIn('status', ['enrolled', 'in_progress', 'pending_approval']), 'active')
            ->selectSub(DB::table('enrollments')->selectRaw('count(*)')->whereColumn('organization_id', 'o.id')->whereBetween('created_at', $bindings)->where('status', 'passed'), 'passed')
            ->selectSub(DB::table('enrollments')->selectRaw('count(*)')->whereColumn('organization_id', 'o.id')->whereBetween('created_at', $bindings)->where('status', 'failed'), 'failed')
            ->selectSub(DB::table('enrollments')->selectRaw('count(*)')->whereColumn('organization_id', 'o.id')->whereBetween('created_at', $bindings)->where('status', 'cancelled'), 'cancelled')
            ->selectSub(DB::table('certificates')->selectRaw('count(*)')->whereColumn('organization_id', 'o.id')->where('status', 'active')->whereBetween('issued_at', $bindings), 'certificates')
            ->selectSub(DB::table('payment_transactions')->selectRaw('count(*)')->whereColumn('organization_id', 'o.id')->where('status', 'settled')->whereBetween('settled_at', $bindings), 'payments')
            ->selectSub(DB::table('payment_transactions')->selectRaw('coalesce(sum(gross_amount), 0)')->whereColumn('organization_id', 'o.id')->where('status', 'settled')->whereBetween('settled_at', $bindings), 'revenue')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $query->where(fn (Builder $q) => $q->where('o.name', 'ilike', $like)->orWhere('o.code', 'ilike', $like));
            })
            ->orderBy('o.name')->get();

        return $rows->map(fn (stdClass $row): array => self::organizationRow($row));
    }

    /**
     * Enrollment satu organisasi dalam periode (untuk halaman detail & CSV).
     *
     * @return LengthAwarePaginator<int, Enrollment>
     */
    public function organizationEnrollments(Organization $organization, Carbon $from, Carbon $to, int $perPage = 50): LengthAwarePaginator
    {
        return Enrollment::query()->with(['user:id,name,email', 'program:id,name', 'courseClass:id,batch_name'])
            ->where('organization_id', $organization->id)->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')->paginate($perPage)->withQueryString();
    }

    /**
     * Rekap referral per referrer: kunjungan tautan, akun terdaftar, transaksi berkomisi,
     * komisi tertunda/dibayar/dibatalkan dalam periode.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function referral(Carbon $from, Carbon $to, string $search = ''): Collection
    {
        $commission = fn (string $status): Builder => DB::table('referral_commissions')->selectRaw('coalesce(sum(amount), 0)')
            ->whereColumn('referrer_id', 'u.id')->where('status', $status)->whereBetween('created_at', [$from, $to]);

        return DB::table('users as u')
            ->join('referral_profiles as p', 'p.user_id', '=', 'u.id')
            ->select('u.id', 'u.name', 'u.email', 'u.status', 'p.code', 'p.visits', 'p.bank_name')
            ->selectSub(DB::table('users')->selectRaw('count(*)')->whereColumn('referred_by', 'u.id')->whereBetween('referred_at', [$from, $to]), 'registered')
            ->selectSub(DB::table('referral_commissions')->selectRaw('count(*)')->whereColumn('referrer_id', 'u.id')->where('status', '<>', 'void')->whereBetween('created_at', [$from, $to]), 'transactions')
            ->selectSub($commission('pending'), 'pending')
            ->selectSub($commission('paid'), 'paid')
            ->selectSub($commission('void'), 'void')
            ->selectSub(DB::table('referral_commissions')->selectRaw('coalesce(sum(amount), 0)')->whereColumn('referrer_id', 'u.id')->where('status', 'pending'), 'pending_all_time')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $query->where(fn (Builder $q) => $q->where('u.name', 'ilike', $like)->orWhere('u.email', 'ilike', $like)->orWhere('p.code', 'ilike', $like));
            })
            ->orderByDesc('pending_all_time')->orderByDesc('paid')->orderBy('u.name')->get()
            ->map(fn (stdClass $row): array => self::referralRow($row));
    }

    /** @return Collection<int, ReferralCommission> */
    public function commissionsOf(string $referrerId): Collection
    {
        return ReferralCommission::query()->with(['referredUser:id,name,email', 'transaction:id,order_id,invoice_number,settled_at,program_id', 'transaction.program:id,name', 'payout:id,reference,paid_at'])
            ->where('referrer_id', $referrerId)->orderByDesc('created_at')->limit(500)->get();
    }

    /** @return Collection<int, ReferralPayout> */
    public function payoutsOf(string $referrerId): Collection
    {
        return ReferralPayout::query()->with('payer:id,name')->where('referrer_id', $referrerId)->orderByDesc('paid_at')->limit(100)->get();
    }

    /** @return array<string, mixed> */
    private static function organizationRow(stdClass $row): array
    {
        $passed = (int) $row->passed;
        $failed = (int) $row->failed;

        return [
            'id' => (string) $row->id, 'name' => (string) $row->name, 'code' => (string) $row->code, 'type' => (string) $row->type,
            'city' => $row->city === null ? null : (string) $row->city, 'status' => (string) $row->status,
            'members' => (int) $row->members, 'enrollments' => (int) $row->enrollments, 'active' => (int) $row->active,
            'passed' => $passed, 'failed' => $failed, 'cancelled' => (int) $row->cancelled, 'certificates' => (int) $row->certificates,
            'payments' => (int) $row->payments, 'revenue' => (int) $row->revenue,
            'pass_rate' => $passed + $failed === 0 ? null : round($passed * 100 / ($passed + $failed), 1),
        ];
    }

    /** @return array<string, mixed> */
    private static function referralRow(stdClass $row): array
    {
        return [
            'id' => (string) $row->id, 'name' => (string) $row->name, 'email' => (string) $row->email, 'status' => (string) $row->status,
            'code' => (string) $row->code, 'visits' => (int) $row->visits, 'bank_name' => $row->bank_name === null ? null : (string) $row->bank_name,
            'registered' => (int) $row->registered, 'transactions' => (int) $row->transactions,
            'pending' => (int) $row->pending, 'paid' => (int) $row->paid, 'void' => (int) $row->void, 'pending_all_time' => (int) $row->pending_all_time,
        ];
    }

    private static function parseDate(?string $value): ?Carbon
    {
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }
        try {
            return Carbon::createFromFormat('Y-m-d', $value, display_tz()) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
