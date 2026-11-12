<?php

namespace App\Support;

use Illuminate\Http\Request;

/** Small helpers for classifying visitors without any outside service. */
class Analytics
{
    public const BOT_PATTERN = '/bot|crawl|spider|slurp|mediapartners|facebookexternalhit|embedly|quora|pinterest|preview|lighthouse|pagespeed|gtmetrix|headless|phantom|curl\/|wget|python-requests|python-urllib|go-http-client|java\/|libwww|httpclient|okhttp|monitor|uptime|pingdom|statuscake|checkly|ahrefs|semrush|dotbot|petalbot|bingpreview|yandex|baidu|duckduckbot|applebot|scrapy|node-fetch|axios\/|postman/i';

    public static function isBot(?string $ua): bool
    {
        return $ua === null || $ua === '' || (bool) preg_match(self::BOT_PATTERN, $ua);
    }

    public static function device(?string $ua): string
    {
        $ua = (string) $ua;
        if (preg_match('/ipad|tablet|kindle|silk|(android(?!.*mobile))/i', $ua)) {
            return 'tablet';
        }

        return preg_match('/mobi|iphone|ipod|android|blackberry|opera mini|iemobile/i', $ua) ? 'mobile' : 'desktop';
    }

    public static function browser(?string $ua): string
    {
        $ua = (string) $ua;

        return match (true) {
            (bool) preg_match('/edg(e|a|ios)?\//i', $ua) => 'Edge',
            (bool) preg_match('/opr\/|opera/i', $ua) => 'Opera',
            (bool) preg_match('/samsungbrowser/i', $ua) => 'Samsung Internet',
            (bool) preg_match('/firefox|fxios/i', $ua) => 'Firefox',
            (bool) preg_match('/chrome|crios/i', $ua) => 'Chrome',
            (bool) preg_match('/safari/i', $ua) => 'Safari',
            default => 'Lainnya',
        };
    }

    /** direct, google, instagram, tiktok, whatsapp, facebook, youtube, other. */
    public static function source(?string $host, ?string $utmSource = null): string
    {
        $probe = strtolower(trim(($utmSource ?: '').' '.($host ?: '')));
        if ($probe === '') {
            return 'direct';
        }

        return match (true) {
            (bool) preg_match('/google|goog\b|gclid|bing|yahoo|duckduckgo|ecosia|search/', $probe) => 'google',
            (bool) preg_match('/instagram|\big\b|l\.instagram/', $probe) => 'instagram',
            (bool) preg_match('/tiktok/', $probe) => 'tiktok',
            (bool) preg_match('/whatsapp|wa\.me|\bwa\b/', $probe) => 'whatsapp',
            (bool) preg_match('/facebook|fb\.|\bfb\b|messenger|m\.me/', $probe) => 'facebook',
            (bool) preg_match('/youtube|youtu\.be/', $probe) => 'youtube',
            default => 'other',
        };
    }

    /** Host of the referring site, ignoring our own domain. */
    public static function referrerHost(Request $request): ?string
    {
        $ref = $request->headers->get('referer');
        if (! $ref) {
            return null;
        }
        $host = strtolower((string) parse_url($ref, PHP_URL_HOST));

        return ($host === '' || $host === strtolower($request->getHost())) ? null : $host;
    }

    /** A visitor id that changes every day and cannot be reversed into an IP address. */
    public static function visitorHash(Request $request): string
    {
        return hash_hmac('sha256', ($request->ip() ?? '0').'|'.$request->userAgent(), (string) config('app.key').'|'.now()->toDateString());
    }
}
