<?php

use App\Mail\BackupMail;
use App\Models\Destination;
use App\Models\Gallery;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Services\DatabaseBackup;
use App\Support\Content;
use App\Support\ContentTools;
use Database\Seeders\ContentSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;

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

it('has an English version for every seeded package, with the same number of days', function () {
    $en = json_decode(file_get_contents(database_path('seed-data/tours_en.json')), true);
    foreach (json_decode(file_get_contents(database_path('seed-data/tours.json')), true) as $t) {
        expect($en)->toHaveKey($t['slug']);
        expect(count($en[$t['slug']]['itinerary']))->toBe(count($t['itinerary'] ?? []), $t['slug']);
        foreach ($en[$t['slug']]['itinerary'] as $d) {
            expect($d['title'])->not->toBeEmpty()->and($d['text'])->not->toBeEmpty();
        }
    }
});

it('shows the English package texts on the English site and Indonesian on the Indonesian site', function () {
    $seeder = new ContentSeeder;
    $seeder->importTours(['9d7n-umrah-reguler-18-desember-2026']);
    expect($seeder->importEnglish())->toBe(1)->and($seeder->importEnglish())->toBe(0);
    $slug = '/packages/9d7n-umrah-reguler-18-desember-2026';

    $en = $this->get($slug.'?lang=en')->assertOk()->getContent();
    expect($en)->toContain('After the dawn (Subuh) prayer')->toContain('Umrah visa')->toContain('Cancellation policy')
        ->toContain('18 December 2026')->not->toContain('Setelah sholat subuh')->not->toContain('Itinerary details are written in Bahasa Indonesia');

    $id = $this->get($slug.'?lang=id')->assertOk()->getContent();
    expect($id)->toContain('Setelah sholat subuh')->toContain('Kebijakan pembatalan')->not->toContain('After the dawn (Subuh) prayer');

    // an admin's own English text is never overwritten
    Tour::where('slug', '9d7n-umrah-reguler-18-desember-2026')->first()->update(['dates_label_en' => 'Custom date']);
    $seeder->importEnglish();
    expect(Tour::where('slug', '9d7n-umrah-reguler-18-desember-2026')->value('dates_label_en'))->toBe('Custom date');
});

it('falls back to Indonesian with a note when a package has no English itinerary', function () {
    (new ContentSeeder)->importTours(['6d5n-west-coast-usa-tour']);
    $html = $this->get('/packages/6d5n-west-coast-usa-tour?lang=en')->assertOk()->getContent();
    expect($html)->toContain('Waktu bebas menikmati Los Angeles');
});

it('shows English articles and blog categories on the English site', function () {
    Page::create(['key' => 'blog', 'slug' => 'blog', 'title' => ['en' => 'Blog', 'id' => 'Blog'], 'is_active' => true, 'is_system' => true]);
    $seeder = new ContentSeeder;
    $seeder->importPosts();
    $seeder->importEnglish();

    $this->get('/blog?lang=en')->assertOk()->assertSee('Umrah Preparation: Documents, Vaccines and What to Pack')->assertSee('Travel Tips')->assertDontSee('Tips Perjalanan');
    $html = $this->get('/blog/persiapan-umrah-dokumen-vaksin-perlengkapan?lang=en')->assertOk()->getContent();
    expect($html)->toContain('Good umrah preparation makes the pilgrimage feel much calmer')->toContain('How long must my passport be valid for umrah?')
        ->not->toContain('Persiapan umrah yang matang');
    $this->get('/blog/persiapan-umrah-dokumen-vaksin-perlengkapan?lang=id')->assertOk()->assertSee('Persiapan umrah yang matang')->assertSee('Ziarah &amp; Umrah', false);
    expect($this->get('/feed.xml?lang=en')->getContent())->toContain('China Immersion Program 2026');
    // the database still holds the Indonesian texts
    expect(Post::where('slug', 'persiapan-umrah-dokumen-vaksin-perlengkapan')->value('title'))->toStartWith('Persiapan Umrah');
});

it('imports the starter FAQ once and shows it on the home and contact pages', function () {
    $seeder = new ContentSeeder;
    foreach (json_decode(file_get_contents(database_path('seed-data/pages.json')), true) as $p) {
        if (in_array($p['key'], ['home', 'contact'], true)) {
            $page = Page::create(['key' => $p['key'], 'slug' => $p['slug'], 'title' => $p['title'], 'is_active' => true, 'is_system' => true]);
            foreach ($p['sections'] as $i => $s) {
                if ($s['type'] !== 'faq') {
                    $page->sections()->create(['type' => $s['type'], 'name' => $s['name'], 'data' => [], 'sort' => $i + 1, 'is_visible' => false]);
                }
            }
        }
    }
    expect($seeder->importFaqs())->toBe(12)->and($seeder->importFaqs())->toBe(0);
    $seeder->addFaqSection(['home', 'contact']);
    $seeder->addFaqSection(['home', 'contact']);
    $home = Page::where('key', 'home')->first();
    expect($home->sections()->where('type', 'faq')->count())->toBe(1)
        ->and($home->sections()->orderBy('sort')->pluck('type')->slice(-2)->values()->all())->toBe(['faq', 'contact']);

    $html = $this->get('/?lang=id')->assertOk()->getContent();
    expect($html)->toContain('Siapa Tabernacle Ways Tour &amp; Travel?')->toContain('"@type":"FAQPage"');
    $this->get('/?lang=en')->assertOk()->assertSee('What is the difference between a group tour and FIT?');
    $this->get('/contact?lang=id')->assertOk()->assertSee('Di mana kantor Tabernacle Ways');
});

it('loads the calm corporate stylesheet and no motion libraries', function () {
    Page::create(['key' => 'home', 'slug' => 'home', 'title' => ['en' => 'Home', 'id' => 'Beranda'], 'is_active' => true, 'is_system' => true]);
    $html = $this->get('/?lang=id')->assertOk()->getContent();
    expect($html)->toContain('assets/css/corporate.css')->not->toContain('gsap.min.js')->not->toContain('lenis.min.js');
});

it('shows the English package name on the English site only', function () {
    $seeder = new ContentSeeder;
    $seeder->importTours(['9d7n-umrah-reguler-18-desember-2026']);
    $seeder->importEnglish();
    expect(Tour::where('slug', '9d7n-umrah-reguler-18-desember-2026')->value('name_en'))->toBe('Regular Umrah 18 December 2026');
    $this->get('/packages/9d7n-umrah-reguler-18-desember-2026?lang=en')->assertOk()->assertSee('Regular Umrah 18 December 2026');
    $this->get('/packages/9d7n-umrah-reguler-18-desember-2026?lang=id')->assertOk()->assertSee('Umrah Reguler 18 Desember 2026')->assertDontSee('Regular Umrah 18 December 2026');
});

it('backs up the database every night and emails a copy when an address is set', function () {
    Mail::fake();
    // the real dump needs MySQL; tests run on sqlite, so a stand-in writes a small file
    app()->instance(DatabaseBackup::class, new class extends DatabaseBackup
    {
        public function create(): string
        {
            $p = sys_get_temp_dir().'/tw-backup-test.sql.gz';
            file_put_contents($p, gzencode('-- test'));

            return $p;
        }

        public function prune(int $keep = 8): int
        {
            return 0;
        }
    });
    Setting::updateOrCreate(['key' => 'backup_email'], ['value' => json_encode('backup@example.com')]);
    Content::bump();
    $this->artisan('db:backup', ['--keep' => 2])->assertSuccessful();
    Mail::assertSent(BackupMail::class, fn ($m) => $m->hasTo('backup@example.com'));
    $events = collect(app(Schedule::class)->events())->filter(fn ($e) => str_contains((string) $e->description, 'db:backup'));
    expect($events->first()->expression)->toBe('0 2 * * *')->and($events->first()->timezone)->toBe('Asia/Jakarta');
});

it('adds the Türkiye chapter to the Journeys section once, with its photos', function () {
    $seeder = new ContentSeeder;
    $seed = collect(json_decode(file_get_contents(database_path('seed-data/pages.json')), true))->firstWhere('key', 'home');
    $journeys = collect($seed['sections'])->firstWhere('type', 'journeys');
    $old = $journeys['data'];
    $old['chapters'] = array_slice($old['chapters'], 0, 2); // the site as it was before the new chapter
    $page = Page::create(['key' => 'home', 'slug' => 'home', 'title' => ['en' => 'Home', 'id' => 'Beranda'], 'is_active' => true, 'is_system' => true]);
    $page->sections()->create(['type' => 'journeys', 'name' => 'Perjalanan', 'data' => $old, 'sort' => 1, 'is_visible' => true]);

    expect($seeder->addJourneyChapters())->toBe(1)->and($seeder->addJourneyChapters())->toBe(0);
    $chapters = $page->sections()->first()->data['chapters'];
    $last = end($chapters);
    expect(count($chapters))->toBe(3)->and($last['title']['en'])->toBe('Seven Churches, Türkiye')->and(count($last['tiles']))->toBe(9)
        ->and(collect($last['tiles'])->every(fn ($t) => is_int($t['image']) && Media::whereKey($t['image'])->exists()))->toBeTrue();

    $html = $this->get('/?lang=id')->assertOk()->getContent();
    expect($html)->toContain('7 Churches, Turki')->toContain('Perpustakaan Celsus, Efesus');
    expect($this->get('/?lang=en')->getContent())->toContain('Seven Churches, Türkiye')->toContain('Library of Celsus, Ephesus');
});
