<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // super_admin passes every permission check
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        RateLimiter::for('lead', fn (Request $r) => [Limit::perMinute(4)->by($r->ip()), Limit::perDay(30)->by($r->ip())]);
        RateLimiter::for('wa', fn (Request $r) => Limit::perMinute(30)->by($r->ip()));
    }
}
