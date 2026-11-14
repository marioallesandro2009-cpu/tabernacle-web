<?php

use App\Models\ActivityLog;
use App\Models\AnalyticsDaily;
use App\Models\Destination;
use App\Models\Lead;
use App\Models\Page;
use App\Models\PageView;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Support\Analytics;
use App\Support\AnalyticsAggregator;
use App\Support\Settings;
use Illuminate\Http\Request;

function makePage(string $key, ?string $slug = null): Page
{
    return Page::create(['key' => $key, 'slug' => $slug ?? $key, 'title' => ['en' => ucfirst($key), 'id' => ucfirst($key)], 'is_active' => true]);
}

function makeTour(string $name = 'Japan 6D5N', array $o = []): Tour
{
    $cat = TourCategory::firstOrCreate(['slug' => 'group'], ['name' => ['en' => 'Group', 'id' => 'Grup'], 'kind' => 'group']);

    return Tour::create(array_merge(['category_id' => $cat->id, 'name' => $name, 'slug' => str($name)->slug()->toString(), 'duration' => '6D5N', 'region' => 'asia', 'is_active' => true], $o));
}

it('serves public pages that exist and a friendly 404 for the rest', function () {
    foreach (['about', 'contact', 'packages', 'blog', 'destinations'] as $k) {
        makePage($k);
    }
    makePage('home', '/');
    foreach (['/', '/about', '/contact', '/packages', '/blog', '/destinations'] as $url) {
        $this->get($url)->assertOk();
    }
    $this->get('/halaman-tidak-ada')->assertNotFound();
});

it('shows a tour page, and hides inactive tours', function () {
    makePage('packages');
    $t = makeTour();
    $off = makeTour('Hidden Trip', ['is_active' => false]);
    $this->get('/packages/'.$t->slug)->assertOk()->assertSee('Japan 6D5N');
    $this->get('/packages/'.$off->slug)->assertNotFound();
});

it('builds a sitemap with live content only and a robots.txt from settings', function () {
    $t = makeTour();
    Post::create(['title' => 'Live', 'slug' => 'live-post', 'status' => 'published', 'published_at' => now()->subDay(), 'content' => '<p>x</p>']);
    Post::create(['title' => 'Draft', 'slug' => 'draft-post', 'status' => 'draft', 'content' => '<p>x</p>']);
    Post::create(['title' => 'Future', 'slug' => 'future-post', 'status' => 'scheduled', 'published_at' => now()->addDay(), 'content' => '<p>x</p>']);
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
    expect($xml)->toContain('/packages/'.$t->slug)->toContain('/blog/live-post')->not->toContain('draft-post')->not->toContain('future-post');

    Settings::set('robots_txt', "User-agent: *\nDisallow: /admin\nSitemap: https://x.test/sitemap.xml");
    Settings::forget();
    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin', false);
});

it('serves 301 redirects from the redirect manager, keeps the query and counts hits', function () {
    Redirect::create(['from_path' => '/about.html', 'to_url' => '/about', 'code' => 301, 'is_active' => true]);
    Redirect::create(['from_path' => '/lama', 'to_url' => '/packages', 'code' => 302, 'is_active' => false]);
    $this->get('/about.html?utm_source=ig')->assertStatus(301)->assertRedirect('/about?utm_source=ig');
    $this->get('/lama')->assertNotFound();
    expect(Redirect::first()->hits)->toBe(1);
});

it('refuses redirect loops and javascript targets in the admin', function () {
    $this->actingAs(adminUser('admin'));
    $this->post('/admin/redirects', ['from_path' => '/a', 'to_url' => '/a', 'code' => '301', 'is_active' => true])->assertSessionHasErrors('to_url');
    $this->post('/admin/redirects', ['from_path' => '/b', 'to_url' => 'javascript:alert(1)', 'code' => '301', 'is_active' => true])->assertSessionHasErrors('to_url');
    $this->post('/admin/redirects', ['from_path' => 'tanpa-slash', 'to_url' => '/c', 'code' => '301', 'is_active' => true])->assertSessionHasErrors('from_path');
    $this->post('/admin/redirects', ['from_path' => '/ok', 'to_url' => '/c', 'code' => '301', 'is_active' => true])->assertSessionHasNoErrors();
    $this->post('/admin/redirects', ['from_path' => '/ok', 'to_url' => '/d', 'code' => '301', 'is_active' => true])->assertSessionHasErrors('from_path');
});

it('switches language with a cookie', function () {
    makePage('contact');
    $this->get('/lang/en?back=/contact')->assertRedirect('/en/contact')->assertCookie('lang', 'en');
    $this->get('/lang/id?back=/en/contact')->assertRedirect('/contact')->assertCookie('lang', 'id');
});

it('sends security headers', function () {
    makePage('contact');
    $r = $this->get('/contact');
    expect($r->headers->get('X-Content-Type-Options'))->toBe('nosniff')->and($r->headers->get('X-Frame-Options'))->not->toBeNull();
});

it('counts normal visits but ignores bots, ajax and signed-in admins', function () {
    makePage('contact');
    $this->get('/contact')->assertOk();
    expect(PageView::count())->toBe(1);
    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])->get('/contact');
    $this->withHeaders(['User-Agent' => 'AhrefsBot'])->get('/contact');
    $this->getJson('/contact');
    expect(PageView::count())->toBe(1);
    $this->actingAs(adminUser('admin'))->get('/contact');
    expect(PageView::count())->toBe(1);
});

it('records tours as tour views with device and source', function () {
    makePage('packages');
    $t = makeTour();
    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile Safari', 'Referer' => 'https://www.google.com/'])->get('/packages/'.$t->slug)->assertOk();
    $v = PageView::first();
    expect($v->page_type)->toBe('tour')->and($v->ref_id)->toBe($t->id)->and($v->device)->toBe('mobile')->and($v->source)->toBe('google')->and($v->is_entry)->toBeTrue()
        ->and($v->visitor_hash)->not->toContain('127.0.0.1');
});

it('hashes visitors per day so they cannot be followed across days', function () {
    $req = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_USER_AGENT' => 'UA']);
    $a = Analytics::visitorHash($req);
    $this->travel(1)->day();
    $b = Analytics::visitorHash($req);
    expect($a)->not->toBe($b)->and(strlen($a))->toBe(64);
});

it('aggregates a day of views into the daily table', function () {
    $day = now()->toDateString();
    foreach ([['h1', '/a'], ['h1', '/b'], ['h2', '/a']] as [$h, $p]) {
        PageView::create(['day' => $day, 'created_at' => now(), 'path' => $p, 'page_type' => 'page', 'visitor_hash' => $h, 'is_entry' => false, 'source' => 'direct', 'device' => 'desktop', 'browser' => 'Chrome']);
    }
    AnalyticsAggregator::store($day);
    $total = AnalyticsDaily::where('dimension', 'total')->first();
    expect($total->views)->toBe(3)->and($total->visitors)->toBe(2);
    expect(AnalyticsDaily::where(['dimension' => 'path', 'dim_key' => '/a'])->first()->views)->toBe(2);
    AnalyticsAggregator::store($day); // idempotent
    expect(AnalyticsDaily::where('dimension', 'total')->count())->toBe(1);
});

it('shows the dashboard with analytics to permitted roles only', function () {
    $this->actingAs(adminUser('admin'))->get('/admin?range=7d')->assertOk();
    $this->actingAs(adminUser('editor'))->get('/admin')->assertOk();
});

it('publishes scheduled posts when their time comes', function () {
    Post::create(['title' => 'S', 'slug' => 's', 'status' => 'scheduled', 'published_at' => now()->subMinute(), 'content' => '<p>x</p>']);
    Post::create(['title' => 'F', 'slug' => 'f', 'status' => 'scheduled', 'published_at' => now()->addHour(), 'content' => '<p>x</p>']);
    $this->artisan('posts:publish-due')->assertSuccessful();
    expect(Post::where('slug', 's')->first()->status)->toBe('published')->and(Post::where('slug', 'f')->first()->status)->toBe('scheduled');
});

it('keeps a space before the title suffix even when the admin saved it without one', function () {
    Settings::set('title_suffix', '| Tabernacle Ways');
    Settings::forget();
    $d = Destination::create(['name' => ['en' => 'Japan', 'id' => 'Jepang'], 'slug' => 'japan', 'region' => 'asia', 'is_active' => true]);
    $html = $this->get('/destinations/japan')->assertOk()->getContent();
    preg_match('/<title>([^<]*)<\/title>/', $html, $m);
    expect($m[1])->toContain(' | Tabernacle Ways')->not->toMatch('/\S\|/');
});

it('lets only a super admin reset visitor statistics, after typing RESET, without touching leads', function () {
    foreach (range(1, 3) as $i) {
        PageView::create(['day' => now()->toDateString(), 'created_at' => now(), 'path' => '/', 'page_type' => 'page', 'visitor_hash' => 'h'.$i, 'is_entry' => true, 'source' => 'direct', 'device' => 'desktop']);
    }
    AnalyticsAggregator::store(now()->toDateString());
    Lead::create(['type' => 'form', 'name' => 'Keep me', 'phone' => '6281200000000', 'status' => 'baru']);

    $this->actingAs(adminUser('admin'))->post('/admin/stats/reset', ['confirm' => 'RESET'])->assertForbidden();
    $this->actingAs(adminUser('super_admin'))->post('/admin/stats/reset', ['confirm' => 'reset'])->assertSessionHasErrors('confirm');
    expect(PageView::count())->toBe(3);

    $this->post('/admin/stats/reset', ['confirm' => 'RESET'])->assertRedirect('/admin')->assertSessionHas('success');
    expect(PageView::count())->toBe(0)->and(AnalyticsDaily::count())->toBe(0)->and(Lead::count())->toBe(1)
        ->and(ActivityLog::where('action', 'reset')->exists())->toBeTrue();
});
