<?php

use App\Models\CaseStudy;
use App\Models\Destination;
use App\Models\Page;
use App\Models\Pillar;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Setting;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Support\Content;
use App\Support\Settings;

beforeEach(function () {
    Settings::forget();
    Content::bump();
});

function structSet(array $values): void
{
    foreach ($values as $k => $v) {
        Setting::updateOrCreate(['key' => $k], ['value' => json_encode($v)]);
    }
    Settings::forget();
    Content::bump();
}

/** A pillar (Wisata Rohani) with a ziarah package to Turki, an article, and a case study. */
function rohani(): array
{
    $ziarah = TourCategory::create(['slug' => 'ziarah', 'name' => ['id' => 'Ziarah', 'en' => 'Pilgrimage'], 'kind' => 'group']);
    $fit = TourCategory::create(['slug' => 'fit', 'name' => ['id' => 'FIT', 'en' => 'FIT'], 'kind' => 'fit']);
    $turki = Destination::create(['name' => ['id' => 'Turki', 'en' => 'Turkey'], 'slug' => 'turki', 'region' => 'europe', 'is_active' => true]);
    $t = Tour::create(['category_id' => $ziarah->id, 'name' => 'Tujuh Gereja Turki', 'slug' => 'tujuh-gereja', 'region' => 'europe', 'is_active' => true, 'price_from' => 1]);
    $t->destinations()->attach($turki);
    Tour::create(['category_id' => $fit->id, 'name' => 'Bali Santai', 'slug' => 'bali', 'region' => 'asia', 'is_active' => true, 'price_from' => 1]);
    $cat = PostCategory::create(['name' => 'Ziarah', 'slug' => 'ziarah']);
    Post::create(['title' => 'Persiapan ziarah', 'slug' => 'persiapan', 'content' => '<p>x</p>', 'category_id' => $cat->id, 'status' => 'published', 'published_at' => now()->subDay()]);
    Post::create(['title' => 'Kuliner Korea', 'slug' => 'kuliner', 'content' => '<p>y</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
    $p = Pillar::where('slug', 'wisata-rohani')->first();
    $p->update(['is_active' => true, 'intro' => ['id' => '<p>Perjalanan iman.</p>', 'en' => ''], 'tour_category_ids' => [$ziarah->id], 'post_category_ids' => [$cat->id]]);
    $case = CaseStudy::create(['slug' => 'gki-turki', 'title' => ['id' => 'Ziarah GKI ke Turki', 'en' => ''], 'client' => 'GKI Contoh', 'pillar_id' => $p->id, 'participants' => 40,
        'period' => ['id' => 'Maret 2026', 'en' => 'March 2026'], 'objective' => ['id' => '<p>Menapak tilas.</p>', 'en' => ''], 'results' => ['id' => '<p>Semua puas.</p>', 'en' => ''], 'is_active' => true]);
    $case->destinations()->attach($turki);
    Content::bump();

    return [$p, $case];
}

it('installs the four service pages and the trust pages as empty, hidden templates', function () {
    expect(Pillar::orderBy('sort')->pluck('slug')->all())->toBe(['corporate-mice', 'studi-banding', 'wisata-rohani', 'tour-reguler'])
        ->and(Pillar::where('is_active', true)->count())->toBe(0)
        ->and(Page::where('key', 'like', 'trust-%')->pluck('slug')->sort()->values()->all())->toBe(['karier', 'kebijakan-privasi', 'media', 'mitra', 'syarat-ketentuan'])
        ->and(Page::where('key', 'like', 'trust-%')->where('is_active', true)->count())->toBe(0);
    $this->get('/layanan/wisata-rohani')->assertNotFound();
    $this->get('/layanan')->assertNotFound();
    $this->get('/kebijakan-privasi')->assertNotFound();
    $this->get('/studi-kasus')->assertNotFound();
});

it('shows a pillar page with its packages, destinations, case studies and articles', function () {
    rohani();
    $html = $this->get('/layanan/wisata-rohani')->assertOk()->getContent();
    expect($html)->toContain('Wisata Rohani')->toContain('Perjalanan iman.')->toContain('Tujuh Gereja Turki')->not->toContain('Bali Santai')
        ->toContain('href="'.url('/destinations/turki').'"')->toContain('Ziarah GKI ke Turki')->toContain('Persiapan ziarah')->not->toContain('Kuliner Korea')
        ->toContain('"@type":"Service"');
    $this->get('/layanan')->assertOk()->assertSee('Wisata Rohani')->assertDontSee('Corporate &amp; MICE', false);
    // no English intro yet: the English address points search engines to the Indonesian one
    $en = $this->get('/en/layanan/wisata-rohani')->assertOk()->getContent();
    expect($en)->toContain('<link rel="canonical" href="'.url('/layanan/wisata-rohani').'">');
});

it('shows case studies in a list and in detail, with the related packages', function () {
    rohani();
    $this->get('/studi-kasus')->assertOk()->assertSee('Ziarah GKI ke Turki')->assertSee('GKI Contoh');
    $html = $this->get('/studi-kasus/gki-turki')->assertOk()->getContent();
    expect($html)->toContain('Menapak tilas.')->toContain('Semua puas.')->toContain('40')->toContain('Maret 2026')->toContain('Tujuh Gereja Turki')
        ->toContain('href="'.url('/layanan/wisata-rohani').'"');
    CaseStudy::first()->update(['is_active' => false]);
    $this->get('/studi-kasus/gki-turki')->assertNotFound();
});

it('lists pillars and case studies in the sitemap once they are live', function () {
    rohani();
    $xml = $this->get('/sitemap.xml')->getContent();
    expect($xml)->toContain(url('/layanan/wisata-rohani'))->toContain(url('/studi-kasus/gki-turki'))->not->toContain(url('/layanan/corporate-mice'));
});

it('has a case-study section for the home page that stays hidden while empty', function () {
    $home = Page::create(['key' => 'home', 'slug' => '/', 'title' => ['id' => 'Beranda', 'en' => 'Home'], 'is_active' => true, 'is_system' => true]);
    $home->sections()->create(['type' => 'case_studies', 'sort' => 1, 'is_visible' => true, 'data' => ['limit' => 3]]);
    Content::bump();
    expect($this->get('/')->getContent())->not->toContain('case-grid');
    rohani();
    expect($this->get('/')->getContent())->toContain('case-grid')->toContain('Ziarah GKI ke Turki');
});

it('builds a full footer from the company data, showing only what is filled in', function () {
    $empty = $this->get('/tidak-ada')->getContent();
    expect($empty)->not->toContain('NIB ')->not->toContain('Berdiri sejak')->not->toContain('Kebijakan Privasi');

    rohani();
    structSet(['company_name' => 'PT Contoh Wisata', 'company_founded' => '2015', 'legal_nib' => '1234567890123', 'legal_ahu' => 'AHU-0001.AH.01.01',
        'social_instagram' => 'https://instagram.com/contoh', 'contact_email' => 'halo@contoh.test']);
    Page::where('slug', 'kebijakan-privasi')->update(['is_active' => true]);
    Content::bump();
    $html = $this->get('/tidak-ada')->getContent();
    expect($html)->toContain('NIB 1234567890123')->toContain('AHU AHU-0001.AH.01.01')->toContain('Berdiri sejak 2015')->toContain('© '.now()->year.' PT Contoh Wisata')
        ->toContain('href="'.url('/kebijakan-privasi').'"')->not->toContain('href="'.url('/karier').'"')
        ->toContain('href="'.url('/layanan/wisata-rohani').'"')->toContain('href="'.url('/studi-kasus').'"')->toContain('https://instagram.com/contoh');
    expect($this->get('/en/tidak-ada')->getContent())->toContain('Since 2015')->toContain('href="'.url('/en/kebijakan-privasi').'"');
});

it('shows a visible breadcrumb on inner pages, matching the one for Google, and only once', function () {
    [, $case] = rohani();
    Page::create(['key' => 'home', 'slug' => '/', 'title' => ['id' => 'Beranda', 'en' => 'Home'], 'is_active' => true, 'is_system' => true]);
    Page::create(['key' => 'about', 'slug' => 'about', 'title' => ['id' => 'Tentang kami', 'en' => 'About us'], 'is_active' => true, 'is_system' => true]);
    Content::bump();

    $dest = $this->get('/destinations/turki')->getContent();
    expect($dest)->toContain('<nav class="crumbs-bar mono" aria-label="Breadcrumb">')->toContain('<span aria-current="page">Turki</span>')
        ->toContain('"@type":"BreadcrumbList"');
    expect($this->get('/about')->getContent())->toContain('<span aria-current="page">Tentang kami</span>');
    expect($this->get('/studi-kasus/gki-turki')->getContent())->toContain('crumbs-bar')->toContain('href="'.url('/studi-kasus').'"');
    // the package page keeps the breadcrumb inside its header image: not twice
    expect($this->get('/packages/tujuh-gereja')->getContent())->not->toContain('crumbs-bar');
    // no breadcrumb on the home page
    expect($this->get('/')->getContent())->not->toContain('crumbs-bar');
});

it('lets the admin manage pillars and case studies', function () {
    $this->actingAs(adminUser());
    $turki = Destination::create(['name' => ['id' => 'Turki', 'en' => 'Turkey'], 'slug' => 'turki', 'region' => 'europe', 'is_active' => true]);
    $this->get('/admin/pillars')->assertOk()->assertSee('Wisata Rohani');
    $this->get('/admin/case-studies/create')->assertOk();
    $this->post('/admin/case-studies', ['title' => ['id' => 'Studi banding PT X', 'en' => 'PT X study visit'], 'slug' => '', 'client' => 'PT X', 'destinations_ids' => [$turki->id], 'is_active' => true])
        ->assertSessionHasNoErrors();
    $c = CaseStudy::first();
    expect($c->slug)->toBe('pt-x-study-visit')->and($c->destinations->pluck('id')->all())->toBe([$turki->id]);
    $this->actingAs(adminUser('editor', ['email' => 'ed@kita.test']))->get('/admin/case-studies')->assertForbidden();
});
