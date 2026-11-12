<?php

namespace App\Support;

use App\Models\Destination;
use App\Models\Faq;
use App\Models\Gallery;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\TeamMember;
use App\Models\Testimonial;
use App\Models\Tour;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Cached reads used by the public pages. Everything is rebuilt after any admin save. */
class SiteData
{
    /** Width pattern of the uneven package grid. */
    public const SPANS = [7, 5, 4, 4, 4, 5, 7, 4, 4, 4];

    /** @return Collection<int, MenuItem> */
    public static function menu(string $location): Collection
    {
        return Content::remember('menu:'.$location, fn () => MenuItem::query()
            ->where('location', $location)->whereNull('parent_id')->where('is_active', true)
            ->with('children')->orderBy('sort')->get());
    }

    public static function page(string $key): ?Page
    {
        return Content::remember('page:'.$key, fn () => Page::query()
            ->with(['sections' => fn ($q) => $q->where('is_visible', true)])->where('key', $key)->first());
    }

    /**
     * Light-weight cards for every active tour.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function tourCards(): array
    {
        return Content::remember('tour_cards', function () {
            return Tour::query()->active()->with(['category', 'destinations'])->orderBy('sort')->orderBy('id')->get()->map(function (Tour $t) {
                $countries = $t->destinations->pluck('slug')->all();
                $searchParts = [$t->name, $t->route, tr($t->area_label, 'en'), ...$t->destinations->map(fn ($d) => tr($d->name, 'en'))->all(),
                    ...($t->highlights ?: []), ...collect($t->itinerary ?: [])->pluck('title')->all()];

                return [
                    'id' => $t->id, 'slug' => $t->slug, 'name' => $t->name, 'duration' => $t->duration, 'area' => $t->area_label,
                    'kind' => ($t->category_id ? $t->category->kind : null) ?? ($t->flights_included ? 'group' : 'fit'), 'region' => $t->region,
                    'countries' => $countries, 'search' => self::norm(implode(' ', $searchParts)),
                    'price' => $t->fromPrice(), 'min_pax' => $t->min_pax, 'flights' => (bool) $t->flights_included,
                    'cover' => $t->cover_id, 'featured' => (bool) $t->is_featured, 'featured_sort' => (int) $t->featured_sort,
                    'promo' => $t->promo_label, 'credit' => $t->cover_credit,
                ];
            })->all();
        });
    }

    /** @return array<int, array<string, mixed>> */
    public static function featuredCards(): array
    {
        return collect(self::tourCards())->where('featured', true)->sortBy('featured_sort')->values()->all();
    }

    /** @return Collection<int, Destination> */
    public static function destinations(): Collection
    {
        return Content::remember('destinations', fn () => Destination::query()->where('is_active', true)
            ->withCount(['tours as tours_count' => fn ($q) => $q->active()])->orderBy('sort')->orderBy('id')->get());
    }

    /** @return Collection<int, Faq> */
    public static function faqs(): Collection
    {
        return Content::remember('faqs', fn () => Faq::where('is_active', true)->orderBy('sort')->get());
    }

    /** @return Collection<int, Testimonial> */
    public static function testimonials(): Collection
    {
        return Content::remember('testimonials', fn () => Testimonial::where('is_active', true)->orderBy('sort')->get());
    }

    /** @return Collection<int, Gallery> */
    public static function albums(): Collection
    {
        return Content::remember('albums', fn () => Gallery::where('is_active', true)->orderBy('sort')->get());
    }

    /** @return Collection<int, TeamMember> */
    public static function team(): Collection
    {
        return Content::remember('team', fn () => TeamMember::where('is_active', true)->orderBy('sort')->get());
    }

    /** Lowercase, accent-free words only: what the package search compares against. */
    public static function norm(string $s): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower(Str::ascii($s))));
    }

    /** Region keys that actually have packages, in a stable order. @return array<int, string> */
    public static function regions(): array
    {
        $used = collect(self::tourCards())->pluck('region')->unique()->all();

        return array_values(array_filter(['asia', 'europe', 'america', 'oceania', 'middle-east', 'central-asia'], fn ($r) => in_array($r, $used, true)));
    }
}
