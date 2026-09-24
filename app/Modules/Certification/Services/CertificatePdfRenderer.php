<?php

declare(strict_types=1);

namespace App\Modules\Certification\Services;

use App\Modules\Certification\Models\Certificate;
use App\Modules\Certification\Models\CertificateTemplate;
use Illuminate\Support\Carbon;

/**
 * Render PDF sertifikat di server dari data DB & template terstruktur (SEC-CERT-01/05/16):
 * tanpa HTML dari pengguna, tanpa akses jaringan, aset lokal; teks dinamis ditulis sebagai
 * teks (bukan markup). PDF ditandatangani digital (PKCS#7 tertanam) bila penandatangan siap.
 */
final class CertificatePdfRenderer
{
    public function __construct(private readonly CertificateSigner $signer) {}

    public function render(Certificate $certificate, CertificateTemplate $template): string
    {
        $pdf = new CertificatePdf('L', 'mm', 'A4');
        $pdf->SetCreator(config('app.name').' Certificate Service');
        $pdf->SetAuthor('Semesta Teknologi Utama');
        $pdf->SetTitle('Sertifikat '.$certificate->number);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        [$r, $g, $b] = sscanf($template->accent_color, '#%02x%02x%02x') ?? [14, 58, 99];
        $accent = [(int) $r, (int) $g, (int) $b];

        // Bingkai
        $pdf->SetFillColor(248, 250, 252);
        $pdf->Rect(0, 0, 297, 210, 'F');
        $pdf->SetLineStyle(['width' => 2.2, 'color' => $accent]);
        $pdf->Rect(10, 10, 277, 190);
        $pdf->SetLineStyle(['width' => 0.4, 'color' => [20, 184, 166]]);
        $pdf->Rect(14, 14, 269, 182);

        $issued = Carbon::parse($certificate->issued_at)->translatedFormat('d F Y');
        $categoryLabel = $certificate->category === 'bnsp' ? 'Pelatihan Skema BNSP' : 'Pelatihan Sertifikasi Internasional';

        $pdf->SetTextColor(...$accent);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetXY(20, 24);
        $pdf->Cell(257, 6, 'SEMESTA TEKNOLOGI UTAMA', 0, 1, 'C');
        $pdf->SetFont('helvetica', 'B', 30);
        $pdf->SetXY(20, 34);
        $pdf->Cell(257, 14, mb_strtoupper($template->title_text), 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 11);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->SetXY(20, 50);
        $pdf->Cell(257, 6, 'Nomor: '.$certificate->number, 0, 1, 'C');

        $pdf->SetXY(20, 64);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(257, 6, 'Diberikan kepada', 0, 1, 'C');
        $pdf->SetTextColor(15, 23, 42);
        $pdf->SetFont('helvetica', 'B', 26);
        $pdf->SetXY(20, 72);
        $pdf->Cell(257, 14, $certificate->holder_name, 0, 1, 'C', false, '', 1);

        $body = strtr($template->body_text, [
            '{nama}' => $certificate->holder_name,
            '{program}' => $certificate->program_name,
            '{penyelenggara}' => $certificate->provider_name,
            '{tanggal}' => $issued,
            '{nomor}' => $certificate->number,
            '{kategori}' => $categoryLabel,
        ]);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->SetTextColor(51, 65, 85);
        $pdf->SetXY(40, 92);
        $pdf->MultiCell(217, 6, $body, 0, 'C');

        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetTextColor(...$accent);
        $pdf->SetXY(20, 112);
        $pdf->Cell(257, 10, $certificate->program_name, 0, 1, 'C', false, '', 1);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->SetXY(20, 122);
        $pdf->Cell(257, 6, $categoryLabel.' · Penyelenggara: '.$certificate->provider_name, 0, 1, 'C', false, '', 1);

        $validity = $certificate->valid_until === null ? 'Tanpa masa berlaku' : 'Berlaku hingga '.Carbon::parse($certificate->valid_until)->translatedFormat('d F Y');
        $pdf->SetXY(20, 130);
        $pdf->Cell(257, 6, 'Diterbitkan '.$issued.' · '.$validity, 0, 1, 'C');

        // Penandatangan
        $pdf->SetDrawColor(148, 163, 184);
        $pdf->Line(185, 176, 255, 176);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetXY(175, 177);
        $pdf->Cell(90, 6, $template->signatory_name, 0, 1, 'C', false, '', 1);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetXY(175, 183);
        $pdf->Cell(90, 5, $template->signatory_title, 0, 1, 'C', false, '', 1);

        // QR verifikasi
        $url = rtrim((string) config('app.url'), '/').'/verifikasi/'.$certificate->verification_code;
        $pdf->write2DBarcode($url, 'QRCODE,M', 30, 148, 34, 34, ['border' => false, 'padding' => 0, 'fgcolor' => [15, 23, 42], 'bgcolor' => false], 'N');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->SetXY(70, 160);
        $pdf->MultiCell(95, 4, "Verifikasi keaslian:\n".$url."\nKode verifikasi: ".$certificate->formattedCode(), 0, 'L');
        $pdf->SetXY(70, 178);
        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->MultiCell(100, 3.5, 'Sertifikat pelatihan yang diterbitkan Semesta Teknologi Utama. Bukan sertifikat resmi vendor/BNSP kecuali dinyatakan lain. Status terkini selalu mengacu pada halaman verifikasi.', 0, 'L');

        if ($this->signer->isConfigured()) {
            $pdf->setSignature($this->signer->certificatePem(), $this->signer->privateKeyPem(), '', '', 2, [
                'Name' => 'Semesta Teknologi Utama',
                'Location' => 'Indonesia',
                'Reason' => 'Penerbitan sertifikat '.$certificate->number,
                'ContactInfo' => (string) config('app.url'),
            ]);
        }

        return $pdf->Output('', 'S');
    }
}
