<?php

declare(strict_types=1);

namespace App\Modules\Payment\Http\Controllers;

use App\Modules\Learning\Services\MediaStorage;
use App\Modules\Payment\Models\PaymentTransaction;
use App\Modules\Payment\Services\InvoicePdfRenderer;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/** Penyajian bukti transfer (privat, inline) & invoice PDF setelah pemeriksaan hak akses di controller. */
trait ServesPaymentFiles
{
    private function proofResponse(PaymentTransaction $transaction): BinaryFileResponse
    {
        $asset = $transaction->proof;
        abort_if($asset === null || ! $asset->isServable(), 404);

        $response = new BinaryFileResponse(app(MediaStorage::class)->absolutePath($asset), 200, [
            'Content-Type' => $asset->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], true, null, false, true);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'bukti-'.$transaction->order_id.'.'.pathinfo($asset->storage_key, PATHINFO_EXTENSION), 'bukti');

        return $response;
    }

    private function invoiceResponse(PaymentTransaction $transaction): Response
    {
        abort_unless($transaction->isSettled(), 404);
        $pdf = app(InvoicePdfRenderer::class)->render($transaction->load('user', 'program', 'courseClass'));
        $name = str_replace('/', '-', (string) $transaction->invoice_number).'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
