<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\CaseStudy;
use App\Models\Destination;
use App\Models\Pillar;
use App\Models\Post;
use App\Support\Locale;
use App\Support\Seo;
use App\Support\SiteData;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/** Service pillar pages (/layanan) and case studies (/studi-kasus). */
class ServiceController extends Controller
{
    public function pillars(): View
    {
        $pillars = Pillar::where('is_active', true)->orderBy('sort')->get();
        abort_if($pillars->isEmpty(), 404);
        $title = app()->getLocale() === 'en' ? 'Our services' : 'Layanan kami';
        $seo = Seo::make($title, null, null, lurl('/layanan'), titleIsFinal: false, schema: [Seo::breadcrumbs([[t('common.home'), lurl('/')], [$title, lurl('/layanan')]])]);

        return view('site.pillars', ['pillars' => $pillars, 'title' => $title, 'seo' => $seo, 'bodyClass' => 'page-pillars']);
    }

    public function pillar(string $slug, Request $request): View
    {
        $pillar = Pillar::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $request->attributes->set('track_ref_id', $pillar->id);
        Locale::translated($pillar->hasEnglish());

        $catIds = array_map('intval', (array) ($pillar->tour_category_ids ?? []));
        $cards = collect(SiteData::tourCards())->filter(fn ($c) => in_array((int) ($c['category_id'] ?? 0), $catIds, true))->values();
        $countries = $cards->pluck('countries')->flatten()->unique()->all();
        $destinations = Destination::where('is_active', true)->whereIn('slug', $countries)->orderBy('sort')->get();
        $posts = $this->posts($pillar);
        $cases = $pillar->caseStudies()->where('is_active', true)->orderBy('sort')->limit(6)->get();

        $name = tr($pillar->title);
        $seo = Seo::make(tr($pillar->meta_title) ?: $name, tr($pillar->meta_description) ?: strip_tags(tr($pillar->intro)), $pillar->cover_id, lurl('/layanan/'.$pillar->slug),
            titleIsFinal: (bool) tr($pillar->meta_title), schema: [
                Seo::breadcrumbs([[t('common.home'), lurl('/')], [app()->getLocale() === 'en' ? 'Services' : 'Layanan', lurl('/layanan')], [$name, lurl('/layanan/'.$pillar->slug)]]),
                array_filter(['@context' => 'https://schema.org', '@type' => 'Service', 'name' => $name, 'serviceType' => $name, 'description' => strip_tags(tr($pillar->intro)) ?: null,
                    'provider' => ['@type' => 'TravelAgency', 'name' => setting('site_name'), 'url' => url('/')], 'areaServed' => 'ID']),
            ]);

        return view('site.pillar', ['pillar' => $pillar, 'cards' => $cards->all(), 'destinations' => $destinations, 'posts' => $posts, 'cases' => $cases, 'seo' => $seo, 'bodyClass' => 'page-pillar']);
    }

    public function cases(): View
    {
        $cases = CaseStudy::where('is_active', true)->with('pillar')->orderBy('sort')->orderByDesc('id')->get();
        abort_if($cases->isEmpty(), 404);
        $title = app()->getLocale() === 'en' ? 'Case studies' : 'Studi kasus';
        $seo = Seo::make($title, null, null, lurl('/studi-kasus'), titleIsFinal: false, schema: [Seo::breadcrumbs([[t('common.home'), lurl('/')], [$title, lurl('/studi-kasus')]])]);

        return view('site.cases', ['cases' => $cases, 'title' => $title, 'seo' => $seo, 'bodyClass' => 'page-cases']);
    }

    public function case(string $slug, Request $request): View
    {
        $case = CaseStudy::where('slug', $slug)->where('is_active', true)->with(['pillar', 'destinations'])->firstOrFail();
        $request->attributes->set('track_ref_id', $case->id);
        Locale::translated($case->hasEnglish());
        $name = tr($case->title);
        $listTitle = app()->getLocale() === 'en' ? 'Case studies' : 'Studi kasus';
        $related = collect(SiteData::tourCards())->filter(fn ($c) => array_intersect($c['countries'], $case->destinations->pluck('slug')->all()) !== [])->take(3)->values();
        $seo = Seo::make(tr($case->meta_title) ?: $name, tr($case->meta_description) ?: strip_tags(tr($case->objective)), $case->cover_id, lurl('/studi-kasus/'.$case->slug),
            titleIsFinal: (bool) tr($case->meta_title), schema: [Seo::breadcrumbs([[t('common.home'), lurl('/')], [$listTitle, lurl('/studi-kasus')], [$name, lurl('/studi-kasus/'.$case->slug)]])]);

        return view('site.case', ['case' => $case, 'related' => $related->all(), 'listTitle' => $listTitle, 'seo' => $seo, 'bodyClass' => 'page-case']);
    }

    /** @return Collection<int, Post> articles of the pillar's categories, or about its keywords */
    protected function posts(Pillar $pillar): Collection
    {
        $cats = array_map('intval', (array) ($pillar->post_category_ids ?? []));
        $byCat = $cats ? Post::query()->live()->whereIn('category_id', $cats)->orderByDesc('published_at')->limit(3)->get()->each->localize() : collect();

        return $byCat->count() >= 3 ? $byCat : $byCat->merge(SiteData::postsAbout($pillar->keywordList(), 3 - $byCat->count()))->unique('id')->values();
    }
}
