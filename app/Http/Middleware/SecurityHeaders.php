<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every page, including a Content-Security-Policy. Scripts run only from this
 * site, from a few named services (GA4/Tag Manager, Meta Pixel, YouTube, Cloudflare Turnstile) or
 * with this request's nonce, which the layouts put on their own inline scripts and on the tracking
 * code pasted in Pengaturan. CSP_MODE=report only reports, CSP_MODE=off sends no CSP.
 */
class SecurityHeaders
{
    public const SOURCES = [
        'script-src' => ['https://challenges.cloudflare.com', 'https://www.googletagmanager.com', 'https://www.google-analytics.com', 'https://ssl.google-analytics.com',
            'https://connect.facebook.net', 'https://www.youtube.com', 'https://s.ytimg.com'],
        'style-src' => ["'unsafe-inline'", 'https://fonts.googleapis.com'],
        'font-src' => ['data:', 'https://fonts.gstatic.com'],
        'img-src' => ['data:', 'blob:', 'https:'],
        'media-src' => ['blob:', 'https:'],
        'frame-src' => ['https://www.youtube.com', 'https://www.youtube-nocookie.com', 'https://challenges.cloudflare.com', 'https://www.googletagmanager.com',
            'https://td.doubleclick.net', 'https://www.facebook.com', 'https://www.google.com'],
        'connect-src' => ['https://*.google-analytics.com', 'https://*.analytics.google.com', 'https://www.googletagmanager.com', 'https://stats.g.doubleclick.net',
            'https://connect.facebook.net', 'https://www.facebook.com', 'https://challenges.cloudflare.com'],
        'worker-src' => ['blob:'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $mode = (string) config('admin.csp', 'enforce');
        $csp = $mode !== 'off' && ! Vite::isRunningHot(); // the Vite dev server injects its own inline scripts
        if ($csp) {
            Vite::useCspNonce();
        }

        $response = $next($request);
        $h = $response->headers;
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'SAMEORIGIN');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        if ($request->is(trim(admin_path(), '/'), trim(admin_path(), '/').'/*')) {
            $h->set('Cache-Control', 'no-store, private');
        }
        if ($csp && ! $h->has('Content-Security-Policy')) {
            $h->set($mode === 'report' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy', self::policy((string) Vite::cspNonce(), $request->isSecure()));
        }

        return $response;
    }

    public static function policy(string $nonce, bool $https): string
    {
        $parts = [
            "default-src 'self'",
            "script-src 'self' 'nonce-$nonce' ".implode(' ', self::SOURCES['script-src']),
        ];
        foreach (['style-src', 'font-src', 'img-src', 'media-src', 'frame-src', 'connect-src', 'worker-src'] as $d) {
            $parts[] = "$d 'self' ".implode(' ', self::SOURCES[$d]);
        }
        array_push($parts, "object-src 'none'", "base-uri 'self'", "form-action 'self'", "frame-ancestors 'self'");
        if ($https) {
            $parts[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $parts);
    }
}
