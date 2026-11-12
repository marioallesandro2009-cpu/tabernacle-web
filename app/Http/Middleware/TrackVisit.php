<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use App\Support\Analytics;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts public page views on the server. Bots, logged-in admins, prefetches and
 * anything that is not a normal HTML page are skipped. No raw IP is stored.
 */
class TrackVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $this->record($request, $response);
        } catch (\Throwable) {
            // tracking must never break a page
        }

        return $response;
    }

    protected function record(Request $request, Response $response): void
    {
        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200 || ! setting('analytics_enabled', true)) {
            return;
        }
        if (auth()->check() || Analytics::isBot($request->userAgent()) || $request->ajax() || $request->headers->get('Purpose') === 'prefetch' || $request->headers->get('Sec-Purpose') === 'prefetch') {
            return;
        }
        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return;
        }

        $route = $request->route();
        $type = $route?->defaults['track_type'] ?? 'page';
        $refId = $request->attributes->get('track_ref_id');
        $hash = Analytics::visitorHash($request);
        $day = now()->toDateString();
        $host = Analytics::referrerHost($request);
        $utm = $request->query('utm_source');

        PageView::create([
            'day' => $day,
            'created_at' => now(),
            'path' => '/'.ltrim($request->path(), '/'),
            'page_type' => $type,
            'ref_id' => $refId,
            'visitor_hash' => $hash,
            'is_entry' => ! PageView::where('day', $day)->where('visitor_hash', $hash)->exists(),
            'referrer_host' => $host,
            'source' => Analytics::source($host, $utm),
            'utm_source' => $this->short($utm),
            'utm_medium' => $this->short($request->query('utm_medium')),
            'utm_campaign' => $this->short($request->query('utm_campaign')),
            'device' => Analytics::device($request->userAgent()),
            'browser' => Analytics::browser($request->userAgent()),
        ]);
    }

    protected function short(mixed $v): ?string
    {
        return is_string($v) && $v !== '' ? mb_substr($v, 0, 100) : null;
    }
}
