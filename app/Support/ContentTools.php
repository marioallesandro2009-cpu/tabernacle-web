<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;

/** Helpers for article HTML: table of contents, reading time, plain text. */
class ContentTools
{
    public static function text(?string $html): string
    {
        $t = html_entity_decode(strip_tags(str_replace(['</p>', '</li>', '</h2>', '</h3>', '</h4>', '<br>', '<br/>', '<br />'], ' ', (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $t));
    }

    public static function wordCount(?string $html): int
    {
        $t = self::text($html);

        return $t === '' ? 0 : count(preg_split('/\s+/u', $t) ?: []);
    }

    public static function readingTime(?string $html): int
    {
        return max(1, (int) ceil(self::wordCount($html) / 200));
    }

    /** @return DOMDocument|null a document whose body holds the fragment */
    public static function dom(?string $html): ?DOMDocument
    {
        if (trim((string) $html) === '') {
            return null;
        }
        $doc = new DOMDocument;
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?><body>'.$html.'</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return $doc;
    }

    /**
     * Adds anchor ids to h2/h3 and returns the table of contents.
     *
     * @return array{html: string, toc: array<int, array{id: string, text: string, level: int}>}
     */
    public static function withToc(?string $html): array
    {
        $doc = self::dom($html);
        if (! $doc) {
            return ['html' => '', 'toc' => []];
        }
        $toc = [];
        $used = [];
        foreach ((new DOMXPath($doc))->query('//h2|//h3') ?: [] as $h) {
            /** @var DOMElement $h */
            $text = trim($h->textContent);
            if ($text === '') {
                continue;
            }
            $id = Str::slug($text) ?: 'bagian';
            $base = $id;
            for ($i = 2; in_array($id, $used, true); $i++) {
                $id = $base.'-'.$i;
            }
            $used[] = $id;
            $h->setAttribute('id', $id);
            $toc[] = ['id' => $id, 'text' => $text, 'level' => (int) substr($h->nodeName, 1)];
        }
        $out = '';
        $body = $doc->getElementsByTagName('body')->item(0);
        foreach ($body->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return ['html' => $out, 'toc' => $toc];
    }
}
