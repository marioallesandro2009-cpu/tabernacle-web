<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Indonesian → English draft translations through the free MyMemory service. Long text is cut into
 * pieces the service accepts (about 450 bytes), HTML keeps its tags (only the text between them is
 * translated), and every piece is cached so the same sentence never uses the daily quota twice.
 * The result is a draft for staff to check, never published on its own.
 */
class Translator
{
    public const URL = 'https://api.mymemory.translated.net/get';

    /** MyMemory refuses a query above 500 bytes. */
    public const PIECE = 450;

    /** @throws RuntimeException with a message meant for the admin */
    public function translate(string $text, bool $html = false, string $from = 'id', string $to = 'en'): string
    {
        if (trim(strip_tags($text)) === '') {
            return $text;
        }

        return $html ? $this->html($text, $from, $to) : $this->plain($text, $from, $to);
    }

    protected function html(string $html, string $from, string $to): string
    {
        $parts = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($parts as $i => $part) {
            if ($part[0] === '<' || ! preg_match('/\p{L}/u', $part)) {
                continue; // a tag, or only spaces / numbers
            }
            $plain = html_entity_decode($part, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            preg_match('/^\s*/u', $plain, $lead);
            preg_match('/\s*$/u', $plain, $trail);
            $parts[$i] = $lead[0].htmlspecialchars($this->plain(trim($plain), $from, $to), ENT_NOQUOTES, 'UTF-8').$trail[0];
        }

        return implode('', $parts);
    }

    protected function plain(string $text, string $from, string $to): string
    {
        // keep line breaks: translate line by line, and long lines piece by piece
        $lines = preg_split('/(\R)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        foreach ($lines as $i => $line) {
            if (trim($line) === '' || ! preg_match('/\p{L}/u', $line)) {
                continue;
            }
            $lines[$i] = implode(' ', array_map(fn ($p) => $this->piece($p, $from, $to), $this->pieces(trim($line))));
        }

        return implode('', $lines);
    }

    /** @return array<int, string> sentences grouped up to PIECE bytes; a longer sentence is cut at commas or spaces */
    public function pieces(string $text): array
    {
        $out = [];
        $buf = '';
        foreach (preg_split('/(?<=[.!?;:])\s+/u', $text) ?: [] as $sentence) {
            foreach ($this->cut($sentence) as $s) {
                if ($buf !== '' && strlen($buf.' '.$s) > self::PIECE) {
                    $out[] = $buf;
                    $buf = '';
                }
                $buf = $buf === '' ? $s : $buf.' '.$s;
            }
        }
        if ($buf !== '') {
            $out[] = $buf;
        }

        return $out;
    }

    /** @return array<int, string> */
    protected function cut(string $s): array
    {
        if (strlen($s) <= self::PIECE) {
            return [$s];
        }
        $out = [];
        $buf = '';
        foreach (preg_split('/(?<=,)\s+|\s+/u', $s) ?: [] as $word) {
            if ($buf !== '' && strlen($buf.' '.$word) > self::PIECE) {
                $out[] = $buf;
                $buf = '';
            }
            $buf = $buf === '' ? $word : $buf.' '.$word;
        }
        if ($buf !== '') {
            $out[] = $buf;
        }

        return $out;
    }

    protected function piece(string $text, string $from, string $to): string
    {
        $key = 'tr:'.$from.$to.':'.md5($text);
        $cached = Cache::get($key);
        if (is_string($cached)) {
            return $cached;
        }
        $params = ['q' => $text, 'langpair' => $from.'|'.$to];
        if ($email = trim((string) setting('translate_email', ''))) {
            $params['de'] = $email; // a registered address raises the free daily limit
        }
        try {
            $res = Http::timeout(20)->retry(2, 500, throw: false)->acceptJson()->get(self::URL, $params);
        } catch (\Throwable $e) {
            throw new RuntimeException('Layanan terjemahan tidak bisa dihubungi. Coba lagi sebentar lagi.');
        }
        $data = $res->json();
        $out = is_array($data) ? (string) data_get($data, 'responseData.translatedText', '') : '';
        $status = is_array($data) ? (int) data_get($data, 'responseStatus', $res->status()) : $res->status();
        if (data_get($data, 'quotaFinished') || str_contains(strtoupper($out), 'YOU USED ALL AVAILABLE FREE TRANSLATIONS')) {
            throw new RuntimeException('Kuota terjemahan gratis hari ini sudah habis. Coba lagi besok, atau terjemahkan manual.');
        }
        if ($status !== 200 || $out === '' || str_starts_with(strtoupper($out), 'MYMEMORY WARNING') || str_contains(strtoupper($out), 'QUERY LENGTH LIMIT')) {
            throw new RuntimeException('Terjemahan gagal ('.($status ?: 'tanpa jawaban').'). Coba lagi, atau terjemahkan manual.');
        }
        $out = html_entity_decode($out, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        Cache::put($key, $out, now()->addDays(60));

        return $out;
    }
}
