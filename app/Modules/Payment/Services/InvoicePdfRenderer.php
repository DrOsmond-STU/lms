<?php

declare(strict_types=1);

namespace App\Modules\Payment\Services;

use App\Modules\Certification\Services\CertificatePdf;
use App\Modules\Cms\Models\SiteProfile;
use App\Modules\Payment\Models\PaymentTransaction;

/**
 * Invoice PDF (FR-PAY-004) dirender saat diminta dari data transaksi — tidak disimpan,
 * sehingga selalu konsisten dengan status terkini. Hanya untuk transaksi lunas.
 */
final class InvoicePdfRenderer
{
    public function render(PaymentTransaction $transaction): string
    {
        $profile = SiteProfile::current();
        $issuerTaxId = trim((string) setting('invoice.tax_id'));
        $settled = $transaction->settled_at?->timezone(display_tz());

        $pdf = new CertificatePdf('P', 'mm', 'A4');
        $pdf->SetCreator((string) config('app.name'));
        $pdf->SetAuthor($profile->company_name);
        $pdf->SetTitle('Invoice '.$transaction->invoice_number);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(18, 18, 18);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->AddPage();

        // Kop penerbit
        $pdf->SetTextColor(14, 58, 99);
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(110, 8, $profile->company_name, 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->Cell(64, 8, 'INVOICE', 0, 1, 'R');
        $pdf->SetTextColor(71, 85, 105);
        $pdf->SetFont('helvetica', '', 9);
        $issuerLines = array_values(array_filter([
            $profile->address, $profile->email !== null ? 'Email: '.$profile->email : null, $profile->phone !== null ? 'Telepon: '.$profile->phone : null,
            $issuerTaxId !== '' ? 'NPWP: '.$issuerTaxId : null,
        ]));
        $pdf->MultiCell(110, 4.5, implode("\n", $issuerLines), 0, 'L', false, 0);
        $pdf->MultiCell(64, 4.5, 'Nomor: '.$transaction->invoice_number."\nTanggal: ".($settled?->translatedFormat('d F Y') ?? '-')."\nOrder: ".$transaction->order_id, 0, 'R', false, 1);
        $pdf->Ln(6);
        $pdf->SetDrawColor(203, 213, 225);
        $pdf->Line(18, $pdf->GetY(), 192, $pdf->GetY());
        $pdf->Ln(5);

        // Ditagihkan kepada
        $pdf->SetTextColor(15, 23, 42);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 5, 'Ditagihkan kepada', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $billing = array_values(array_filter([
            $transaction->billing_name ?? $transaction->user->name,
            $transaction->billing_name !== null && $transaction->billing_name !== $transaction->user->name ? 'u.p. '.$transaction->user->name : null,
            $transaction->user->email,
            $transaction->billingTaxId() !== null ? 'NPWP: '.$transaction->billingTaxId() : null,
            $transaction->billingAddress(),
        ]));
        $pdf->MultiCell(0, 5, implode("\n", $billing), 0, 'L');
        $pdf->Ln(6);

        // Rincian
        $pdf->SetFillColor(241, 245, 249);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(110, 7, 'Deskripsi', 1, 0, 'L', true);
        $pdf->Cell(20, 7, 'Jumlah', 1, 0, 'C', true);
        $pdf->Cell(44, 7, 'Nilai (Rp)', 1, 1, 'R', true);
        $pdf->SetFont('helvetica', '', 9);
        $description = $transaction->program->name."\n".$transaction->courseClass->batch_name.' · '.$transaction->program->provider_name;
        $y = $pdf->GetY();
        $pdf->MultiCell(110, 6, $description, 1, 'L', false, 0);
        $height = $pdf->GetY() - $y;
        $pdf->SetXY(128, $y);
        $pdf->Cell(20, $height, '1', 1, 0, 'C');
        $pdf->Cell(44, $height, number_format($transaction->list_price, 0, ',', '.'), 1, 1, 'R');
        if ($transaction->discount_amount > 0) {
            $pdf->Cell(130, 6, 'Diskon', 1, 0, 'R');
            $pdf->Cell(44, 6, '-'.number_format($transaction->discount_amount, 0, ',', '.'), 1, 1, 'R');
        }
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(130, 7, 'Total', 1, 0, 'R', true);
        $pdf->Cell(44, 7, number_format($transaction->gross_amount, 0, ',', '.'), 1, 1, 'R', true);
        $pdf->Ln(6);

        // Status pembayaran
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(4, 120, 87);
        $pdf->Cell(0, 7, 'LUNAS', 0, 1, 'L');
        $pdf->SetTextColor(71, 85, 105);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell(0, 4.5, 'Metode: '.(PaymentTransaction::METHODS[$transaction->payment_method] ?? $transaction->payment_method)
            ."\nDiterima: ".($settled?->translatedFormat('d F Y H:i') ?? '-').' '.tz_label()
            ."\nMata uang: ".$transaction->currency, 0, 'L');
        $pdf->Ln(8);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->MultiCell(0, 4, (string) setting('invoice.footer_note'), 0, 'L');

        return $pdf->Output('', 'S');
    }
}
