<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\Tour;
use App\Support\Seo;
use App\Support\SiteData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        return $this->render('home', schema: [Seo::organization()]);
    }

    public function about(): View
    {
        return $this->render('about', crumbs: true);
    }

    public function contact(): View
    {
        return $this->render('contact', schema: [Seo::organization()], crumbs: true);
    }

    public function packages(): View
    {
        return $this->render('packages', crumbs: true);
    }

    public function destinations(): View
    {
        return $this->render('destinations', 'site.destinations', crumbs: true);
    }

    /** @param array<int, array<string, mixed>> $schema */
    protected function render(string $key, string $view = 'site.page', array $schema = [], bool $crumbs = false): View
    {
        $page = SiteData::page($key);
        abort_unless($page && $page->is_active, 404);

        if ($crumbs) {
            $schema[] = Seo::breadcrumbs([[tr(SiteData::page('home')?->title) ?: 'Home', url('/')], [tr($page->title), url()->current()]]);
        }
        $seo = Seo::make(tr($page->meta_title) ?: tr($page->title), tr($page->meta_description), $page->og_image_id, url($key === 'home' ? '/' : '/'.$page->slug),
            noindex: (bool) $page->noindex, schema: $schema, titleIsFinal: (bool) tr($page->meta_title));

        return view($view, ['page' => $page, 'seo' => $seo, 'bodyClass' => 'page-'.$key]);
    }

    public function destination(string $slug, Request $request): View
    {
        $dest = Destination::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $request->attributes->set('track_ref_id', $dest->id);
        $cards = collect(SiteData::tourCards())->filter(fn ($c) => in_array($dest->slug, $c['countries'], true))->values()->all();
        $name = tr($dest->name);
        $seo = Seo::make(tr($dest->meta_title) ?: t('dest.tours', ['name' => $name]), tr($dest->meta_description) ?: strip_tags(tr($dest->intro)), $dest->cover_id, url('/destinations/'.$dest->slug), titleIsFinal: (bool) tr($dest->meta_title), schema: [
            Seo::breadcrumbs([['Home', url('/')], [t('dest.title'), url('/destinations')], [$name, url()->current()]]),
        ]);

        return view('site.destination', ['dest' => $dest, 'cards' => $cards, 'seo' => $seo, 'bodyClass' => 'page-destination']);
    }

    public function tour(string $slug, Request $request): View
    {
        $tour = Tour::query()->active()->with(['category', 'destinations'])->where('slug', $slug)->firstOrFail();
        $request->attributes->set('track_ref_id', $tour->id);

        $cards = collect(SiteData::tourCards());
        $related = $cards->where('region', $tour->region)->where('id', '!=', $tour->id)->take(3);
        if ($related->count() < 3) {
            $related = $related->concat($cards->where('id', '!=', $tour->id)->whereNotIn('id', $related->pluck('id'))->take(3 - $related->count()));
        }
        $name = $tour->name;
        $price = $tour->fromPrice();
        $schema = [
            Seo::breadcrumbs([['Home', url('/')], [t('tour.crumb'), url('/packages')], [$name, url()->current()]]),
            array_filter([
                '@context' => 'https://schema.org', '@type' => 'TouristTrip', 'name' => trim($tour->duration.' '.$name), 'description' => tr($tour->description) ?: ($tour->route ?: $name),
                'image' => media_url($tour->cover_id, 1600), 'url' => url()->current(), 'touristType' => $tour->flights_included ? 'Group tour' : 'FIT',
                'itinerary' => $tour->route ? ['@type' => 'ItemList', 'itemListElement' => collect(explode('–', str_replace('-', '–', $tour->route)))->map(fn ($p, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'item' => ['@type' => 'Place', 'name' => trim($p)]])->values()->all()] : null,
                'provider' => ['@type' => 'TravelAgency', 'name' => setting('site_name'), 'url' => url('/')],
                'offers' => $price ? ['@type' => 'Offer', 'priceCurrency' => 'IDR', 'price' => $price, 'availability' => 'https://schema.org/InStock', 'url' => url()->current()] : null,
            ]),
        ];
        $title = tr($tour->meta_title) ?: trim(($tour->duration ? $tour->duration.' ' : '').$name).' | '.trim(setting('site_name', '').' '.ucwords(strtolower((string) setting('site_suffix', ''))));
        $desc = tr($tour->meta_description) ?: trim(($tour->duration ? $tour->duration.' ' : '').$name.($tour->route ? ' ('.$tour->route.')' : '').'. '.($price ? t('tour.from').' '.rupiah($price).'. ' : '').tr(setting('default_description')));
        $seo = Seo::make($title, $desc, $tour->og_image_id ?: $tour->cover_id, url('/packages/'.$tour->slug), 'website', schema: $schema);

        return view('site.tour', ['tour' => $tour, 'related' => $related->values()->all(), 'seo' => $seo, 'bodyClass' => 'page-tour']);
    }
}
