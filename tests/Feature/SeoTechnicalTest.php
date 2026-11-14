<?php

use App\Models\Destination;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Support\Content;
use App\Support\Seo;
use App\Support\Settings;

function seoSet(array $values): void
{
    foreach ($values as $k => $v) {
        Setting::updateOrCreate(['key' => $k], ['value' => json_encode($v)]);
    }
    Settings::forget();
    Content::bump();
}

function seoMedia(string $name = 'foto'): Media
{
    return Media::create(['folder' => 'umum', 'kind' => 'image', 'path' => "x/$name.webp", 'variants' => ['480' => "x/$name-480.webp", '960' => "x/$name-960.webp"], 'name' => $name, 'mime' => 'image/webp', 'width' => 1600, 'height' => 900, 'alt' => 'Foto '.$name]);
}

function seoTour(array $o = []): Tour
{
    $cat = TourCategory::firstOrCreate(['slug' => 'group'], ['name' => ['en' => 'Group', 'id' => 'Grup'], 'kind' => 'group']);

    return Tour::create($o + ['category_id' => $cat->id, 'name' => 'Jepang Sakura', 'slug' => 'jepang-sakura', 'duration' => '7D6N', 'region' => 'asia', 'is_active' => true, 'price_from' => 1000000]);
}

beforeEach(fn () => Settings::forget());

it('preloads the two first-screen fonts and the main image of the page', function () {
    $cover = seoMedia('sampul');
    $t = seoTour(['cover_id' => $cover->id]);
    $html = $this->get('/packages/'.$t->slug)->assertOk()->getContent();
    expect($html)->toContain('<link rel="preload" href="'.asset('assets/fonts/archivo.woff2').'" as="font" type="font/woff2" crossorigin>')
        ->toContain('<link rel="preload" href="'.asset('assets/fonts/newsreader.woff2').'" as="font" type="font/woff2" crossorigin>')
        ->toContain('<link rel="preload" as="image" href="'.asset('uploads/x/sampul.webp').'" imagesrcset=')
        ->toContain('fetchpriority="high"');
    // the image itself keeps its size, so nothing jumps
    expect($html)->toContain('width="1600" height="900"');
});

it('loads the first package tile of a list at once and the rest lazily', function () {
    $m = seoMedia('a');
    seoTour(['slug' => 'satu', 'cover_id' => $m->id, 'sort' => 1]);
    seoTour(['slug' => 'dua', 'name' => 'Korea', 'cover_id' => $m->id, 'sort' => 2]);
    $d = Destination::create(['name' => ['id' => 'Jepang', 'en' => 'Japan'], 'slug' => 'jepang', 'region' => 'asia', 'is_active' => true]);
    foreach (Tour::all() as $t) {
        $t->destinations()->attach($d);
    }
    Content::bump();
    $html = $this->get('/destinations/jepang')->assertOk()->getContent();
    preg_match_all('/<a class="pkg[^"]*"[^>]*>\s*<img ([^>]*)>/', $html, $m2);
    expect($m2[1])->toHaveCount(2)->and($m2[1][0])->toContain('fetchpriority="high"')->not->toContain('loading="lazy"')
        ->and($m2[1][1])->toContain('loading="lazy"');
});

it('keeps meta descriptions near 160 characters, cut at a word', function () {
    $long = str_repeat('Perjalanan ziarah ke Turki mengunjungi tujuh gereja kuno. ', 10);
    $d = Seo::trimDescription($long);
    expect(mb_strlen($d))->toBeLessThanOrEqual(160)->and($d)->toEndWith('…')->and($d)->not->toContain('  ');
    expect(Seo::trimDescription('Pendek saja.'))->toBe('Pendek saja.');
});

it('fills the TravelAgency schema from the company data, leaving out what is empty', function () {
    $empty = Seo::organization();
    expect($empty)->not->toHaveKey('foundingDate')->and($empty['address'])->not->toHaveKey('postalCode');

    seoSet(['company_name' => 'PT Contoh Wisata', 'company_founded' => '2015', 'address_city' => 'Tangerang Selatan', 'address_region' => 'Banten', 'address_postal_code' => '15310',
        'contact_email' => 'halo@contoh.test', 'wa_number' => '628123', 'social_instagram' => 'https://instagram.com/contoh', 'address' => ['id' => 'Jl. Contoh 1', 'en' => 'Jl. Contoh 1']]);
    $o = Seo::organization();
    expect($o)->toMatchArray(['@type' => 'TravelAgency', 'legalName' => 'PT Contoh Wisata', 'foundingDate' => '2015', 'sameAs' => ['https://instagram.com/contoh']])
        ->and($o['address'])->toMatchArray(['streetAddress' => 'Jl. Contoh 1', 'addressLocality' => 'Tangerang Selatan', 'addressRegion' => 'Banten', 'postalCode' => '15310', 'addressCountry' => 'ID'])
        ->and($o['contactPoint'][0])->toMatchArray(['@type' => 'ContactPoint', 'telephone' => '+628123', 'email' => 'halo@contoh.test']);
    seoSet(['company_founded' => 'dulu']);
    expect(Seo::organization())->not->toHaveKey('foundingDate');
});

it('adds GA4 and the Search Console tag from the settings, on public pages only', function () {
    Page::create(['key' => 'home', 'slug' => '/', 'title' => ['id' => 'Beranda', 'en' => 'Home'], 'is_active' => true, 'is_system' => true]);
    seoSet(['ga4_id' => 'G-ABC123XYZ', 'google_site_verification' => 'kode-verifikasi-123']);
    $r = $this->get('/');
    preg_match("/'nonce-([^']+)'/", (string) $r->headers->get('Content-Security-Policy'), $n);
    expect($r->getContent())->toContain('<meta name="google-site-verification" content="kode-verifikasi-123">')
        ->toContain('<script async nonce="'.$n[1].'" src="https://www.googletagmanager.com/gtag/js?id=G-ABC123XYZ"></script>')
        ->toContain("gtag('config',\"G-ABC123XYZ\")");
    $this->actingAs(adminUser())->get('/admin')->assertOk()->assertDontSee('googletagmanager.com/gtag', false);

    seoSet(['ga4_id' => '<script>alert(1)</script>']);
    expect($this->get('/')->getContent())->not->toContain('gtag/js');
});

it('links a destination to articles about it', function () {
    $d = Destination::create(['name' => ['id' => 'Turki', 'en' => 'Turkey'], 'slug' => 'turki', 'region' => 'europe', 'is_active' => true]);
    Post::create(['title' => 'Tujuh gereja di Turki', 'slug' => 'tujuh-gereja', 'content' => '<p>x</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
    Post::create(['title' => 'Musim dingin di Korea', 'slug' => 'korea', 'content' => '<p>y</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
    Post::create(['title' => 'Draf tentang Turki', 'slug' => 'draf', 'content' => '<p>z</p>', 'status' => 'draft']);
    $this->get('/destinations/turki')->assertOk()->assertSee('Tujuh gereja di Turki')->assertDontSee('Musim dingin di Korea')->assertDontSee('Draf tentang Turki')
        ->assertSee('href="'.url('/blog/tujuh-gereja').'"', false);
    $this->get('/en/destinations/turki')->assertOk()->assertSee('href="'.url('/en/blog/tujuh-gereja').'"', false);
});

it('shows a friendly 404 with a package search and popular packages, in the language of the address', function () {
    seoTour(['is_featured' => true]);
    Content::bump();
    $r = $this->get('/tidak-ada-halaman-ini')->assertNotFound();
    expect($r->getContent())->toContain('action="'.url('/packages').'#list"')->toContain('name="q"')->toContain('Jepang Sakura')->toContain('Destinasi')
        ->toContain('<meta name="robots" content="noindex, follow">');
    $en = $this->get('/en/packages/tidak-ada')->assertNotFound()->getContent();
    expect($en)->toContain('<html lang="en"')->toContain('action="'.url('/en/packages').'#list"')->toContain('Popular packages');
});

it('finds images without alt text in the media library', function () {
    $a = seoMedia('ada-alt');
    $b = seoMedia('tanpa-alt');
    $b->update(['alt' => '']);
    $this->actingAs(adminUser());
    $ids = collect($this->getJson('/admin/media/list?no_alt=1')->assertOk()->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$b->id]);
});
