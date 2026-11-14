<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use App\Support\Content;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/** 301/302 redirects managed in the admin (Redirect manager). */
class HandleRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') || $request->is(trim(admin_path(), '/'), trim(admin_path(), '/').'/*')) {
            return $next($request);
        }
        try {
            $map = Content::remember('redirects', fn () => Schema::hasTable('redirects')
                ? Redirect::where('is_active', true)->get(['id', 'from_path', 'to_url', 'code'])->keyBy(fn ($r) => rtrim(strtolower($r->from_path), '/') ?: '/')->map(fn ($r) => [$r->id, $r->to_url, $r->code])->all()
                : []);
        } catch (\Throwable) {
            return $next($request);
        }
        $path = rtrim(strtolower('/'.ltrim($request->getPathInfo(), '/')), '/') ?: '/';
        if (isset($map[$path])) {
            [$id, $to, $code] = $map[$path];
            Redirect::whereKey($id)->increment('hits');
            $qs = $request->getQueryString();
            $target = $to.($qs && ! str_contains($to, '?') ? '?'.$qs : '');

            return redirect($target, in_array($code, [301, 302, 307, 308], true) ? $code : 301);
        }

        return $next($request);
    }
}
