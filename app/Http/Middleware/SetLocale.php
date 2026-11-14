<?php

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The language comes from the address: /en/... is English, everything else Indonesian. Form posts
 * and beacons take the language of the page they came from. Old links (?lang=en, or a saved
 * English choice from before the /en addresses) are sent to the right address.
 */
class SetLocale
{
    /** Public page routes (both languages) that may be redirected to the other language. */
    public const PAGE_ROUTES = ['home', 'about', 'contact', 'packages', 'tour', 'destinations', 'destination', 'blog', 'post', 'page.custom'];

    public function handle(Request $request, Closure $next): Response
    {
        Locale::reset();
        $first = $request->segment(1);
        $lang = match (true) {
            $first === 'en' => 'en',
            $request->isMethod('GET') && $this->isPage($request) => 'id',
            default => Locale::fromReferer($request->headers->get('referer')),
        };
        app()->setLocale($lang);

        if ($request->isMethod('GET') && $this->isPage($request)) {
            // old links with ?lang=…: one permanent address per language
            $q = $request->query('lang');
            if (in_array($q, Locale::LOCALES, true)) {
                $query = collect($request->query())->except('lang')->all();
                $target = Locale::path($request->getPathInfo(), $q).($query ? '?'.http_build_query($query) : '');

                return redirect($target, 301)->withCookie(cookie('lang', $q, 60 * 24 * 365, '/', null, $request->isSecure(), false));
            }
            // someone who chose English before keeps getting English (search engines never carry this cookie)
            if ($lang === 'id' && $request->cookie('lang') === 'en') {
                return redirect(Locale::here('en'), 302);
            }
        }

        return $next($request);
    }

    protected function isPage(Request $request): bool
    {
        $name = (string) $request->route()?->getName();

        return in_array(preg_replace('/^en\./', '', $name), self::PAGE_ROUTES, true);
    }
}
