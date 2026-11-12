<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Tiny cache layer for public content. Every key carries a version number that
 * changes whenever something is saved in the admin, so stale content never lingers.
 */
class Content
{
    public static function version(): string
    {
        return (string) Cache::rememberForever('content_version', fn () => (string) time());
    }

    public static function bump(): void
    {
        Cache::forever('content_version', (string) hrtime(true));
        Settings::forget();
        Ui::forget();
    }

    public static function remember(string $key, \Closure $callback, int $seconds = 86400): mixed
    {
        return Cache::remember('c'.self::version().':'.$key, $seconds, $callback);
    }
}
