<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Tag;
use App\Support\ContentTools;
use App\Support\Locale;
use App\Support\Seo;
use App\Support\SiteData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(Request $request): View
    {
        $page = SiteData::page('blog');
        abort_unless($page && $page->is_active, 404);

        $q = Post::query()->live()->with(['category', 'author'])->orderByDesc('published_at');
        $category = $request->query('category') ? PostCategory::where('slug', $request->query('category'))->first()?->localize() : null;
        $tag = $request->query('tag') ? Tag::where('slug', $request->query('tag'))->first() : null;
        if ($category) {
            $q->where('category_id', $category->id);
        }
        if ($tag) {
            $q->whereHas('tags', fn ($t) => $t->whereKey($tag->id));
        }
        $posts = $q->paginate(9)->withQueryString();
        $posts->getCollection()->each->localize();

        $title = $category ? $category->name.' | Blog' : (tr($page->meta_title) ?: tr($page->title));
        $seo = Seo::make($title, tr($page->meta_description), $page->og_image_id, lurl('/blog'.($category ? '?category='.$category->slug : '')),
            noindex: (bool) ($page->noindex || $request->query('page', 1) > 1 || $tag), schema: [Seo::breadcrumbs([[t('common.home'), lurl('/')], [t('blog.title'), lurl('/blog')]])]);

        return view('site.blog-index', [
            'page' => $page, 'posts' => $posts, 'categories' => PostCategory::withCount(['posts' => fn ($p) => $p->live()])->orderBy('name')->get()->each->localize(),
            'category' => $category, 'tag' => $tag, 'seo' => $seo, 'bodyClass' => 'page-blog',
        ]);
    }

    public function show(string $slug, Request $request): View
    {
        $post = Post::query()->live()->with(['category', 'author', 'tags'])->where('slug', $slug)->firstOrFail();
        $request->attributes->set('track_ref_id', $post->id);

        return $this->render($post);
    }

    /** Shared by the public route and the admin preview (drafts, never indexed). */
    public function render(Post $post, bool $preview = false): View
    {
        $post->loadMissing(['category', 'author', 'tags']);
        Locale::translated($post->hasEnglish());
        $post->localize();

        $parsed = ContentTools::withToc($post->content);
        $related = Post::query()->live()->where('id', '!=', $post->id)
            ->when($post->category_id, fn ($q) => $q->orderByRaw('category_id = ? desc', [$post->category_id]))
            ->orderByDesc('published_at')->limit(3)->get()->each->localize();
        $tours = array_slice(SiteData::featuredCards(), 0, 3);

        $url = lurl('/blog/'.$post->slug);
        $img = media_url($post->og_image_id ?: $post->cover_id, 1600);
        $schema = [
            Seo::breadcrumbs([[t('common.home'), lurl('/')], [t('blog.title'), lurl('/blog')], [$post->title, $url]]),
            array_filter([
                '@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $post->title, 'description' => $post->meta_description ?: $post->excerpt,
                'image' => $img ? [$img] : null, 'datePublished' => $post->published_at?->toAtomString(), 'dateModified' => $post->updated_at?->toAtomString(),
                'author' => ['@type' => 'Person', 'name' => $post->author?->name ?: setting('site_name')],
                'publisher' => array_filter(['@type' => 'Organization', 'name' => setting('site_name'), 'logo' => ['@type' => 'ImageObject', 'url' => media_url(setting('logo_dark') ?: setting('logo_light'))]]),
                'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url], 'wordCount' => ContentTools::wordCount($post->content), 'keywords' => $post->tags->pluck('name')->implode(', ') ?: null,
            ]),
        ];
        foreach (ContentTools::videos((string) $post->content) as $i => $v) {
            // VideoObject: lets Google show the article with a video thumbnail
            $schema[] = array_filter([
                '@context' => 'https://schema.org', '@type' => 'VideoObject',
                'name' => $post->title.($i ? ' ('.($i + 1).')' : ''),
                'description' => $post->meta_description ?: ($post->excerpt ?: $post->title),
                'thumbnailUrl' => [$v['poster'] ?: ($img ?: media_url(setting('og_default_image'), 1600))],
                'uploadDate' => ($post->published_at ?? $post->created_at)?->toAtomString(),
                'contentUrl' => $v['type'] === 'file' ? $v['src'] : null,
                'embedUrl' => $v['type'] === 'youtube' ? $v['src'] : null,
            ]);
        }
        if (! empty($post->faq)) {
            $schema[] = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => collect($post->faq)->filter(fn ($f) => ! empty($f['q']) && ! empty($f['a']))
                ->map(fn ($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]])->values()->all()];
        }
        $seo = Seo::make($post->meta_title ?: $post->title.' | '.setting('site_name'), $post->meta_description ?: $post->excerpt, $post->og_image_id ?: $post->cover_id, $url, 'article', noindex: $preview, schema: $schema);

        return view('site.blog-post', ['post' => $post, 'html' => $parsed['html'], 'toc' => $parsed['toc'], 'related' => $related, 'tours' => $tours, 'seo' => $seo, 'bodyClass' => 'page-post', 'preview' => $preview]);
    }
}
