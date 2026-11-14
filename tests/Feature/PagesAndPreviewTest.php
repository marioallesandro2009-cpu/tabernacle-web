<?php

use App\Models\Page;
use App\Models\PageView;
use App\Models\Post;
use App\Models\Section;

function systemPage(string $key): Page
{
    return Page::create(['key' => $key, 'slug' => $key === 'home' ? '/' : $key, 'title' => ['en' => ucfirst($key), 'id' => ucfirst($key)], 'is_active' => true, 'is_system' => true]);
}

it('lets an admin create a custom page that starts inactive with a text section', function () {
    $this->actingAs(adminUser('admin'));
    $this->post('/admin/pages', ['title' => ['en' => 'Terms & Conditions', 'id' => 'Syarat & Ketentuan'], 'slug' => ''])->assertRedirect();
    $page = Page::where('is_system', false)->latest('id')->first(); // the newest, after the empty trust-page templates
    expect($page->slug)->toBe('terms-conditions')->and($page->is_active)->toBeFalse()->and($page->sections()->count())->toBe(1)
        ->and($page->sections()->first()->type)->toBe('text_block');

    // not public until activated
    $this->get('/terms-conditions')->assertNotFound();
    $page->update(['is_active' => true]);
    $this->get('/en/terms-conditions')->assertOk()->assertSee('Terms &amp; Conditions', false);
    expect(PageView::where('path', '/terms-conditions')->exists())->toBeFalse(); // admin visits are not counted
});

it('serves an active custom page to visitors and counts the view', function () {
    systemPage('home');
    Page::where('slug', 'kebijakan-privasi')->delete(); // the empty template from the install
    $page = Page::create(['key' => 'c-privasi', 'slug' => 'kebijakan-privasi', 'title' => ['en' => 'Privacy', 'id' => 'Privasi'], 'is_active' => true, 'is_system' => false]);
    $page->sections()->create(['type' => 'text_block', 'sort' => 1, 'is_visible' => true, 'data' => ['title_a' => ['en' => 'Privacy policy', 'id' => 'Kebijakan privasi'], 'body' => ['en' => '<p>We keep your data safe.</p>', 'id' => '<p>Data Anda aman.</p>']]]);
    $this->get('/en/kebijakan-privasi')->assertOk()->assertSee('We keep your data safe.');
    expect(PageView::where('path', '/en/kebijakan-privasi')->count())->toBe(1);
    expect($this->get('/sitemap.xml')->getContent())->toContain('/kebijakan-privasi');
});

it('refuses addresses the site already uses and duplicates', function () {
    $this->actingAs(adminUser('admin'));
    foreach (['about', 'admin', 'blog', 'packages'] as $slug) {
        $this->post('/admin/pages', ['title' => ['en' => 'X'], 'slug' => $slug])->assertSessionHasErrors('slug');
    }
    $this->post('/admin/pages', ['title' => ['en' => 'Faq Visa'], 'slug' => 'faq-visa'])->assertSessionHasNoErrors();
    $this->post('/admin/pages', ['title' => ['en' => 'Faq Visa'], 'slug' => 'faq-visa'])->assertSessionHasErrors('slug');
    $this->post('/admin/pages', ['title' => ['en' => ''], 'slug' => 'kosong'])->assertSessionHasErrors('title.en');
    expect(Page::where('is_system', false)->where('key', 'not like', 'trust-%')->count())->toBe(1);
});

it('keeps the built-in pages and the admin area safe from the custom page route', function () {
    systemPage('about');
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->get('/about')->assertOk();
    $this->get('/does-not-exist')->assertNotFound();
});

it('can rename a custom page address but never delete a built-in page', function () {
    $this->actingAs(adminUser('admin'));
    $about = systemPage('about');
    $this->delete("/admin/pages/{$about->id}")->assertSessionHas('error');
    expect(Page::find($about->id))->not->toBeNull();

    $this->post('/admin/pages', ['title' => ['en' => 'Old name'], 'slug' => 'lama']);
    $custom = Page::where('slug', 'lama')->first();
    $this->put("/admin/pages/{$custom->id}", ['title' => ['en' => 'New', 'id' => 'Baru'], 'slug' => 'baru', 'is_active' => true])->assertSessionHasNoErrors();
    expect($custom->fresh()->slug)->toBe('baru');
    $this->put("/admin/pages/{$custom->id}", ['title' => ['en' => 'New', 'id' => 'Baru'], 'slug' => 'contact', 'is_active' => true])->assertSessionHasErrors('slug');
    // the built-in page's address cannot be changed from the form
    $this->put("/admin/pages/{$about->id}", ['title' => ['en' => 'About', 'id' => 'Tentang'], 'slug' => 'hacked', 'is_active' => true])->assertSessionHasNoErrors();
    expect($about->fresh()->slug)->toBe('about');

    $this->delete("/admin/pages/{$custom->id}")->assertRedirect('/admin/pages');
    expect(Page::find($custom->id))->toBeNull()->and(Section::where('page_id', $custom->id)->count())->toBe(0);
});

it('previews an inactive custom page for admins only, marked noindex', function () {
    $page = Page::create(['key' => 'c-draft', 'slug' => 'draft-page', 'title' => ['en' => 'Draft page', 'id' => 'Draf'], 'is_active' => false, 'is_system' => false]);
    $this->get("/admin/pages/{$page->id}/preview")->assertRedirect('/admin/login');
    $this->actingAs(adminUser('editor'))->get("/admin/pages/{$page->id}/preview")->assertForbidden();
    $html = $this->actingAs(adminUser('admin'))->get("/admin/pages/{$page->id}/preview")->assertOk()->getContent();
    expect($html)->toContain('noindex')->toContain('Pratinjau admin');
    $this->get('/draft-page')->assertNotFound();
});

it('previews draft and scheduled articles for blog editors only', function () {
    $draft = Post::create(['title' => 'Rahasia Draft', 'slug' => 'rahasia-draft', 'status' => 'draft', 'content' => '<h2>Isi</h2><p>Belum terbit.</p>']);
    $later = Post::create(['title' => 'Nanti Terbit', 'slug' => 'nanti', 'status' => 'scheduled', 'published_at' => now()->addWeek(), 'content' => '<p>x</p>']);
    $this->get('/blog/rahasia-draft')->assertNotFound();
    $this->get("/admin/posts/{$draft->id}/preview")->assertRedirect('/admin/login');

    $this->actingAs(adminUser('editor'));
    $html = $this->get("/admin/posts/{$draft->id}/preview")->assertOk()->getContent();
    expect($html)->toContain('Rahasia Draft')->toContain('Belum terbit.')->toContain('noindex')->toContain('Pratinjau admin');
    $this->get("/admin/posts/{$later->id}/preview")->assertOk()->assertSee('Nanti Terbit');
    expect(PageView::count())->toBe(0);
});
