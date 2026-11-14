<?php

use App\Models\Media;
use App\Support\Content;
use App\Support\Settings;
use App\Support\Ui;
use Mews\Purifier\Facades\Purifier;

if (! function_exists('lang_id')) {
    /** True when the visitor is reading the Indonesian version. */
    function lang_id(): bool
    {
        return app()->getLocale() === 'id';
    }
}

if (! function_exists('tr')) {
    /**
     * Pick the visible text from a translatable value: {"en": "...", "id": "..."}.
     * Falls back to the other language, then to the plain string.
     */
    function tr(mixed $value, ?string $locale = null): string
    {
        if (is_array($value)) {
            $locale ??= app()->getLocale();
            $other = $locale === 'id' ? 'en' : 'id';
            foreach ([$locale, $other] as $l) {
                if (isset($value[$l]) && trim((string) $value[$l]) !== '') {
                    return (string) $value[$l];
                }
            }

            return '';
        }

        return (string) ($value ?? '');
    }
}

if (! function_exists('admin_path')) {
    /** "/admin" — or whatever ADMIN_PATH says — plus an optional sub path: admin_path('/leads') → "/admin/leads". */
    function admin_path(string $path = ''): string
    {
        $base = '/'.trim((string) (function_exists('config') && app()->bound('config') ? config('admin.path', 'admin') : 'admin'), '/');

        return $path === '' ? $base : $base.'/'.ltrim($path, '/');
    }
}

if (! function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        return url(admin_path($path));
    }
}

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return Settings::get($key, $default);
    }
}

if (! function_exists('t')) {
    /** Interface text from the database (Admin > Teks Antarmuka). */
    function t(string $key, array $replace = []): string
    {
        return Ui::get($key, $replace);
    }
}

if (! function_exists('media_map')) {
    /** @return array<int, array<string, mixed>> */
    function media_map(): array
    {
        return Content::remember('media_map', fn () => Media::query()
            ->get(['id', 'path', 'variants', 'alt', 'width', 'height', 'kind', 'mime', 'name', 'size'])
            ->keyBy('id')
            ->map(fn ($m) => [
                'path' => $m->path, 'variants' => $m->variants ?: [], 'alt' => $m->alt, 'w' => $m->width,
                'h' => $m->height, 'kind' => $m->kind, 'mime' => $m->mime, 'name' => $m->name, 'size' => $m->size,
            ])->all());
    }
}

if (! function_exists('media_url')) {
    /** Public URL of a media item. With $width, the closest generated size is used. */
    function media_url(int|string|null $id, ?int $width = null): ?string
    {
        if (! $id) {
            return null;
        }
        $m = media_map()[(int) $id] ?? null;
        if (! $m) {
            return null;
        }
        $path = $m['path'];
        if ($width && $m['variants']) {
            ksort($m['variants'], SORT_NUMERIC);
            foreach ($m['variants'] as $w => $p) {
                if ((int) $w >= $width) {
                    $path = $p;
                    break;
                }
            }
        }

        return asset('uploads/'.$path);
    }
}

if (! function_exists('media_srcset')) {
    function media_srcset(int|string|null $id): ?string
    {
        $m = $id ? (media_map()[(int) $id] ?? null) : null;
        if (! $m || ! $m['variants']) {
            return null;
        }
        $parts = [];
        foreach ($m['variants'] as $w => $p) {
            $parts[] = asset('uploads/'.$p).' '.$w.'w';
        }

        return implode(', ', $parts);
    }
}

if (! function_exists('media_alt')) {
    function media_alt(int|string|null $id): string
    {
        return (string) ((media_map()[(int) $id] ?? [])['alt'] ?? '');
    }
}

if (! function_exists('media_attrs')) {
    /** Ready-made <img> attributes: src, srcset, sizes, alt, width, height. */
    function media_attrs(int|string|null $id, string $sizes = '100vw', ?string $alt = null): string
    {
        $m = $id ? (media_map()[(int) $id] ?? null) : null;
        if (! $m) {
            return '';
        }
        $out = 'src="'.e(media_url($id)).'"';
        if ($set = media_srcset($id)) {
            $out .= ' srcset="'.e($set).'" sizes="'.e($sizes).'"';
        }
        if ($m['w'] && $m['h']) {
            $out .= ' width="'.(int) $m['w'].'" height="'.(int) $m['h'].'"';
        }

        return $out.' alt="'.e($alt ?? $m['alt'] ?? '').'"';
    }
}

if (! function_exists('rupiah')) {
    function rupiah(int|float|null $n): string
    {
        return $n ? 'Rp'.number_format((float) $n, 0, ',', '.') : '';
    }
}

if (! function_exists('wa_link')) {
    function wa_link(?string $message = null): string
    {
        $num = preg_replace('/\D/', '', (string) setting('wa_number', ''));
        $url = 'https://wa.me/'.$num;

        return $message ? $url.'?text='.rawurlencode($message) : $url;
    }
}

if (! function_exists('asset_v')) {
    /** Asset URL with a version so browsers fetch new CSS/JS after an update. */
    function asset_v(string $path): string
    {
        $file = public_path($path);

        return asset($path).(is_file($file) ? '?v='.filemtime($file) : '');
    }
}

if (! function_exists('link_attrs')) {
    /** href (and target) for a button link. The value "wa" means "open WhatsApp". */
    function link_attrs(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === 'wa') {
            return 'href="'.e(wa_link()).'" data-wa';
        }
        if ($url === '') {
            return 'href="#"';
        }
        $external = preg_match('#^https?://#i', $url) && ! str_starts_with($url, url('/'));

        return 'href="'.e($url).'"'.($external ? ' target="_blank" rel="noopener"' : '');
    }
}

if (! function_exists('nl')) {
    /** Replace :n style placeholders in editor-written text. @param array<string, string|int> $r */
    function nl(string $text, array $r): string
    {
        foreach ($r as $k => $v) {
            $text = str_replace(':'.$k, (string) $v, $text);
        }

        return $text;
    }
}

if (! function_exists('clean_html')) {
    function clean_html(?string $html, string $profile = 'default'): string
    {
        if (! $html) {
            return '';
        }
        $clean = (string) Purifier::clean($html, $profile);

        // <video> is only allowed for files from this site's own media library (/uploads/...),
        // never hot-linked from elsewhere; posters must be local too
        if (stripos($clean, '<video') !== false) {
            $isLocal = function (?string $u): bool {
                if ($u === null || $u === '') {
                    return false;
                }
                $path = (string) parse_url($u, PHP_URL_PATH);
                $host = parse_url($u, PHP_URL_HOST);
                $own = parse_url((string) config('app.url'), PHP_URL_HOST);

                return str_starts_with($path, '/uploads/') && ($host === null || $host === $own || $host === request()->getHost());
            };
            $clean = (string) preg_replace_callback('#<video\b([^>]*)>(.*?)</video>#is', function ($m) use ($isLocal) {
                preg_match('/\ssrc="([^"]*)"/i', $m[1], $src);
                if (! $isLocal(html_entity_decode($src[1] ?? ''))) {
                    return '';
                }
                $attrs = $m[1];
                if (preg_match('/\sposter="([^"]*)"/i', $attrs, $poster) && ! $isLocal(html_entity_decode($poster[1]))) {
                    $attrs = (string) preg_replace('/\sposter="[^"]*"/i', '', $attrs);
                }
                // always playable with controls, and only fetch the first frames until pressed
                $attrs = (string) preg_replace('/\s(controls|preload)(="[^"]*")?(?=\s|$)/i', '', $attrs);

                return '<video'.$attrs.' controls preload="metadata" playsinline></video>';
            }, $clean);
        }

        return $clean;
    }
}
