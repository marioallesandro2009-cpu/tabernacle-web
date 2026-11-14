<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tour;
use App\Support\Content;
use App\Support\Locale;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    /**
     * Both languages: every page at its Indonesian address, and at its /en address when it has real
     * English content. Each entry lists its language versions (hreflang), as Google recommends.
     */
    public function sitemap(): Response
    {
        $xml = Content::remember('sitemap', function () {
            $items = []; // [path, lastmod, priority, has English]
            foreach (Page::where('is_active', true)->where('noindex', false)->with('sections')->get() as $p) {
                $items[] = [$p->key === 'home' ? '/' : '/'.$p->slug, $p->updated_at, $p->key === 'home' ? '1.0' : '0.8', $p->hasEnglish()];
            }
            foreach (Tour::active()->get() as $t) {
                $items[] = ['/packages/'.$t->slug, $t->updated_at, '0.7', $t->hasEnglish()];
            }
            foreach (Destination::where('is_active', true)->get() as $d) {
                $items[] = ['/destinations/'.$d->slug, $d->updated_at, '0.6', $d->hasEnglish()];
            }
            foreach (Post::live()->get() as $p) {
                $items[] = ['/blog/'.$p->slug, $p->updated_at, '0.7', $p->hasEnglish()];
            }
            $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";
            foreach ($items as [$path, $mod, $prio, $en]) {
                $versions = ['id' => url(Locale::path($path, 'id'))] + ($en ? ['en' => url(Locale::path($path, 'en'))] : []);
                $links = '';
                foreach ($versions as $lang => $href) {
                    $links .= '<xhtml:link rel="alternate" hreflang="'.$lang.'" href="'.e($href).'"/>';
                }
                $links .= '<xhtml:link rel="alternate" hreflang="x-default" href="'.e($versions['id']).'"/>';
                foreach ($versions as $loc) {
                    $out .= '  <url><loc>'.e($loc).'</loc>'.($mod ? '<lastmod>'.$mod->toAtomString().'</lastmod>' : '').'<priority>'.$prio.'</priority>'.$links.'</url>'."\n";
                }
            }

            return $out.'</urlset>'."\n";
        }, 3600);

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $txt = trim((string) setting('robots_txt', "User-agent: *\nAllow: /\nDisallow: /admin\n"));
        if (! preg_match('/^sitemap:/mi', $txt)) {
            $txt .= "\n\nSitemap: ".url('/sitemap.xml');
        }

        return response($txt."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function feed(): Response
    {
        $lang = app()->getLocale();
        $xml = Content::remember('feed:'.$lang, function () use ($lang) {
            $posts = Post::live()->with('author')->orderByDesc('published_at')->limit(30)->get()->each->localize();
            $title = e(trim(setting('site_name', '').' Blog'));
            $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>'."\n";
            $out .= '<title>'.$title.'</title><link>'.e(lurl('/blog')).'</link><description>'.e(tr(setting('default_description'))).'</description><language>'.$lang.'</language>'."\n";
            $out .= '<atom:link href="'.e(lurl('/feed.xml')).'" rel="self" type="application/rss+xml"/>'."\n";
            foreach ($posts as $p) {
                $out .= '<item><title>'.e($p->title).'</title><link>'.e(lurl('/blog/'.$p->slug)).'</link><guid isPermaLink="true">'.e(lurl('/blog/'.$p->slug)).'</guid>'
                    .'<pubDate>'.$p->published_at->toRssString().'</pubDate><description>'.e($p->excerpt ?: '').'</description></item>'."\n";
            }

            return $out.'</channel></rss>'."\n";
        }, 3600);

        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }
}
