<?php

declare(strict_types=1);

namespace App\Modules\Referral\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Payment\Models\PaymentTransaction;
use App\Modules\Referral\Models\ReferralCommission;
use App\Modules\Referral\Services\ReferralService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Halaman "Referral Saya" peserta: kode & tautan, statistik, komisi, rekening pencairan. */
final class ReferralController
{
    public function __construct(private readonly ReferralService $referrals, private readonly TenantContext $tenant) {}

    public function show(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $this->referrals->profileFor($user);
        $commissions = ReferralCommission::query()->with(['referredUser:id,name', 'payout:id,reference,paid_at'])
            ->where('referrer_id', $user->id)->orderByDesc('created_at')->limit(100)->get();
        // Transaksi milik orang lain tidak terlihat oleh referrer (RLS); hanya nama program yang ditampilkan.
        $programNames = $this->tenant->runAsSystem(fn () => PaymentTransaction::query()->whereIn('id', $commissions->pluck('payment_transaction_id'))
            ->with('program:id,name')->get(['id', 'program_id'])->mapWithKeys(fn (PaymentTransaction $t) => [$t->id => $t->program->name]));

        return view('referral.mine', [
            'enabled' => ReferralService::isEnabled(),
            'profile' => $profile,
            'link' => $this->referrals->linkFor($profile),
            'stats' => $this->referrals->stats($user),
            'referred' => User::query()->where('referred_by', $user->id)->orderByDesc('referred_at')->limit(100)->get(['id', 'name', 'status', 'referred_at']),
            'commissions' => $commissions,
            'programNames' => $programNames,
            'percent' => (int) config('lms.referral_commission_percent'),
            'terms' => (string) setting('referral.terms'),
        ]);
    }

    public function updateAccount(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'bank_name' => ['required', 'string', 'min:2', 'max:60'],
            'bank_account' => ['required', 'string', 'regex:/^[0-9][0-9 -]{4,28}[0-9]$/'],
            'bank_account_name' => ['required', 'string', 'min:2', 'max:100'],
        ], ['bank_account.regex' => 'Nomor rekening hanya berisi angka (6–30 digit).']);

        $this->referrals->updatePayoutAccount($user, $data);

        return redirect()->route('referral.mine')->with('status', 'Rekening pencairan komisi disimpan.');
    }
}
