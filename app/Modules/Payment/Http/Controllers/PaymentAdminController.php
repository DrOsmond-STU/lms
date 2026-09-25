<?php

declare(strict_types=1);

namespace App\Modules\Payment\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Payment\Models\PaymentTransaction;
use App\Modules\Payment\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Admin Keuangan: verifikasi bukti transfer, konfirmasi lunas (maker–checker), tolak/batalkan. */
final class PaymentAdminController
{
    use ServesPaymentFiles;

    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $search = trim((string) $request->query('q', ''));
        $review = $request->query('tinjau') === '1';

        $transactions = PaymentTransaction::query()->with('user:id,name,email', 'program:id,name', 'courseClass:id,batch_name')
            ->when(array_key_exists($status, PaymentTransaction::STATUSES), fn ($query) => $query->where('status', $status))
            ->when($review, fn ($query) => $query->where('needs_review', true)->where('status', 'pending'))
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $query->where(fn ($q) => $q->where('order_id', 'ilike', $like)->orWhere('invoice_number', 'ilike', $like)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'ilike', $like)->orWhere('email', 'ilike', $like)));
            })
            ->orderByDesc('needs_review')->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('payments.admin-index', [
            'transactions' => $transactions,
            'status' => $status,
            'search' => $search,
            'review' => $review,
            'reviewCount' => PaymentTransaction::query()->where('needs_review', true)->where('status', 'pending')->count(),
            'threshold' => PaymentService::settleThreshold(),
        ]);
    }

    public function show(PaymentTransaction $transaction): View
    {
        $transaction->load('user', 'program', 'courseClass', 'enrollment', 'proof', 'settler');

        return view('payments.admin-show', [
            'transaction' => $transaction,
            'events' => $transaction->events()->with('actor:id,name')->get(),
            'threshold' => PaymentService::settleThreshold(),
        ]);
    }

    public function settle(Request $request, PaymentTransaction $transaction): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:300']]);
        /** @var User $actor */
        $actor = $request->user();
        $result = $this->payments->settle($transaction, $actor, $data['note'] ?? null);

        return redirect()->route('admin.payments.show', $transaction)->with('status', $result === 'settled'
            ? 'Pembayaran dikonfirmasi lunas; invoice '.$transaction->invoice_number.' diterbitkan dan peserta resmi terdaftar.'
            : 'Nilai tagihan di atas ambang: permintaan persetujuan dikirim ke admin lain (maker–checker).');
    }

    public function rejectProof(Request $request, PaymentTransaction $transaction): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']], ['reason.min' => 'Tuliskan alasan yang jelas (minimal 5 karakter).']);
        /** @var User $actor */
        $actor = $request->user();
        $this->payments->rejectProof($transaction, $actor, $data['reason']);

        return redirect()->route('admin.payments.show', $transaction)->with('status', 'Bukti ditolak; peserta diminta mengunggah ulang.');
    }

    public function fail(Request $request, PaymentTransaction $transaction): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']], ['reason.min' => 'Tuliskan alasan yang jelas (minimal 5 karakter).']);
        /** @var User $actor */
        $actor = $request->user();
        $this->payments->fail($transaction, $actor, $data['reason']);

        return redirect()->route('admin.payments.show', $transaction)->with('status', 'Tagihan ditolak dan pendaftaran dibatalkan; kursi dilepas.');
    }

    public function proof(PaymentTransaction $transaction): BinaryFileResponse
    {
        return $this->proofResponse($transaction);
    }

    public function invoice(PaymentTransaction $transaction): Response
    {
        return $this->invoiceResponse($transaction);
    }
}
