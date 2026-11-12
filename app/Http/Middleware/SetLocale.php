<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Picks Indonesian or English: ?lang= first, then the saved choice, then the browser language. */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $lang = $request->query('lang');
        if (! in_array($lang, ['id', 'en'], true)) {
            $lang = $request->cookie('lang');
        }
        if (! in_array($lang, ['id', 'en'], true)) {
            $lang = str_starts_with(strtolower((string) $request->getPreferredLanguage(['en', 'id'])), 'id') ? 'id' : 'en';
        }
        app()->setLocale($lang);

        $response = $next($request);
        if ($request->query('lang') === $lang && method_exists($response, 'withCookie')) {
            $response->withCookie(cookie('lang', $lang, 60 * 24 * 365, '/', null, $request->isSecure(), false));
        }

        return $response;
    }
}
