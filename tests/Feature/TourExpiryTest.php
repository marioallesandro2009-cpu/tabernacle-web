<?php

use App\Models\Destination;
use App\Models\Media;
use App\Models\Page;
use App\Models\Testimonial;
use App\Models\Tour;
use App\Models\TourCategory;
use Illuminate\Support\Carbon;

function expTour(string $slug, ?string $until, array $o = []): Tour
{
    $cat = TourCategory::firstOrCreate(['slug' => 'group'], ['name' => ['en' => 'Group', 'id' => 'Grup'], 'kind' => 'group']);

    return Tour::create(array_merge(['category_id' => $cat->id, 'name' => ucfirst(str_replace('-', ' ', $slug)), 'slug' => $slug, 'duration' => '5D4N',
        'region' => 'asia', 'is_active' => true, 'price_from' => 10000000, 'available_until' => $until], $o));
}

afterEach(fn () => Carbon::setTestNow());

it('hides a package everywhere once its date has passed (WIB), and keeps it on its last day', function () {
    Page::create(['key' => 'packages', 'slug' => 'packages', 'title' => ['en' => 'Packages', 'id' => 'Paket'], 'is_active' => true, 'is_system' => true])
        ->sections()->create(['type' => 'tour_list', 'sort' => 1, 'is_visible' => true, 'data' => []]);
    $jp = Destination::create(['name' => ['en' => 'Japan', 'id' => 'Jepang'], 'slug' => 'japan', 'region' => 'asia', 'is_active' => true]);
    $t = expTour('sakura-trip', '2026-12-18');
    $t->destinations()->attach($jp);
    $keep = expTour('long-trip', '2027-03-31');

    // 18 Dec, 23:30 WIB = 16:30 UTC: still the last day in Indonesia
    Carbon::setTestNow(Carbon::parse('2026-12-18 16:30:00', 'UTC'));
    $this->get('/packages/sakura-trip')->assertOk();
    expect(Tour::active()->count())->toBe(2);

    // 19 Dec, 00:30 WIB = 18 Dec 17:30 UTC: gone, although it is still the 18th on the server clock
    Carbon::setTestNow(Carbon::parse('2026-12-18 17:30:00', 'UTC'));
    $this->get('/packages/sakura-trip')->assertNotFound();
    expect(Tour::active()->pluck('slug')->all())->toBe(['long-trip']);
    $this->get('/packages?lang=en')->assertOk()->assertDontSee('/packages/sakura-trip')->assertSee('/packages/long-trip');
    expect($this->get('/sitemap.xml')->getContent())->not->toContain('sakura-trip')->toContain('long-trip');
    $this->get('/destinations?lang=en')->assertDontSee('/destinations/japan');
    expect($keep->fresh()->isExpired())->toBeFalse()->and($t->fresh()->isExpired())->toBeTrue();
});

it('hides past departure dates on the package page and prices from the next one', function () {
    Carbon::setTestNow(Carbon::parse('2026-12-21 03:00:00', 'UTC'));
    $t = expTour('korea-winter', '2026-12-31', ['price_from' => null, 'departures' => [
        ['date' => '2026-12-20', 'price_adult' => 15000000, 'status' => 'available'],
        ['date' => '2026-12-22', 'price_adult' => 19500000, 'status' => 'available'],
        ['date' => '2026-12-29', 'price_adult' => 20200000, 'status' => 'available'],
    ]]);
    expect($t->fromPrice())->toBe(19500000)->and(count($t->upcomingDepartures()))->toBe(2);
    $html = $this->get('/packages/korea-winter?lang=id')->assertOk()->getContent();
    expect($html)->not->toContain('20 Des 2026')->toContain('22 Des 2026')->toContain('29 Des 2026');
});

it('requires an end date in the admin and pushes it forward to the last departure', function () {
    $this->actingAs(adminUser('admin'));
    $t = expTour('edit-me', '2026-12-31');
    $cover = Media::create(['folder' => 'paket', 'kind' => 'image', 'path' => 'x.webp', 'name' => 'x.webp', 'alt' => 'x']);
    $base = ['name' => 'Edit me', 'slug' => 'edit-me', 'category_id' => $t->category_id, 'region' => 'asia', 'cover_id' => $cover->id, 'is_active' => true];

    $this->put("/admin/tours/{$t->id}", $base + ['available_until' => ''])->assertSessionHasErrors('available_until');

    $this->put("/admin/tours/{$t->id}", $base + ['available_until' => '2026-12-31', 'departures' => [['date' => '2027-01-15', 'status' => 'available']]])->assertSessionHasNoErrors();
    expect($t->fresh()->available_until->toDateString())->toBe('2027-01-15');

    $this->get('/admin/tours?masa=kedaluwarsa')->assertOk();
    $this->get('/admin/tours?masa=tayang')->assertOk()->assertSee('Edit me');
    $this->get("/admin/tours/{$t->id}/edit")->assertOk()->assertSee('2027-01-15');
});

it('saves when optional number fields with a default are left empty', function () {
    $this->actingAs(adminUser('admin'));
    $this->post('/admin/testimonials', ['name' => 'Ani', 'content' => ['en' => 'Great', 'id' => 'Bagus'], 'rating' => '', 'is_active' => true])->assertSessionHasNoErrors();
    expect(Testimonial::first()->rating)->toBe(5);
});
