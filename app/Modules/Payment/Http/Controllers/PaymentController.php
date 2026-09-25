<?php

declare(strict_types=1);

namespace App\Modules\Payment\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Learning\Models\CourseClass;
use App\Modules\Payment\Models\PaymentTransaction;
use App\Modules\Payment\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Sisi peserta: daftar & bayar, unggah bukti, riwayat transaksi, invoice. */
final class PaymentController
{
    use ServesPaymentFiles;

    public function __construct(private readonly PaymentService $payments) {}

    public function checkout(Request $request, CourseClass $class): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $transaction = $this->payments->checkout($user, $class->load('program'));

        return redirect()->route('payments.show', $transaction)->with('status', 'Kursi Anda dipesan. Selesaikan pembayaran sebelum batas waktu.');
    }

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $transactions = PaymentTransaction::query()->where('user_id', $user->id)->with('program:id,name', 'courseClass:id,batch_name')
            ->orderByDesc('created_at')->paginate(15);

        return view('payments.mine', ['transactions' => $transactions]);
    }

    public function show(Request $request, PaymentTransaction $transaction): View
    {
        $this->own($request, $transaction);
        $transaction->load('program', 'courseClass', 'proof');

        return view('payments.show', [
            'transaction' => $transaction,
            'bank' => PaymentService::bankAccount(),
            'events' => $transaction->events()->get(),
        ]);
    }

    public function submitProof(Request $request, PaymentTransaction $transaction): RedirectResponse
    {
        $user = $this->own($request, $transaction);
        $data = $request->validate([
            'proof' => ['required', 'file', 'max:5120'],
            'note' => ['nullable', 'string', 'max:300'],
            'billing_name' => ['nullable', 'string', 'max:160'],
            'billing_tax_id' => ['nullable', 'string', 'max:25', 'regex:/^[0-9.\- ]+$/'],
            'billing_address' => ['nullable', 'string', 'max:300'],
        ], [
            'proof.required' => 'Pilih berkas bukti transfer (JPG, PNG, atau PDF).',
            'proof.max' => 'Ukuran bukti transfer maksimal 5 MB.',
            'billing_tax_id.regex' => 'NPWP hanya berisi angka, titik, dan tanda hubung.',
        ]);

        $this->payments->submitProof($transaction, $user, $request->file('proof'), $data['note'] ?? null, [
            'billing_name' => $data['billing_name'] ?? null,
            'billing_tax_id' => $data['billing_tax_id'] ?? null,
            'billing_address' => $data['billing_address'] ?? null,
        ]);

        return redirect()->route('payments.show', $transaction)->with('status', 'Bukti transfer diterima dan menunggu verifikasi Admin Keuangan.');
    }

    public function proof(Request $request, PaymentTransaction $transaction): BinaryFileResponse
    {
        $this->own($request, $transaction);

        return $this->proofResponse($transaction);
    }

    public function invoice(Request $request, PaymentTransaction $transaction): Response
    {
        $this->own($request, $transaction);

        return $this->invoiceResponse($transaction);
    }

    private function own(Request $request, PaymentTransaction $transaction): User
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($transaction->user_id === $user->id && $user->can('payment.view'), 404);

        return $user;
    }
}
