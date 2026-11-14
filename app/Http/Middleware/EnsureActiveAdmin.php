<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** A deactivated admin is signed out right away, even in the middle of a session. */
class EnsureActiveAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->is_active) {
            ActivityLog::auth('forced_logout', $user);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect(admin_path().'/login')->withErrors(['email' => 'Akun ini dinonaktifkan.']);
        }

        return $next($request);
    }
}
