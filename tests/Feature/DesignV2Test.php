<?php

use App\Models\Destination;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Models\UiString;
use App\Services\MediaService;
use App\Support\Settings;
use Database\Seeders\DefaultsSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    config(['filesystems.disks.uploads.root' => sys_get_temp_dir().'/tw-uploads-'.uniqid()]);
    app('filesystem')->forgetDisk('uploads');
});

function v2Tour(array $o = []): Tour
{
    $cat = TourCategory::firstOrCreate(['slug' => 'group'], ['name' => ['en' => 'Group', 'id' => 'Grup'], 'kind' => 'group']);

    return Tour::create(array_merge(['category_id' => $cat->id, 'name' => 'Japan Sakura', 'slug' => 'japan-sakura', 'duration' => '7D6N', 'region' => 'asia', 'is_active' => true,
        'description' => ['en' => '<p>Spring in Japan.</p>', 'id' => '<p>Musim semi di Jepang.</p>'],
        'itinerary' => [['n' => 1, 'title' => 'Arrive Osaka', 'text' => 'Welcome.'], ['n' => 2, 'title' => 'Kyoto', 'text' => 'Temples.'], ['n' => 3, 'title' => 'Tokyo', 'text' => 'City.']],
        'includes' => ['Hotel'], 'excludes' => ['Visa'], 'dp_min' => 5000000, 'price_from' => 25000000], $o));
}

it('shows the home quick finder with destinations that have trips, and can be switched off', function () {
    $home = Page::create(['key' => 'home', 'slug' => '/', 'title' => ['en' => 'Home', 'id' => 'Beranda'], 'is_active' => true, 'is_system' => true]);
    $section = $home->sections()->create(['type' => 'hero', 'sort' => 1, 'is_visible' => true, 'data' => ['line1' => ['en' => 'Your dream', 'id' => 'Impian']]]);
    $jp = Destination::create(['name' => ['en' => 'Japan', 'id' => 'Jepang'], 'slug' => 'japan', 'region' => 'asia', 'is_active' => true]);
    Destination::create(['name' => ['en' => 'Peru', 'id' => 'Peru'], 'slug' => 'peru', 'region' => 'america', 'is_active' => true]);
    v2Tour()->destinations()->attach($jp);

    $html = $this->get('/en')->assertOk()->getContent();
    expect($html)->toContain('class="hero-find"')->toContain('value="japan"')->not->toContain('value="peru"')
        ->toContain('action="'.url('/en/packages').'#list"');

    $section->update(['data' => ['line1' => ['en' => 'Your dream', 'id' => 'Impian'], 'show_finder' => false]]);
    expect($this->get('/en')->getContent())->not->toContain('class="hero-find"');
});

it('adds section navigation, collapsible days and the mobile booking bar to a package page', function () {
    $t = v2Tour();
    $html = $this->get('/en/packages/'.$t->slug)->assertOk()->getContent();
    expect($html)->toContain('class="tr-nav"')->toContain('href="#itinerary"')->toContain('href="#included"')->toContain('href="#inquiry"')
        ->not->toContain('href="#schedule"')->not->toContain('href="#photos"')
        ->and(substr_count($html, '<details'))->toBe(3)
        ->and(preg_match_all('/<details\s+open/', $html))->toBe(2);
    expect($html)->toContain('class="tr-bar"')->toContain('tr-toggle-days')->toContain('id="price-card"');
});

it('renders the tour photo gallery that admins fill in, with a lightbox', function () {
    $svc = app(MediaService::class);
    $a = $svc->store(UploadedFile::fake()->image('a.jpg', 1200, 800), 'tours', 'Torii gate in Kyoto');
    $b = $svc->store(UploadedFile::fake()->image('b.jpg', 1200, 800), 'tours', 'Mount Fuji');
    $t = v2Tour(['gallery' => [$a->id, $b->id]]);
    $html = $this->get('/en/packages/'.$t->slug)->assertOk()->getContent();
    expect($html)->toContain('id="photos"')->toContain('href="#photos"')->toContain('data-full=')->toContain('Torii gate in Kyoto')->toContain('Mount Fuji');
});

it('lists deposit, pdf and editable trust points next to the price', function () {
    Settings::set('trust_points', [['text' => ['en' => 'Licensed travel agent', 'id' => 'Biro perjalanan berizin']], ['text' => ['en' => '', 'id' => '']]]);
    Settings::forget();
    $t = v2Tour();
    $html = $this->get('/packages/'.$t->slug)->assertOk()->getContent();
    expect($html)->toContain('class="tr-trust"')->toContain('Biro perjalanan berizin')->toContain('5.000.000');
    $this->get('/en/packages/'.$t->slug)->assertSee('Licensed travel agent');
});

it('lets the admin edit trust points (max 5)', function () {
    $this->actingAs(adminUser('super_admin'));
    $base = ['site_name' => 'X', 'wa_number' => '6281200001111', 'color_navy' => '#112233', 'color_navy_deep' => '#001122', 'color_amber' => '#ff9900',
        'color_amber_deep' => '#cc6600', 'color_cream' => '#fff8ee', 'color_teal' => '#009988', 'lead_notify_enabled' => true, 'analytics_enabled' => true];
    $this->put('/admin/settings', $base + ['trust_points' => [['text' => ['en' => 'Real office', 'id' => 'Kantor nyata']]]])->assertSessionHasNoErrors();
    expect(setting('trust_points')[0]['text']['id'])->toBe('Kantor nyata');
    $six = array_fill(0, 6, ['text' => ['en' => 'x', 'id' => 'x']]);
    $this->put('/admin/settings', $base + ['trust_points' => $six])->assertSessionHasErrors('trust_points');
});

it('adds only missing texts and settings on deploy, never overwriting admin edits', function () {
    UiString::create(['key' => 'hero.find_btn', 'group' => 'hero', 'text_en' => 'Search now', 'text_id' => 'Cari sekarang']);
    Setting::create(['key' => 'site_name', 'value' => json_encode('Nama Diubah Admin')]);
    $this->seed(DefaultsSeeder::class);
    expect(UiString::where('key', 'hero.find_btn')->first()->text_id)->toBe('Cari sekarang')
        ->and(UiString::where('key', 'tour.nav_overview')->exists())->toBeTrue()
        ->and(setting('site_name'))->toBe('Nama Diubah Admin')
        ->and(setting('trust_points'))->toBeArray()
        // media placeholders are first-install only
        ->and(Setting::where('key', 'favicon')->exists())->toBeFalse();
    $count = UiString::count();
    $this->seed(DefaultsSeeder::class);
    expect(UiString::count())->toBe($count);
});
