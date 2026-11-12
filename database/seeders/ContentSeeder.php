<?php

namespace Database\Seeders;

use App\Models\Destination;
use App\Models\Media;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\Setting;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Models\UiString;
use App\Services\MediaService;
use App\Support\Content;
use Illuminate\Database\Seeder;

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
        Content::bump();
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
                'slug' => $p['slug'], 'title' => $p['title'], 'meta_title' => $p['meta_title'], 'meta_description' => $p['meta_description'], 'is_active' => true, 'is_system' => true,
            ]);
            if ($page->sections()->exists()) {
                continue;
            }
            foreach ($p['sections'] as $i => $s) {
                $page->sections()->create(['type' => $s['type'], 'name' => $s['name'], 'data' => $this->resolve($s['data']), 'sort' => $i + 1, 'is_visible' => $s['is_visible'] ?? true]);
            }
        }
    }

    protected function redirects(): void
    {
        foreach ($this->json('redirects.json') as $r) {
            Redirect::firstOrCreate(['from_path' => $r['from_path']], ['to_url' => $r['to_url'], 'code' => $r['code'], 'is_active' => true]);
        }
    }
}
