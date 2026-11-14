<?php

namespace App\Support;

/**
 * Indonesian lives at the plain address (/packages/x), English under /en (/en/packages/x).
 * A page whose content has no English version yet is still served under /en (in Indonesian),
 * but tells search engines that the Indonesian address is the real one, and is left out of the
 * English part of the sitemap.
 */
class Locale
{
    public const LOCALES = ['id', 'en'];

    /** Whether the page being rendered has real English content (null = not said: yes). */
    protected static ?bool $translated = null;

    public static function reset(): void
    {
        self::$translated = null;
        Seo::resetTrail();
    }

    public static function translated(bool $yes): void
    {
        self::$translated = $yes;
    }

    public static function isTranslated(): bool
    {
        return self::$translated !== false;
    }

    /** "/packages/x" without any language prefix. */
    public static function strip(string $path): string
    {
        $path = '/'.ltrim($path, '/');
        if ($path === '/en' || str_starts_with($path, '/en/') || str_starts_with($path, '/en?')) {
            $path = substr($path, 3);
            $path = $path === '' || $path[0] === '?' ? '/'.$path : $path;
        }

        return $path;
    }

    /** The address of $path in $locale: "/packages/x" → "/en/packages/x". */
    public static function path(string $path, string $locale): string
    {
        $path = self::strip($path);
        if ($locale !== 'en') {
            return $path;
        }
        if ($path === '/') {
            return '/en';
        }

        return (str_starts_with($path, '/?') || str_starts_with($path, '/#')) ? '/en'.substr($path, 1) : '/en'.$path;
    }

    /** A menu link in the current language: site paths get the prefix, outside links and #anchors stay. */
    public static function link(?string $url): string
    {
        $url = (string) $url;

        return str_starts_with($url, '/') && ! str_starts_with($url, '//') ? self::path($url, app()->getLocale()) : $url;
    }

    /** Full URL of $url (absolute or a path) in $locale. */
    public static function url(string $url, string $locale): string
    {
        $parts = parse_url($url);
        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        return url(self::path($path, $locale));
    }

    /** The current page in the other language (keeps the query string). */
    public static function here(string $locale): string
    {
        return self::url(request()->getRequestUri(), $locale);
    }

    /**
     * The canonical address: the English one only when the page really has English content,
     * otherwise the Indonesian one.
     */
    public static function canonical(string $url): string
    {
        return self::url($url, app()->getLocale() === 'en' && self::isTranslated() ? 'en' : 'id');
    }

    /**
     * hreflang links for the page: id + x-default always, en only when it is really English.
     *
     * @return array<string, string>
     */
    public static function alternates(string $canonical): array
    {
        $out = ['id' => self::url($canonical, 'id')];
        if (self::isTranslated()) {
            $out['en'] = self::url($canonical, 'en');
        }
        $out['x-default'] = $out['id'];

        return $out;
    }

    /** The language of a request that is not a page (form posts, beacons): from the page it came from. */
    public static function fromReferer(?string $referer): string
    {
        $path = (string) parse_url((string) $referer, PHP_URL_PATH);

        return $path === '/en' || str_starts_with($path, '/en/') ? 'en' : 'id';
    }
}
