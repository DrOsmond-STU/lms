<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Content\RichText;
use Illuminate\View\View;

/**
 * Halaman dokumen hukum berversi (docs/08 PUB-07/PUB-08). Isi & versi diatur di
 * Pengaturan Sistem → Dokumen Hukum; Markdown disanitasi sebelum ditampilkan.
 */
final class LegalController
{
    public function terms(): View
    {
        return $this->document('Syarat & Ketentuan', 'terms');
    }

    public function privacy(): View
    {
        return $this->document('Kebijakan Privasi', 'privacy');
    }

    private function document(string $title, string $document): View
    {
        $version = (string) config("legal.{$document}_version");

        return view('legal.document', [
            'title' => $title,
            'version' => $version,
            'isDraft' => str_contains($version, 'draft'),
            'bodyHtml' => RichText::toHtml((string) setting("legal.{$document}_body")),
        ]);
    }
}
