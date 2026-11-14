<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The ID / EN switch: remembers the choice and goes to the same page in the other language. */
class LangController extends Controller
{
    public function switch(Request $request, string $lang): RedirectResponse
    {
        abort_unless(in_array($lang, Locale::LOCALES, true), 404);
        $back = (string) $request->query('back', '/');
        // only ever go back to a path on this site
        if (! str_starts_with($back, '/') || str_starts_with($back, '//') || str_contains($back, '\\')) {
            $back = '/';
        }

        return redirect(Locale::path($back, $lang))->withCookie(cookie('lang', $lang, 60 * 24 * 365, '/', null, $request->isSecure(), false));
    }
}
