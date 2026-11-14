<?php

use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Support\Content;
use App\Support\Locale;

function enPage(string $key): Page
{
    return Page::create(['key' => $key, 'slug' => $key === 'home' ? '/' : $key, 'title' => ['en' => ucfirst($key), 'id' => ucfirst($key).' ID'], 'is_active' => true, 'is_system' => true]);
}

function enTour(string $slug, bool $english): Tour
{
    $cat = TourCategory::firstOrCreate(['slug' => 'group'], ['name' => ['en' => 'Group', 'id' => 'Grup'], 'kind' => 'group']);

    return Tour::create(['category_id' => $cat->id, 'name' => 'Tur '.$slug, 'slug' => $slug, 'duration' => '7D6N', 'region' => 'asia', 'is_active' => true,
        'description' => ['id' => '<p>Deskripsi Indonesia.</p>', 'en' => $english ? '<p>English description.</p>' : ''],
        'itinerary' => [['n' => 1, 'title' => 'Tiba', 'text' => 'Selamat datang.']], 'itinerary_en' => $english ? [['title' => 'Arrive', 'text' => 'Welcome.']] : null,
        'price_from' => 1000000]);
}

/** @return array<string, string> hreflang => href */
function hreflangs(string $html): array
{
    preg_match_all('/<link rel="alternate" hreflang="([^"]+)" href="([^"]+)">/', $html, $m);

    return array_combine($m[1], $m[2]);
}

function canonicalOf(string $html): ?string
{
    return preg_match('/<link rel="canonical" href="([^"]+)">/', $html, $m) ? $m[1] : null;
}

beforeEach(fn () => Content::bump());

it('serves Indonesian at the plain address and English under /en, whatever the browser language', function () {
    enPage('home');
    enPage('packages');
    $id = $this->withHeader('Accept-Language', 'en-US,en;q=0.9')->get('/')->assertOk()->getContent();
    expect($id)->toContain('<html lang="id"');
    $en = $this->get('/en')->assertOk()->getContent();
    expect($en)->toContain('<html lang="en"');
    $this->get('/en/packages')->assertOk()->assertSee('<html lang="en"', false);
    $this->get('/en/halaman-tidak-ada')->assertNotFound();
});

it('gives every translated page hreflang id/en/x-default and its own canonical', function () {
    enPage('packages');
    enTour('jepang', true);

    $id = $this->get('/packages/jepang')->assertOk()->getContent();
    $en = $this->get('/en/packages/jepang')->assertOk()->getContent();
    expect(canonicalOf($id))->toBe(url('/packages/jepang'))->and(canonicalOf($en))->toBe(url('/en/packages/jepang'))
        ->and(hreflangs($id))->toBe(['id' => url('/packages/jepang'), 'en' => url('/en/packages/jepang'), 'x-default' => url('/packages/jepang')])
        ->and(hreflangs($en))->toBe(hreflangs($id))
        ->and($en)->toContain('English description.')->and($id)->toContain('Deskripsi Indonesia.');
});

it('shows an untranslated page in Indonesian under /en, pointing search engines to the Indonesian address', function () {
    enTour('korea', false);
    $en = $this->get('/en/packages/korea')->assertOk()->getContent();
    expect(canonicalOf($en))->toBe(url('/packages/korea'))
        ->and(hreflangs($en))->toBe(['id' => url('/packages/korea'), 'x-default' => url('/packages/korea')])
        ->and($en)->toContain('Deskripsi Indonesia.');

    $post = Post::create(['title' => 'Artikel', 'slug' => 'artikel', 'content' => '<p>Isi.</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
    expect(canonicalOf($this->get('/en/blog/artikel')->getContent()))->toBe(url('/blog/artikel'));
    $post->update(['title_en' => 'Article', 'content_en' => '<p>Body.</p>']);
    Content::bump();
    expect(canonicalOf($this->get('/en/blog/artikel')->getContent()))->toBe(url('/en/blog/artikel'));
});

it('lists both languages in the sitemap, English only for translated content', function () {
    enPage('home');
    enTour('jepang', true);
    enTour('korea', false);
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
    expect($xml)->toContain('<loc>'.url('/packages/jepang').'</loc>')->toContain('<loc>'.url('/en/packages/jepang').'</loc>')
        ->toContain('<loc>'.url('/packages/korea').'</loc>')->not->toContain('<loc>'.url('/en/packages/korea').'</loc>')
        ->toContain('<loc>'.url('/en').'</loc>')->toContain('hreflang="x-default"')->toContain('xmlns:xhtml');
    expect(simplexml_load_string($xml))->not->toBeFalse();
});

it('keeps old addresses working: ?lang=… moves permanently, a saved English choice goes to /en', function () {
    enPage('packages');
    $this->get('/packages?lang=en')->assertStatus(301)->assertRedirect('/en/packages');
    $this->get('/en/packages?lang=id&page=2')->assertStatus(301)->assertRedirect('/packages?page=2');
    $this->withCookie('lang', 'en')->get('/packages')->assertRedirect(url('/en/packages'));
    $this->withCookie('lang', 'id')->get('/packages')->assertOk();
    $this->get('/en/packages')->assertOk(); // no cookie needed
});

it('switches language to the same page in the other language', function () {
    enPage('packages');
    enTour('jepang', true);
    $html = $this->get('/en/packages/jepang')->getContent();
    expect($html)->toContain('/lang/id?back='.urlencode('/en/packages/jepang'));
    $this->get('/lang/id?back=/en/packages/jepang')->assertRedirect('/packages/jepang');
    $this->get('/lang/en?back=/packages/jepang?x=1')->assertRedirect('/en/packages/jepang?x=1');
    $this->get('/lang/en?back=https://jahat.test/')->assertRedirect('/en');
});

it('prefixes site links and menu links on the English site, and leaves outside links alone', function () {
    enPage('home');
    MenuItem::create(['location' => 'header', 'label' => ['en' => 'Packages', 'id' => 'Paket'], 'url' => '/packages', 'sort' => 1, 'is_active' => true]);
    MenuItem::create(['location' => 'header', 'label' => ['en' => 'Services', 'id' => 'Layanan'], 'url' => '/#services', 'sort' => 2, 'is_active' => true]);
    MenuItem::create(['location' => 'header', 'label' => ['en' => 'Instagram', 'id' => 'Instagram'], 'url' => 'https://instagram.com/x', 'sort' => 3, 'is_active' => true]);
    Content::bump();
    $en = $this->get('/en')->getContent();
    expect($en)->toContain('href="/en/packages"')->toContain('href="/en#services"')->toContain('href="https://instagram.com/x"');
    $id = $this->get('/')->getContent();
    expect($id)->toContain('href="/packages"')->toContain('href="/#services"');
});

it('works out paths in both directions', function () {
    expect(Locale::path('/', 'en'))->toBe('/en')->and(Locale::path('/en', 'id'))->toBe('/')
        ->and(Locale::path('/en/blog/x?y=1', 'id'))->toBe('/blog/x?y=1')->and(Locale::path('/blog?c=2', 'en'))->toBe('/en/blog?c=2')
        ->and(Locale::path('/english-tips', 'id'))->toBe('/english-tips')->and(Locale::strip('/en'))->toBe('/')
        ->and(Locale::fromReferer('https://x.test/en/packages'))->toBe('en')->and(Locale::fromReferer('https://x.test/enak'))->toBe('id');
});

it('has an English RSS feed next to the Indonesian one', function () {
    Post::create(['title' => 'Judul', 'title_en' => 'Title', 'slug' => 'a', 'content' => '<p>x</p>', 'content_en' => '<p>y</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
    expect($this->get('/feed.xml')->getContent())->toContain('<language>id</language>')->toContain(url('/blog/a'));
    expect($this->get('/en/feed.xml')->getContent())->toContain('<language>en</language>')->toContain(url('/en/blog/a'));
});

it('lists missing English texts for the admin', function () {
    enTour('korea', false);
    Post::create(['title' => 'Artikel', 'slug' => 'artikel', 'content' => '<p>Isi.</p>', 'status' => 'draft']);
    $this->actingAs(adminUser());
    $rows = collect($this->get('/admin/translations')->assertOk()->viewData('page')['props']['rows']);
    $korea = $rows->firstWhere('name', 'Tur korea');
    expect($korea['blocking'])->toBeTrue()->and($korea['missing'])->toContain('Itinerary')->toContain('Deskripsi')->and($korea['public'])->toBe('/en/packages/korea')
        ->and($rows->firstWhere('name', 'Artikel')['missing'])->toContain('Judul')->toContain('Isi artikel');
    $this->actingAs(adminUser('editor', ['email' => 'ed@kita.test']))->get('/admin/translations')->assertForbidden();
});
