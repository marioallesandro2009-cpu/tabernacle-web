<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Signs an admin out after ADMIN_IDLE_MINUTES (default 120) without any request. */
class AdminIdleTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $limit = max(5, (int) config('admin.idle_minutes', 120)) * 60;
        $last = (int) $request->session()->get('admin_last_seen', 0);
        if ($last > 0 && now()->getTimestamp() - $last > $limit && Auth::check()) {
            ActivityLog::auth('idle_logout', $request->user());
            Auth::logout(); // also forgets the "remember me" cookie, so it cannot sign straight back in
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $message = 'Anda keluar otomatis karena tidak aktif selama '.intdiv($limit, 60).' menit. Silakan masuk lagi.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 401)
                : redirect(admin_path('/login'))->withErrors(['email' => $message]);
        }
        $request->session()->put('admin_last_seen', now()->getTimestamp());

        return $next($request);
    }
}
