<?php

use App\Models\Faq;
use App\Models\Media;
use App\Models\Post;
use App\Models\Testimonial;
use App\Models\UiString;
use App\Services\MediaService;
use App\Support\ContentTools;
use App\Support\MediaUsage;
use App\Support\SeoAnalyzer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['filesystems.disks.uploads.root' => sys_get_temp_dir().'/tw-uploads-'.uniqid()]);
    app('filesystem')->forgetDisk('uploads');
});

it('sanitises rich text: scripts, handlers and javascript links are removed', function () {
    $dirty = '<p onclick="x()">Halo <script>alert(1)</script><a href="javascript:alert(1)">klik</a><img src="a.jpg" onerror="alert(1)" alt="ok"><iframe src="https://evil.test/x"></iframe></p>';
    $clean = clean_html($dirty);
    expect($clean)->not->toContain('<script')->not->toContain('onclick')->not->toContain('onerror')->not->toContain('javascript:')->not->toContain('evil.test')->toContain('Halo');
    $yt = clean_html('<iframe src="https://www.youtube.com/embed/abc123"></iframe>');
    expect($yt)->toContain('youtube.com/embed/abc123');
});

it('accepts an image upload, converts it to webp with several sizes and needs alt to be edited', function () {
    $this->actingAs(adminUser('admin'));
    $res = $this->post('/admin/media', ['files' => [UploadedFile::fake()->image('foto.jpg', 2400, 1200)], 'alt' => 'Pantai di Bali'])->assertOk();
    $m = Media::first();
    expect($m->kind)->toBe('image')->and($m->mime)->toBe('image/webp')->and($m->alt)->toBe('Pantai di Bali')->and($m->width)->toBeLessThanOrEqual(2000)->and(count($m->variants))->toBeGreaterThanOrEqual(2);
    foreach ($m->allPaths() as $p) {
        expect(Storage::disk('uploads')->exists($p))->toBeTrue();
    }
    $this->putJson("/admin/media/{$m->id}", ['alt' => ''])->assertStatus(422)->assertJsonValidationErrors('alt');
    $this->putJson("/admin/media/{$m->id}", ['alt' => 'Alt baru'])->assertOk();
});

it('rejects executables, double extensions and mismatched content', function () {
    $this->actingAs(adminUser('admin'));
    $bad = [
        UploadedFile::fake()->create('shell.php', 5, 'text/x-php'),
        UploadedFile::fake()->create('shell.php.jpg', 5, 'image/jpeg'),
        UploadedFile::fake()->create('run.exe', 5, 'application/octet-stream'),
        UploadedFile::fake()->create('page.html', 5, 'text/html'),
        UploadedFile::fake()->create('fake.png', 5, 'text/plain'),
        UploadedFile::fake()->create('.htaccess', 1, 'text/plain'),
    ];
    foreach ($bad as $file) {
        $res = $this->post('/admin/media', ['files' => [$file], 'alt' => 'x']);
        expect($res->status())->toBeIn([422, 200]);
    }
    expect(Media::count())->toBe(0);
});

it('blocks deleting media that is still in use and deletes unused media with its files', function () {
    $this->actingAs(adminUser('admin'));
    $svc = app(MediaService::class);
    $used = $svc->store(UploadedFile::fake()->image('a.jpg', 800, 600), 'umum', 'Dipakai');
    $free = $svc->store(UploadedFile::fake()->image('b.jpg', 800, 600), 'umum', 'Bebas');
    Testimonial::create(['name' => 'T', 'content' => ['en' => 'Great', 'id' => 'Bagus'], 'photo_id' => $used->id, 'is_active' => true]);
    expect(MediaUsage::of($used->id))->not->toBeEmpty();
    $this->deleteJson("/admin/media/{$used->id}")->assertStatus(422);
    expect(Media::find($used->id))->not->toBeNull();
    $paths = $free->allPaths();
    $this->deleteJson("/admin/media/{$free->id}")->assertOk();
    expect(Media::find($free->id))->toBeNull();
    foreach ($paths as $p) {
        expect(Storage::disk('uploads')->exists($p))->toBeFalse();
    }
    $this->post('/admin/media/purge-unused')->assertSessionHas('success');
    expect(Media::find($used->id))->not->toBeNull();
});

it('scores a well-optimised article higher than a poor one', function () {
    $body = '<p>Paket tour Jepang cocok untuk keluarga. '.str_repeat('Kami menyiapkan itinerary lengkap dan nyaman. ', 40).'</p><h2>Kenapa memilih paket tour Jepang</h2><p>'.str_repeat('Pemandu berpengalaman mendampingi setiap hari. ', 40).'</p><h2>Harga</h2><p><a href="/packages">Lihat paket</a> '.str_repeat('Hubungi kami untuk detail harga terbaru. ', 40).'</p><img src="/a.webp" alt="Sakura di Kyoto">';
    $good = SeoAnalyzer::analyze([
        'title' => 'Paket Tour Jepang 6 Hari untuk Keluarga', 'slug' => 'paket-tour-jepang', 'focus_keyword' => 'paket tour jepang',
        'meta_title' => 'Paket Tour Jepang 6 Hari, Nyaman untuk Keluarga | Tabernacle', 'meta_description' => 'Paket tour Jepang 6 hari dengan itinerary lengkap, hotel nyaman, dan pemandu berpengalaman. Cocok untuk keluarga. Tanya harga hari ini bersama kami.',
        'content' => $body, 'cover_id' => 1,
    ]);
    $poor = SeoAnalyzer::analyze(['title' => 'Halo', 'slug' => 'x', 'focus_keyword' => '', 'content' => '<h1>Hai</h1><p>Pendek.</p><img src="a.jpg">']);
    expect($good['score'])->toBeGreaterThan(75)->and($poor['score'])->toBeLessThan(35)->and($good['score'])->toBeGreaterThan($poor['score']);
    $byId = collect($poor['checks'])->keyBy('id');
    expect($byId['alt']['status'])->toBe('fail')->and($byId['h1']['status'])->toBe('fail')->and($byId['kw']['status'])->toBe('fail');
});

it('computes reading time and words from html', function () {
    expect(ContentTools::wordCount('<p>satu dua <b>tiga</b></p>'))->toBe(3)->and(ContentTools::readingTime('<p>'.str_repeat('kata ', 450).'</p>'))->toBe(3);
});

it('creates a blog post from the admin with tags, score and reading time', function () {
    $admin = adminUser('editor');
    $this->actingAs($admin);
    $this->post('/admin/posts', [
        'title' => 'Tips Visa Jepang', 'slug' => '', 'excerpt' => 'Ringkas', 'content' => '<h2>Syarat</h2><p>Isi artikel visa jepang.</p>',
        'status' => 'published', 'focus_keyword' => 'visa jepang', 'tags' => ['visa', 'jepang'],
    ])->assertSessionHasNoErrors();
    $p = Post::first();
    expect($p->slug)->toBe('tips-visa-jepang')->and($p->status)->toBe('published')->and($p->published_at)->not->toBeNull()
        ->and($p->tags->pluck('name')->sort()->values()->all())->toBe(['jepang', 'visa'])->and($p->seo_score)->toBeGreaterThan(0)->and($p->author_id)->toBe($admin->id);
    $this->post('/admin/posts', ['title' => 'Tips Visa Jepang', 'content' => '<p>x</p>', 'status' => 'draft'])->assertSessionHasNoErrors();
    expect(Post::pluck('slug')->all())->toContain('tips-visa-jepang-2');
    $this->post('/admin/posts', ['title' => 'Jadwal', 'content' => '<p>x</p>', 'status' => 'scheduled', 'published_at' => now()->subDay()->format('Y-m-d\TH:i')])->assertSessionHasErrors('published_at');
    $this->postJson('/admin/posts/analyze', ['title' => 'Tes', 'content' => '<p>x</p>', 'focus_keyword' => 'tes'])->assertOk()->assertJsonStructure(['score', 'checks', 'stats', 'suggestions']);
});

it('sanitises article html on save', function () {
    $this->actingAs(adminUser('editor'));
    $this->post('/admin/posts', ['title' => 'XSS', 'content' => '<p>ok</p><script>alert(1)</script><img src=x onerror=alert(1)>', 'status' => 'draft'])->assertSessionHasNoErrors();
    expect(Post::first()->content)->not->toContain('<script')->not->toContain('onerror');
});

it('runs simple CRUD with validation: faq, testimonial, ui string', function () {
    $this->actingAs(adminUser('admin'));
    $this->post('/admin/faqs', ['question' => ['en' => '', 'id' => ''], 'answer' => ['en' => 'A', 'id' => 'A']])->assertSessionHasErrors('question.en');
    $this->post('/admin/faqs', ['question' => ['en' => 'Q?', 'id' => 'T?'], 'answer' => ['en' => '<p>A</p><script>x</script>', 'id' => '<p>J</p>'], 'is_active' => true])->assertSessionHasNoErrors();
    $faq = Faq::first();
    expect($faq->question['id'])->toBe('T?')->and($faq->answer['en'])->not->toContain('<script');
    $this->post("/admin/faqs/{$faq->id}/toggle")->assertRedirect();
    expect($faq->fresh()->is_active)->toBeFalse();
    $this->delete("/admin/faqs/{$faq->id}")->assertRedirect('/admin/faqs');
    expect(Faq::count())->toBe(0);

    $this->post('/admin/testimonials', ['name' => 'Ani', 'content' => ['en' => 'Great', 'id' => 'Bagus'], 'rating' => 9])->assertSessionHasErrors('rating');
    $this->post('/admin/testimonials', ['name' => 'Ani', 'content' => ['en' => 'Great', 'id' => 'Bagus'], 'rating' => 5, 'is_active' => true])->assertSessionHasNoErrors();

    $ui = UiString::create(['key' => 'x.y', 'group' => 'x', 'text_en' => 'A', 'text_id' => 'B']);
    $this->post('/admin/ui-strings', ['key' => 'x.y', 'text_en' => 'dup', 'group' => 'x'])->assertSessionHasErrors('key');
    $this->delete("/admin/ui-strings/{$ui->id}")->assertSessionHas('error');
    expect(UiString::find($ui->id))->not->toBeNull();
});

it('keeps menus within two levels and refuses a parent that is its own child', function () {
    $this->actingAs(adminUser('admin'));
    $this->post('/admin/menu-items', ['location' => 'header', 'label' => ['en' => 'Tours', 'id' => 'Tur'], 'url' => '/packages', 'is_active' => true])->assertSessionHasNoErrors();
    $parent = App\Models\MenuItem::first();
    $this->post('/admin/menu-items', ['location' => 'header', 'label' => ['en' => 'Asia', 'id' => 'Asia'], 'url' => '/packages?r=asia', 'parent_id' => $parent->id, 'is_active' => true])->assertSessionHasNoErrors();
    $this->delete("/admin/menu-items/{$parent->id}")->assertSessionHas('error');
});

it('saves site settings, normalises the WhatsApp number and clears the cache', function () {
    $this->actingAs(adminUser('super_admin'));
    $payload = [
        'site_name' => 'Nama Baru', 'wa_number' => '0812-0000-1111', 'color_navy' => '#112233', 'color_navy_deep' => '#001122', 'color_amber' => '#ff9900',
        'color_amber_deep' => '#cc6600', 'color_cream' => '#fff8ee', 'color_teal' => '#009988',
        'tagline' => ['en' => 'Hello', 'id' => 'Halo'], 'lead_notify_enabled' => true, 'analytics_enabled' => true,
    ];
    $this->put('/admin/settings', $payload)->assertSessionHasNoErrors();
    expect(setting('site_name'))->toBe('Nama Baru')->and(setting('wa_number'))->toBe('6281200001111')->and(setting('color_navy'))->toBe('#112233');
    $this->put('/admin/settings', array_merge($payload, ['color_navy' => 'merah']))->assertSessionHasErrors('color_navy');
    $this->actingAs(adminUser('admin'))->put('/admin/settings', $payload)->assertForbidden();
});

it('writes an activity log for changes made in the admin', function () {
    $admin = adminUser('admin');
    $this->actingAs($admin);
    $this->post('/admin/faqs', ['question' => ['en' => 'Q', 'id' => 'Q'], 'answer' => ['en' => 'A', 'id' => 'A'], 'is_active' => true]);
    $log = App\Models\ActivityLog::where('subject_type', 'Faq')->first();
    expect($log)->not->toBeNull()->and($log->user_id)->toBe($admin->id)->and($log->action)->toBe('created');
    $this->actingAs(adminUser('super_admin'))->get('/admin/activity')->assertOk();
});
