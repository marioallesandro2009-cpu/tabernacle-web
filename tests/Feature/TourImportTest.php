<?php

use App\Models\Destination;
use App\Models\Tour;
use App\Models\TourCategory;
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
