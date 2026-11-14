<?php

namespace App\Models;

use App\Models\Concerns\FlushesContent;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    use FlushesContent, LogsActivity;

    /** Addresses already used by the site itself; a custom page cannot take them. */
    public const RESERVED_SLUGS = [
        'about', 'contact', 'packages', 'destinations', 'blog', 'admin', 'lang', 'leads', 'track', 'up', 'en', 'id', 'u',
        'uploads', 'assets', 'build', 'storage', 'sitemap', 'robots', 'feed', 'index', 'home', 'login', 'register',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'title' => 'array', 'meta_title' => 'array', 'meta_description' => 'array',
        'noindex' => 'boolean', 'is_active' => 'boolean', 'is_system' => 'boolean',
    ];

    /** Public address of the page. */
    public function publicUrl(): string
    {
        return $this->key === 'home' ? url('/') : url('/'.ltrim($this->slug, '/'));
    }

    /** @return HasMany<Section, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * The built-in pages are written in both languages section by section; a page made in the admin
     * counts as English once its title and every visible text section have English.
     */
    public function hasEnglish(): bool
    {
        if ($this->is_system) {
            return true;
        }
        if (trim((string) ($this->title['en'] ?? '')) === '') {
            return false;
        }
        foreach ($this->sections as $s) {
            if (! $s->is_visible) {
                continue;
            }
            foreach ((array) ($s->data ?? []) as $v) {
                if (is_array($v) && array_key_exists('id', $v) && array_key_exists('en', $v)
                    && trim(strip_tags((string) $v['id'])) !== '' && trim(strip_tags((string) $v['en'])) === '') {
                    return false;
                }
            }
        }

        return true;
    }
}
