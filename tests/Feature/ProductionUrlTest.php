<?php

use App\Models\Page;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;

it('builds links from APP_URL in production, whatever Host header arrives', function () {
    config(['app.url' => 'https://tabernaclewaystour.com']);
    app()->detectEnvironment(fn () => 'production');
    (new AppServiceProvider(app()))->boot();

    Page::create(['key' => 'about', 'slug' => 'about', 'title' => ['en' => 'About', 'id' => 'Tentang'], 'is_active' => true, 'is_system' => true]);
    $xml = $this->withServerVariables(['HTTP_HOST' => 'tabernaclewaystour.com.exodineweb.com', 'HTTPS' => 'on'])->get('/sitemap.xml')->getContent();

    expect($xml)->toContain('https://tabernaclewaystour.com/about')->not->toContain('exodineweb');

    URL::forceRootUrl(null);
    URL::forceScheme(null);
    app()->detectEnvironment(fn () => 'testing');
});
