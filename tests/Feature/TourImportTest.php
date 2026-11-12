<?php

use App\Models\Destination;
use App\Models\Gallery;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Support\ContentTools;
use Database\Seeders\ContentSeeder;

beforeEach(function () {
    config(['filesystems.disks.uploads.root' => sys_get_temp_dir().'/tw-uploads-'.uniqid()]);
    app('filesystem')->forgetDisk('uploads');
    foreach (json_decode(file_get_contents(database_path('seed-data/categories.json')), true) as $c) {
        TourCategory::create($c + ['is_active' => true]);
    }
});

it('imports only the requested packages, with departures, deposit, terms, cover and pdf', function () {
    $made = (new ContentSeeder)->importTours(['6d4n-year-end-korea-winter-experience', '9d7n-umrah-reguler-18-desember-2026']);
    expect($made)->toBe(2)->and(Tour::count())->toBe(2);

    $korea = Tour::where('slug', '6d4n-year-end-korea-winter-experience')->with('category', 'destinations')->first();
    expect($korea->category->kind)->toBe('group')->and($korea->dp_min)->toBe(6000000)->and(count($korea->departures))->toBe(8)
        ->and($korea->fromPrice())->toBe(19200000)->and($korea->cover_id)->not->toBeNull()->and($korea->pdf_id)->not->toBeNull()
        ->and($korea->terms)->toContain('25 peserta')->not->toContain('<script')
        ->and($korea->destinations->pluck('slug')->all())->toBe(['south-korea']);

    $umrah = Tour::where('slug', '9d7n-umrah-reguler-18-desember-2026')->with('destinations')->first();
    expect($umrah->destinations->pluck('slug')->all())->toBe(['saudi-arabia'])->and(Destination::where('slug', 'saudi-arabia')->exists())->toBeTrue()
        ->and(count($umrah->itinerary))->toBe(9);

    $this->get('/packages/'.$korea->slug.'?lang=id')->assertOk()->assertSee('20 Des 2026')->assertSee('Rp6.000.000');
});

it('never brings back a package an admin deleted', function () {
    (new ContentSeeder)->importTours(['6d5n-west-coast-usa-tour']);
    Tour::where('slug', '6d5n-west-coast-usa-tour')->delete();
    expect((new ContentSeeder)->importTours(['6d4n-year-end-korea-winter-experience']))->toBe(1)
        ->and(Tour::where('slug', '6d5n-west-coast-usa-tour')->exists())->toBeFalse();
    // running the same import twice adds nothing
    expect((new ContentSeeder)->importTours(['6d4n-year-end-korea-winter-experience']))->toBe(0);
});

it('ships the legal section hidden by default', function () {
    $pages = json_decode(file_get_contents(database_path('seed-data/pages.json')), true);
    $legal = collect($pages['pages'] ?? $pages)->flatMap(fn ($p) => $p['sections'] ?? [])->firstWhere('type', 'legal');
    expect($legal['is_visible'])->toBeFalse();
});

it('imports the trip photo albums once, with alt text, and shows them on the Galeri page', function () {
    $seeder = new ContentSeeder;
    expect($seeder->importGalleries())->toBe(8)->and($seeder->importGalleries())->toBe(0);
    $g = Gallery::orderBy('sort')->first();
    expect($g->title['en'])->toBe('Tencent & Shenzhen Upperhills')->and(count($g->items))->toBe(8)->and($g->cover_id)->not->toBeNull()
        ->and(Media::where('folder', 'galeri')->whereNull('alt')->count())->toBe(0)
        ->and(Gallery::get()->sum(fn ($a) => count($a->items)))->toBe(50);

    $seeder->importPages(['gallery']);
    $seeder->importPages(['gallery']);
    expect(Page::where('key', 'gallery')->count())->toBe(1)->and(Page::where('key', 'gallery')->first()->is_system)->toBeFalse();
    $html = $this->get('/galeri?lang=id')->assertOk()->getContent();
    expect($html)->toContain('Tencent &amp; Shenzhen Upperhills')->toContain('Immersion Program Tiongkok 2026 · Batch 2')->toContain('data-full=');
});

it('imports the starter blog articles once, published, SEO-scored and with working internal links', function () {
    Page::create(['key' => 'blog', 'slug' => 'blog', 'title' => ['en' => 'Blog', 'id' => 'Blog'], 'is_active' => true, 'is_system' => true]);
    $seeder = new ContentSeeder;
    $seeder->importTours(['9d7n-umrah-reguler-18-desember-2026', '6d4n-year-end-korea-winter-experience', '6d5n-west-coast-usa-tour']);
    expect($seeder->importPosts())->toBe(4)->and($seeder->importPosts())->toBe(0);

    foreach (Post::with('tags', 'category')->get() as $p) {
        expect($p->status)->toBe('published')->and($p->seo_score)->toBeGreaterThanOrEqual(80, $p->slug.' scored '.$p->seo_score)
            ->and($p->cover_id)->not->toBeNull()->and($p->tags)->not->toBeEmpty()->and($p->category)->not->toBeNull()
            ->and(ContentTools::wordCount($p->content))->toBeGreaterThanOrEqual(600);
        $html = $this->get('/blog/'.$p->slug)->assertOk()->getContent();
        expect($html)->toContain('"@type":"Article"')->toContain('"@type":"FAQPage"');
    }
    $this->get('/blog')->assertOk()->assertSee('Persiapan Umrah');
    expect($this->get('/sitemap.xml')->getContent())->toContain('/blog/persiapan-umrah-dokumen-vaksin-perlengkapan');
    expect($this->get('/feed.xml')->getContent())->toContain('Immersion Program Tiongkok 2026');
});
