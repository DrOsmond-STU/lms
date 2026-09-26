<?php

declare(strict_types=1);

namespace App\Support\Export;

use App\Modules\Certification\Services\CertificatePdf;
use App\Modules\Cms\Models\SiteProfile;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Ekspor tabel laporan ke CSV (;), XLSX (OOXML minimal, tanpa pustaka), atau PDF (TCPDF).
 * Nilai teks dinetralkan dari injeksi formula spreadsheet (keamanan/09).
 */
final class TableExport
{
    public const FORMATS = ['csv' => 'CSV', 'xlsx' => 'Excel (XLSX)', 'pdf' => 'PDF'];

    public static function format(?string $value): string
    {
        return array_key_exists((string) $value, self::FORMATS) ? (string) $value : 'csv';
    }

    /**
     * @param  list<string>  $header
     * @param  iterable<int, array<int, int|float|string|null>>  $rows
     */
    public static function download(string $format, string $basename, string $title, array $header, iterable $rows, string $subtitle = ''): StreamedResponse
    {
        $filename = Str::slug($basename).'.'.$format;
        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];

        return match ($format) {
            'xlsx' => response()->streamDownload(fn () => print self::xlsx($title, $header, $rows), $filename, $headers + ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']),
            'pdf' => response()->streamDownload(fn () => print self::pdf($title, $subtitle, $header, $rows), $filename, $headers + ['Content-Type' => 'application/pdf']),
            default => response()->streamDownload(function () use ($header, $rows): void {
                $out = fopen('php://output', 'w');
                if ($out === false) {
                    return;
                }
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, $header, ';');
                foreach ($rows as $row) {
                    fputcsv($out, array_map(fn (mixed $v): string => self::neutralize(self::text($v)), $row), ';');
                }
                fclose($out);
            }, $filename, $headers + ['Content-Type' => 'text/csv; charset=UTF-8']),
        };
    }

    /**
     * @param  list<string>  $header
     * @param  iterable<int, array<int, int|float|string|null>>  $rows
     */
    public static function xlsx(string $title, array $header, iterable $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($path === false) {
            throw new RuntimeException('Tidak dapat membuat berkas sementara.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak dapat membuat arsip XLSX.');
        }
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetData>';
        $sheet .= self::xmlRow(1, $header, true);
        $n = 1;
        foreach ($rows as $row) {
            $sheet .= self::xmlRow(++$n, array_values($row), false);
        }
        $sheet .= '</sheetData></worksheet>';
        $safeTitle = htmlspecialchars(mb_substr(preg_replace('/[\\\\\/\*\?\[\]:]/', ' ', $title) ?? 'Laporan', 0, 31), ENT_XML1);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.$safeTitle.'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" applyFont="1"/></cellXfs></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();
        $binary = (string) file_get_contents($path);
        @unlink($path);

        return $binary;
    }

    /**
     * @param  list<string>  $header
     * @param  iterable<int, array<int, int|float|string|null>>  $rows
     */
    public static function pdf(string $title, string $subtitle, array $header, iterable $rows): string
    {
        $profile = SiteProfile::current();
        $columns = count($header);
        $pdf = new CertificatePdf($columns > 7 ? 'L' : 'P', 'mm', 'A4');
        $pdf->SetCreator((string) config('app.name'));
        $pdf->SetAuthor($profile->company_name);
        $pdf->SetTitle($title);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(12, 14, 12);
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->AddPage();
        $pdf->SetTextColor(14, 58, 99);
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 7, $title, 0, 1, 'L');
        $pdf->SetTextColor(71, 85, 105);
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->Cell(0, 5, trim($profile->company_name.($subtitle !== '' ? ' · '.$subtitle : '')).' · dicetak '.now()->timezone(display_tz())->translatedFormat('d M Y H:i').' '.tz_label(), 0, 1, 'L');
        $pdf->Ln(2);

        $html = '<table border="1" cellpadding="3" cellspacing="0" style="font-size:'.($columns > 9 ? '6.5' : '8').'pt;border-color:#cbd5e1;"><thead><tr style="background-color:#f1f5f9;font-weight:bold;color:#0f172a;">';
        foreach ($header as $cell) {
            $html .= '<th>'.htmlspecialchars($cell, ENT_QUOTES).'</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach (array_values($row) as $cell) {
                $html .= '<td'.(is_int($cell) || is_float($cell) ? ' align="right"' : '').'>'.htmlspecialchars(self::text($cell), ENT_QUOTES).'</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        $pdf->SetTextColor(15, 23, 42);
        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf->Output('', 'S');
    }

    /** @param  array<int, int|float|string|null>  $cells */
    private static function xmlRow(int $number, array $cells, bool $bold): string
    {
        $xml = '<row r="'.$number.'">';
        foreach (array_values($cells) as $index => $cell) {
            $ref = self::column($index).$number;
            if (is_int($cell) || is_float($cell)) {
                $xml .= '<c r="'.$ref.'"'.($bold ? ' s="1"' : '').'><v>'.$cell.'</v></c>';
            } else {
                $text = self::neutralize(self::text($cell));
                $xml .= '<c r="'.$ref.'" t="inlineStr"'.($bold ? ' s="1"' : '').'><is><t xml:space="preserve">'.htmlspecialchars($text, ENT_XML1 | ENT_QUOTES).'</t></is></c>';
            }
        }

        return $xml.'</row>';
    }

    private static function column(int $index): string
    {
        $name = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $name = chr(65 + $mod).$name;
            $index = intdiv($index - $mod - 1, 26);
        }

        return $name;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /** Cegah injeksi formula spreadsheet (keamanan/09). */
    private static function neutralize(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
