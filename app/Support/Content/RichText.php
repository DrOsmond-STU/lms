<?php

declare(strict_types=1);

namespace App\Support\Content;

use League\CommonMark\CommonMarkConverter;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Konten kaya ditulis sebagai Markdown lalu dikonversi ke HTML dan DISANITASI saat simpan
 * (keamanan/04 SEC-INPUT-01..03). HTML mentah dari pengguna tidak pernah dirender:
 * tag HTML di Markdown dibuang, tautan hanya https/mailto, gambar eksternal tidak diizinkan.
 */
final class RichText
{
    private const MAX_LENGTH = 100_000;

    public static function toHtml(?string $markdown): string
    {
        $markdown = mb_substr(trim((string) $markdown), 0, self::MAX_LENGTH);
        if ($markdown === '') {
            return '';
        }

        $converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);

        return self::sanitize((string) $converter->convert($markdown));
    }

    public static function sanitize(string $html): string
    {
        $config = (new HtmlSanitizerConfig)
            ->allowElement('p')->allowElement('br')->allowElement('strong')->allowElement('em')
            ->allowElement('ul')->allowElement('ol')->allowElement('li')->allowElement('blockquote')
            ->allowElement('h2')->allowElement('h3')->allowElement('h4')->allowElement('code')->allowElement('pre')
            ->allowElement('hr')->allowElement('table')->allowElement('thead')->allowElement('tbody')
            ->allowElement('tr')->allowElement('th')->allowElement('td')
            ->allowElement('a', ['href', 'title'])
            ->allowLinkSchemes(['https', 'mailto'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
            ->forceAttribute('a', 'target', '_blank')
            ->withMaxInputLength(self::MAX_LENGTH * 2);

        return (new HtmlSanitizer($config))->sanitize($html);
    }
}
