<?php

namespace Database\Seeders;

use App\Models\Destination;
use App\Models\Gallery;
use App\Models\Media;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Redirect;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Models\UiString;
use App\Services\MediaService;
use App\Support\Content;
use App\Support\ContentTools;
use App\Support\SeoAnalyzer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Initial content, taken from the static website. Safe to run again: it never overwrites
 * anything an admin has already changed.
 */
class ContentSeeder extends Seeder
{
    /** @var array<string, int> */
    protected array $media = [];

    protected MediaService $svc;

    protected string $data;

    protected string $assets;

    public function run(): void
    {
        $this->svc = app(MediaService::class);
        $this->data = database_path('seed-data/');
        $this->assets = database_path('seed-assets/');

        $this->importMedia();
        $this->uiStrings();
        $this->settings();
        $this->menus();
        $this->taxonomy();
        $this->tours();
        $this->pages();
        $this->redirects();
        $this->importGalleries();
        $this->importPosts();
        $this->importEnglish();
        Content::bump();
    }

    /**
     * English versions of package texts (tours_en.json) and articles / blog categories
     * (the *_en keys in posts.json). Only fills English fields that are still empty, so
     * anything an admin wrote is never overwritten.
     */
    public function importEnglish(): int
    {
        $this->data ??= database_path('seed-data/');
        $filled = 0;
        foreach ($this->json('tours_en.json') as $slug => $en) {
            $tour = Tour::where('slug', $slug)->first();
            if (! $tour) {
                continue;
            }
            $set = [];
            if (empty($tour->itinerary_en) && ! empty($en['itinerary'])) {
                $set['itinerary_en'] = $en['itinerary'];
            }
            foreach (['includes', 'excludes'] as $k) {
                if (empty($tour->{$k.'_en'}) && ! empty($en[$k])) {
                    $set[$k.'_en'] = $en[$k];
                }
            }
            if (! $tour->dates_label_en && ! empty($en['dates_label'])) {
                $set['dates_label_en'] = $en['dates_label'];
            }
            if (! $tour->terms_en && ! empty($en['terms'])) {
                $set['terms_en'] = clean_html($en['terms']);
            }
            if ($set) {
                $tour->forceFill($set)->saveQuietly();
                $filled++;
            }
        }
        foreach ($this->json('posts.json') as $p) {
            if (! empty($p['category']['name_en'])) {
                PostCategory::where('slug', $p['category']['slug'])->whereNull('name_en')->update(['name_en' => $p['category']['name_en']]);
            }
            $post = Post::where('slug', $p['slug'])->first();
            if (! $post || filled($post->title_en) || empty($p['title_en'])) {
                continue;
            }
            $post->forceFill([
                'title_en' => $p['title_en'], 'excerpt_en' => $p['excerpt_en'] ?? null, 'content_en' => clean_html($p['content_en'] ?? ''),
                'meta_title_en' => $p['meta_title_en'] ?? null, 'meta_description_en' => $p['meta_description_en'] ?? null, 'faq_en' => $p['faq_en'] ?? null,
            ])->saveQuietly();
            $filled++;
        }
        Content::bump();

        return $filled;
    }

    /** @return array<mixed> */
    protected function json(string $file): array
    {
        return json_decode((string) file_get_contents($this->data.$file), true) ?: [];
    }

    protected function media(string $relative, string $folder, ?string $alt, ?string $name = null): ?int
    {
        if (isset($this->media[$relative])) {
            return $this->media[$relative];
        }
        $abs = $this->assets.$relative;
        if (! is_file($abs)) {
            return null;
        }
        $name ??= basename($relative);
        $existing = Media::where('name', $name)->where('folder', $folder)->first();
        $m = $existing ?: $this->svc->import($abs, $name, $folder, $alt);

        return $this->media[$relative] = $m->id;
    }

    protected function importMedia(): void
    {
        foreach ($this->json('media_manifest.json') as $row) {
            $this->media($row['file'], $row['folder'], $row['alt']);
        }
    }

    /** Replace "@img/file.webp" placeholders with media ids, all the way down. */
    protected function resolve(mixed $v): mixed
    {
        if (is_array($v)) {
            return array_map(fn ($x) => $this->resolve($x), $v);
        }
        if (is_string($v) && (str_starts_with($v, '@img/') || str_starts_with($v, '@video/'))) {
            return $this->media[substr($v, 1)] ?? null;
        }

        return $v;
    }

    protected function uiStrings(): void
    {
        foreach ($this->json('ui_strings.json') as $row) {
            UiString::firstOrCreate(['key' => $row['key']], ['group' => $row['group'], 'text_en' => $row['text_en'], 'text_id' => $row['text_id']]);
        }
    }

    protected function settings(): void
    {
        foreach ($this->json('settings.json') as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => json_encode($this->resolve($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        }
    }

    protected function menus(): void
    {
        if (MenuItem::count() > 0) {
            return;
        }
        foreach ($this->json('menus.json') as $row) {
            MenuItem::create($row + ['is_active' => true, 'new_tab' => false]);
        }
    }

    protected function taxonomy(): void
    {
        foreach ($this->json('categories.json') as $row) {
            TourCategory::firstOrCreate(['slug' => $row['slug']], $row + ['is_active' => true]);
        }
        foreach ($this->json('destinations.json') as $row) {
            Destination::firstOrCreate(['slug' => $row['slug']], $row + ['is_active' => true]);
        }
    }

    protected function tours(): void
    {
        $this->importTours();
    }

    /**
     * Imports packages from tours.json that do not exist yet. With $only, just those slugs
     * (used by data migrations so packages an admin deleted are never brought back).
     *
     * @param  array<int, string>  $only
     */
    public function importTours(array $only = []): int
    {
        $this->svc ??= app(MediaService::class);
        $this->data ??= database_path('seed-data/');
        $this->assets ??= database_path('seed-assets/');
        if ($only) {
            foreach ($this->json('destinations.json') as $row) {
                Destination::firstOrCreate(['slug' => $row['slug']], $row + ['is_active' => true]);
            }
        }
        $cats = TourCategory::pluck('id', 'kind');
        $dests = Destination::pluck('id', 'slug');
        $made = 0;
        foreach ($this->json('tours.json') as $t) {
            if (($only && ! in_array($t['slug'], $only, true)) || Tour::where('slug', $t['slug'])->exists()) {
                continue;
            }
            $made++;
            $cover = $this->media($t['cover'], 'paket', $t['cover_alt'], $t['slug'].'.webp');
            $pdf = $this->media($t['pdf'], 'itinerary-pdf', null, $t['slug'].'.pdf');
            $tour = Tour::create([
                'category_id' => $cats[$t['type']] ?? null, 'name' => $t['name'], 'slug' => $t['slug'], 'duration' => $t['duration'], 'route' => $t['route'],
                'area_label' => $t['area_label'], 'region' => $t['region'], 'highlights' => $t['highlights'], 'cover_id' => $cover, 'cover_credit' => $t['cover_credit'],
                'pdf_id' => $pdf, 'itinerary' => $t['itinerary'], 'dates_label' => $t['dates_label'], 'price_from' => $t['price_from'], 'min_pax' => $t['min_pax'],
                'flights_included' => $t['flights_included'], 'includes' => $t['includes'], 'excludes' => $t['excludes'], 'has_shopping' => $t['has_shopping'],
                'is_featured' => $t['is_featured'], 'featured_sort' => $t['featured_sort'], 'is_active' => true, 'sort' => $t['sort'],
                'departures' => $t['departures'] ?? null, 'dp_min' => $t['dp_min'] ?? null, 'terms' => isset($t['terms']) ? clean_html($t['terms']) : null,
                'description' => $t['description'] ?? null, 'available_until' => $t['available_until'] ?? null,
            ]);
            $tour->destinations()->sync(collect($t['countries'])->map(fn ($s) => $dests[$s] ?? null)->filter()->values()->all());
        }
        Content::bump();

        return $made;
    }

    protected function pages(): void
    {
        foreach ($this->json('pages.json') as $p) {
            $page = Page::firstOrCreate(['key' => $p['key']], [
                'slug' => $p['slug'], 'title' => $p['title'], 'meta_title' => $p['meta_title'], 'meta_description' => $p['meta_description'], 'is_active' => true, 'is_system' => $p['is_system'] ?? true,
            ]);
            if ($page->sections()->exists()) {
                continue;
            }
            foreach ($p['sections'] as $i => $s) {
                $page->sections()->create(['type' => $s['type'], 'name' => $s['name'], 'data' => $this->resolve($s['data']), 'sort' => $i + 1, 'is_visible' => $s['is_visible'] ?? true]);
            }
        }
    }

    /**
     * Trip photo albums (galleries.json + seed-assets/gallery). Albums already present
     * (same English title) are skipped, so an admin's deletions or edits are kept.
     */
    public function importGalleries(): int
    {
        $this->svc ??= app(MediaService::class);
        $this->data ??= database_path('seed-data/');
        $this->assets ??= database_path('seed-assets/');
        $made = 0;
        foreach ($this->json('galleries.json') as $g) {
            if (Gallery::where('title->en', $g['title']['en'])->exists()) {
                continue;
            }
            $items = [];
            foreach ($g['items'] as $it) {
                $id = $this->media($it['file'], 'galeri', $it['alt'] ?? null);
                if ($id) {
                    $items[] = ['media_id' => $id, 'caption' => $it['caption'] ?? null];
                }
            }
            Gallery::create([
                'title' => $g['title'], 'trip' => $g['trip'], 'description' => $g['description'] ?? null,
                'cover_id' => $this->media($g['cover'], 'galeri', $g['items'][0]['alt'] ?? null), 'items' => $items,
                'sort' => $g['sort'] ?? 0, 'is_active' => true,
            ]);
            $made++;
        }
        Content::bump();

        return $made;
    }

    /**
     * Starter blog articles (posts.json). Articles whose slug already exists are skipped,
     * so edits and deletions in the admin are kept. SEO score and reading time are computed.
     */
    public function importPosts(): int
    {
        $this->svc ??= app(MediaService::class);
        $this->data ??= database_path('seed-data/');
        $this->assets ??= database_path('seed-assets/');
        $made = 0;
        foreach ($this->json('posts.json') as $p) {
            if (Post::where('slug', $p['slug'])->exists()) {
                continue;
            }
            $cat = PostCategory::firstOrCreate(['slug' => $p['category']['slug']], ['name' => $p['category']['name']]);
            $folder = str_starts_with($p['cover'], 'gallery/') ? 'galeri' : 'paket';
            $cover = $this->media($p['cover'], $folder, $p['title']);
            $content = clean_html($p['content']);
            $post = Post::create([
                'title' => $p['title'], 'slug' => $p['slug'], 'excerpt' => $p['excerpt'], 'content' => $content,
                'cover_id' => $cover, 'category_id' => $cat->id, 'status' => 'published', 'published_at' => now(),
                'focus_keyword' => $p['focus_keyword'], 'meta_title' => $p['meta_title'], 'meta_description' => $p['meta_description'],
                'faq' => $p['faq'] ?? null, 'reading_time' => ContentTools::readingTime($content),
                'seo_score' => SeoAnalyzer::analyze(['title' => $p['title'], 'slug' => $p['slug'], 'focus_keyword' => $p['focus_keyword'],
                    'meta_title' => $p['meta_title'], 'meta_description' => $p['meta_description'], 'content' => $content, 'cover_id' => $cover])['score'],
            ]);
            $post->tags()->sync(collect($p['tags'] ?? [])->map(fn ($t) => Tag::firstOrCreate(['slug' => Str::slug($t)], ['name' => $t])->id)->all());
            $made++;
        }
        Content::bump();

        return $made;
    }

    /** Pages added after the first install (e.g. Galeri), created only if missing. @param array<int, string> $keys */
    public function importPages(array $keys): void
    {
        $this->svc ??= app(MediaService::class);
        $this->data ??= database_path('seed-data/');
        $this->assets ??= database_path('seed-assets/');
        foreach ($this->json('pages.json') as $p) {
            if (! in_array($p['key'], $keys, true) || Page::where('key', $p['key'])->exists() || Page::where('slug', $p['slug'])->exists()) {
                continue;
            }
            $page = Page::create(['key' => $p['key'], 'slug' => $p['slug'], 'title' => $p['title'], 'meta_title' => $p['meta_title'] ?? null,
                'meta_description' => $p['meta_description'] ?? null, 'is_active' => true, 'is_system' => $p['is_system'] ?? false]);
            foreach ($p['sections'] as $i => $s) {
                $page->sections()->create(['type' => $s['type'], 'name' => $s['name'], 'data' => $this->resolve($s['data']), 'sort' => $i + 1, 'is_visible' => $s['is_visible'] ?? true]);
            }
        }
        Content::bump();
    }

    protected function redirects(): void
    {
        foreach ($this->json('redirects.json') as $r) {
            Redirect::firstOrCreate(['from_path' => $r['from_path']], ['to_url' => $r['to_url'], 'code' => $r['code'], 'is_active' => true]);
        }
    }
}
