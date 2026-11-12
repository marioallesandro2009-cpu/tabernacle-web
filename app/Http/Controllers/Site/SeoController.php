<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tour;
use App\Support\Content;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $xml = Content::remember('sitemap', function () {
            $urls = [];
            foreach (Page::where('is_active', true)->where('noindex', false)->get() as $p) {
                $urls[] = [$p->key === 'home' ? url('/') : url('/'.$p->slug), $p->updated_at, $p->key === 'home' ? '1.0' : '0.8'];
            }
            foreach (Tour::active()->get(['slug', 'updated_at']) as $t) {
                $urls[] = [url('/packages/'.$t->slug), $t->updated_at, '0.7'];
            }
            foreach (Destination::where('is_active', true)->get(['slug', 'updated_at']) as $d) {
                $urls[] = [url('/destinations/'.$d->slug), $d->updated_at, '0.6'];
            }
            foreach (Post::live()->get(['slug', 'updated_at']) as $p) {
                $urls[] = [url('/blog/'.$p->slug), $p->updated_at, '0.7'];
            }
            $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
            foreach ($urls as [$loc, $mod, $prio]) {
                $out .= '  <url><loc>'.e($loc).'</loc>'.($mod ? '<lastmod>'.$mod->toAtomString().'</lastmod>' : '').'<priority>'.$prio.'</priority></url>'."\n";
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
        $xml = Content::remember('feed', function () {
            $posts = Post::live()->with('author')->orderByDesc('published_at')->limit(30)->get();
            $title = e(trim(setting('site_name', '').' Blog'));
            $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>'."\n";
            $out .= '<title>'.$title.'</title><link>'.e(url('/blog')).'</link><description>'.e(tr(setting('default_description'))).'</description><language>id</language>'."\n";
            $out .= '<atom:link href="'.e(url('/feed.xml')).'" rel="self" type="application/rss+xml"/>'."\n";
            foreach ($posts as $p) {
                $out .= '<item><title>'.e($p->title).'</title><link>'.e(url('/blog/'.$p->slug)).'</link><guid isPermaLink="true">'.e(url('/blog/'.$p->slug)).'</guid>'
                    .'<pubDate>'.$p->published_at->toRssString().'</pubDate><description>'.e($p->excerpt ?: '').'</description></item>'."\n";
            }

            return $out.'</channel></rss>'."\n";
        }, 3600);

        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }
}
