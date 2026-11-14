<?php

use App\Models\Page;

/** The admin panel at the address set in ADMIN_PATH; the old /admin answers 404. */
function bootWithAdminPath(?string $path): void
{
    if ($path === null) {
        putenv('ADMIN_PATH');
        unset($_ENV['ADMIN_PATH'], $_SERVER['ADMIN_PATH']);
    } else {
        putenv('ADMIN_PATH='.$path);
        $_ENV['ADMIN_PATH'] = $_SERVER['ADMIN_PATH'] = $path;
    }
    test()->refreshApplication();
    test()->artisan('migrate:fresh'); // the refreshed app has a new in-memory database
}

afterEach(fn () => bootWithAdminPath(null));

it('serves the panel at ADMIN_PATH and answers 404 at /admin', function () {
    bootWithAdminPath('ruang-kerja');
    expect(admin_path())->toBe('/ruang-kerja')->and(admin_path('/leads'))->toBe('/ruang-kerja/leads');

    $this->get('/admin')->assertNotFound();
    $this->get('/admin/login')->assertNotFound();
    $this->post('/admin/login', ['email' => 'a@b.test', 'password' => 'x'])->assertNotFound();
    $this->get('/ruang-kerja/login')->assertOk()->assertSee('<meta name="admin-base" content="/ruang-kerja">', false);
    $this->get('/ruang-kerja')->assertRedirect('/ruang-kerja/login');

    $this->actingAs(adminUser());
    $page = $this->get('/ruang-kerja')->assertOk();
    $urls = collect($page->viewData('page')['props']['nav'])->flatMap(fn ($g) => collect($g['items'])->pluck('url'));
    expect($urls->every(fn ($u) => str_starts_with($u, '/ruang-kerja')))->toBeTrue();
    $this->get('/ruang-kerja/leads')->assertOk();
    $this->get('/ruang-kerja/login')->assertRedirect('/ruang-kerja'); // already signed in
});

it('keeps /admin when ADMIN_PATH is not set or not a safe path', function () {
    bootWithAdminPath('');
    expect(admin_path())->toBe('/admin');
    $this->get('/admin/login')->assertOk();

    bootWithAdminPath('../../etc');
    expect(admin_path())->toBe('/admin');
});

it('never lets an admin-made page take the panel address', function () {
    bootWithAdminPath('ruang-kerja');
    Page::create(['key' => 'custom-1', 'slug' => 'ruang-kerja', 'title' => ['id' => 'X', 'en' => 'X'], 'is_active' => true]);
    $this->get('/ruang-kerja')->assertRedirect('/ruang-kerja/login'); // the panel, not the page
});
