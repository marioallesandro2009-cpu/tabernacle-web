<?php

namespace App\Support;

use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * Scores an article the way a careful editor would. Every check is pass / warn / fail
 * with a plain-language tip, and the weighted result is a 0-100 score.
 */
class SeoAnalyzer
{
    /**
     * @param  array<string, mixed>  $p  title, slug, focus_keyword, meta_title, meta_description, content, cover_id, og_image_id
     * @return array{score: int, checks: array<int, array<string, mixed>>, stats: array<string, mixed>}
     */
    public static function analyze(array $p): array
    {
        $title = trim((string) ($p['title'] ?? ''));
        $slug = trim((string) ($p['slug'] ?? ''));
        $kw = self::norm((string) ($p['focus_keyword'] ?? ''));
        $metaTitle = trim((string) ($p['meta_title'] ?? '')) ?: $title;
        $metaDesc = trim((string) ($p['meta_description'] ?? ''));
        $html = (string) ($p['content'] ?? '');
        $plain = ContentTools::text($html);
        $words = $plain === '' ? [] : (preg_split('/\s+/u', $plain) ?: []);
        $wc = count($words);
        $has = fn (string $hay) => $kw !== '' && str_contains(' '.self::norm($hay).' ', ' '.$kw.' ');

        $doc = ContentTools::dom($html);
        $xp = $doc ? new DOMXPath($doc) : null;
        $h2 = []; $levels = []; $imgs = 0; $imgsNoAlt = 0; $internal = 0; $external = 0; $paras = []; $h1 = 0;
        if ($xp) {
            foreach ($xp->query('//h1|//h2|//h3|//h4|//h5|//h6') ?: [] as $h) {
                /** @var DOMElement $h */
                $lv = (int) substr($h->nodeName, 1);
                $levels[] = $lv;
                if ($lv === 1) {
                    $h1++;
                }
                if ($lv === 2) {
                    $h2[] = $h->textContent;
                }
            }
            foreach ($xp->query('//img') ?: [] as $im) {
                /** @var DOMElement $im */
                $imgs++;
                if (trim($im->getAttribute('alt')) === '') {
                    $imgsNoAlt++;
                }
            }
            $host = parse_url((string) config('app.url'), PHP_URL_HOST);
            foreach ($xp->query('//a[@href]') ?: [] as $a) {
                /** @var DOMElement $a */
                $href = $a->getAttribute('href');
                $h = parse_url($href, PHP_URL_HOST);
                if (str_starts_with($href, '/') || str_starts_with($href, '#') || ($h && $host && strcasecmp($h, $host) === 0)) {
                    $internal++;
                } elseif (preg_match('#^https?://#i', $href)) {
                    $external++;
                }
            }
            foreach ($xp->query('//p') ?: [] as $para) {
                $paras[] = ContentTools::wordCount($para->textContent);
            }
        }

        // keyword density
        $kwWords = $kw === '' ? 0 : count(explode(' ', $kw));
        $occ = $kw === '' ? 0 : substr_count(' '.self::norm($plain).' ', ' '.$kw.' ');
        $density = ($wc > 0 && $kwWords > 0) ? round($occ * $kwWords / $wc * 100, 2) : 0.0;
        $first100 = implode(' ', array_slice($words, 0, 100));

        // readability: sentence and paragraph length
        $sentences = array_values(array_filter(preg_split('/(?<=[.!?])\s+/u', $plain) ?: [], fn ($s) => trim($s) !== ''));
        $avgSentence = $sentences ? round($wc / count($sentences), 1) : 0;
        $longParas = count(array_filter($paras, fn ($n) => $n > 120));
        $skipped = false;
        $prev = 1;
        foreach ($levels as $lv) {
            if ($lv > $prev + 1) {
                $skipped = true;
            }
            $prev = max($prev, $lv);
            $prev = $lv;
        }

        $slugWords = $slug === '' ? 0 : count(array_filter(explode('-', $slug)));
        $slugHasKw = $kw !== '' && str_contains(self::norm(str_replace('-', ' ', $slug)), $kw);
        $mtLen = mb_strlen($metaTitle);
        $mdLen = mb_strlen($metaDesc);

        $checks = [];
        $add = function (string $id, string $label, string $status, string $tip, int $weight) use (&$checks) {
            $checks[] = ['id' => $id, 'label' => $label, 'status' => $status, 'tip' => $tip, 'weight' => $weight];
        };

        $add('kw', 'Focus keyword diisi', $kw !== '' ? 'pass' : 'fail', $kw !== '' ? 'Bagus, keyword utama sudah ditentukan.' : 'Isi focus keyword, kata atau frasa utama yang ingin dicari orang.', 10);
        $add('title_len', 'Meta title 50–60 karakter ('.$mtLen.')', ($mtLen >= 50 && $mtLen <= 60) ? 'pass' : (($mtLen >= 40 && $mtLen <= 70) ? 'warn' : 'fail'),
            ($mtLen >= 50 && $mtLen <= 60) ? 'Panjang ideal untuk tampilan Google.' : ($mtLen < 50 ? 'Terlalu pendek, tambahkan kata yang menjelaskan isi artikel.' : 'Terlalu panjang, bisa terpotong di Google.'), 8);
        $add('title_kw', 'Meta title mengandung focus keyword', $has($metaTitle) ? 'pass' : 'fail', $has($metaTitle) ? 'Keyword ada di meta title.' : 'Masukkan focus keyword di meta title, lebih baik di awal.', 8);
        $add('desc_len', 'Meta description 120–160 karakter ('.$mdLen.')', ($mdLen >= 120 && $mdLen <= 160) ? 'pass' : (($mdLen >= 90 && $mdLen <= 175) ? 'warn' : 'fail'),
            ($mdLen >= 120 && $mdLen <= 160) ? 'Panjang ideal.' : ($mdLen === 0 ? 'Meta description belum diisi.' : ($mdLen < 120 ? 'Terlalu pendek, jelaskan manfaat artikel.' : 'Terlalu panjang, bisa terpotong.')), 8);
        $add('desc_kw', 'Meta description mengandung focus keyword', $has($metaDesc) ? 'pass' : 'fail', $has($metaDesc) ? 'Keyword ada di meta description.' : 'Masukkan focus keyword di meta description.', 6);
        $add('slug', 'Slug pendek dan mengandung keyword', ($slugWords > 0 && $slugWords <= 6 && $slugHasKw) ? 'pass' : (($slugWords > 0 && ($slugWords <= 6 || $slugHasKw)) ? 'warn' : 'fail'),
            $slugHasKw ? ($slugWords <= 6 ? 'Slug rapi.' : 'Slug mengandung keyword tapi terlalu panjang, usahakan maksimal 6 kata.') : 'Masukkan focus keyword ke slug dan buat singkat.', 6);
        $add('first100', 'Keyword muncul di 100 kata pertama', $has($first100) ? 'pass' : 'fail', $has($first100) ? 'Keyword muncul di awal tulisan.' : 'Sebut keyword di paragraf pembuka.', 8);
        $add('h2', 'Keyword muncul di minimal satu H2', count(array_filter($h2, fn ($t) => $has($t))) > 0 ? 'pass' : 'fail',
            count($h2) === 0 ? 'Belum ada subjudul H2. Bagi tulisan dengan beberapa H2.' : (count(array_filter($h2, fn ($t) => $has($t))) > 0 ? 'Ada H2 yang memuat keyword.' : 'Masukkan keyword ke salah satu H2.'), 6);
        $add('density', 'Kepadatan keyword wajar 0,5–2,5% ('.str_replace('.', ',', (string) $density).'%)', ($density >= 0.5 && $density <= 2.5) ? 'pass' : 'warn',
            $density > 2.5 ? 'Keyword terlalu sering diulang, kurangi agar tidak terkesan spam.' : ($density < 0.5 ? 'Keyword terlalu jarang muncul, sebut sedikit lebih sering secara natural.' : 'Kepadatan keyword wajar.'), 6);
        $add('h1', 'Hanya satu H1 (judul artikel)', $h1 === 0 ? 'pass' : 'fail', $h1 === 0 ? 'Judul artikel otomatis menjadi H1. Mulai isi dengan H2.' : 'Hapus H1 di dalam isi, gunakan H2 dan H3.', 6);
        $add('heading_order', 'Struktur heading berurutan', ! $skipped ? 'pass' : 'fail', ! $skipped ? 'Heading tidak melompat level.' : 'Ada heading yang melompat, misalnya H2 langsung ke H4.', 5);
        $add('length', 'Panjang konten minimal 600 kata ('.$wc.')', $wc >= 600 ? 'pass' : ($wc >= 300 ? 'warn' : 'fail'), $wc >= 600 ? 'Panjang cukup.' : 'Tambahkan isi. Ini peringatan, bukan penghalang untuk terbit.', 6);
        $add('alt', 'Semua gambar punya alt text', $imgsNoAlt === 0 ? 'pass' : 'fail', $imgs === 0 ? 'Belum ada gambar di dalam isi. Tambahkan gambar dengan alt text.' : ($imgsNoAlt === 0 ? 'Semua gambar memakai alt text.' : $imgsNoAlt.' gambar belum punya alt text.'), 6);
        $add('internal', 'Ada minimal 1 link internal', $internal > 0 ? 'pass' : 'warn', $internal > 0 ? 'Ada '.$internal.' link internal.' : 'Tautkan ke artikel atau paket lain di situs ini (lihat saran di bawah).', 5);
        $add('read', 'Kalimat dan paragraf tidak terlalu panjang', ($avgSentence <= 22 && $longParas === 0) ? 'pass' : 'warn',
            ($avgSentence <= 22 && $longParas === 0) ? 'Mudah dibaca.' : 'Rata-rata '.$avgSentence.' kata per kalimat'.($longParas ? ', '.$longParas.' paragraf lebih dari 120 kata' : '').'. Pecah agar lebih ringan.', 5);
        $add('og', 'Gambar Open Graph tersedia', (! empty($p['og_image_id']) || ! empty($p['cover_id'])) ? 'pass' : 'warn', (! empty($p['og_image_id']) || ! empty($p['cover_id'])) ? 'Gambar untuk berbagi di media sosial sudah ada.' : 'Pilih cover atau gambar OG agar tampil menarik saat dibagikan.', 5);

        $total = array_sum(array_column($checks, 'weight'));
        $got = 0;
        foreach ($checks as $c) {
            $got += $c['status'] === 'pass' ? $c['weight'] : ($c['status'] === 'warn' ? $c['weight'] * 0.5 : 0);
        }

        return [
            'score' => (int) round($got / max(1, $total) * 100),
            'checks' => $checks,
            'stats' => ['words' => $wc, 'density' => $density, 'reading_time' => ContentTools::readingTime($html), 'internal_links' => $internal, 'external_links' => $external, 'images' => $imgs],
        ];
    }

    protected static function norm(string $s): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower(Str::ascii($s))));
    }
}
