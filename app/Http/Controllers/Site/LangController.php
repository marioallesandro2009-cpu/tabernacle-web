<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LangController extends Controller
{
    public function switch(Request $request, string $lang): RedirectResponse
    {
        abort_unless(in_array($lang, ['id', 'en'], true), 404);
        $back = (string) $request->query('back', '/');
        // only ever go back to a path on this site
        if (! str_starts_with($back, '/') || str_starts_with($back, '//') || str_contains($back, '\\')) {
            $back = '/';
        }

        return redirect($back)->withCookie(cookie('lang', $lang, 60 * 24 * 365, '/', null, $request->isSecure(), false));
    }
}
