<?php

use App\Models\Media;
use App\Models\Post;
use App\Services\MediaService;
use App\Support\ContentTools;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    config(['filesystems.disks.uploads.root' => sys_get_temp_dir().'/tw-uploads-'.uniqid()]);
    app('filesystem')->forgetDisk('uploads');
});

it('keeps videos from the site media library in articles and drops anything else', function () {
    $own = rtrim(config('app.url'), '/');
    $html = clean_html('<p>Intro</p><video src="'.$own.'/uploads/media/2026/11/clip.mp4" onplay="x()"></video>'
        .'<video src="/uploads/media/b.webm" poster="https://evil.test/p.jpg"></video>'
        .'<video src="https://evil.test/x.mp4" controls></video><video src="javascript:alert(1)"></video>');
    expect($html)->toContain('/uploads/media/2026/11/clip.mp4')->toContain('/uploads/media/b.webm')
        ->toContain('controls')->toContain('preload="metadata"')
        ->not->toContain('evil.test')->not->toContain('javascript:')->not->toContain('onplay')
        ->and(substr_count($html, '<video'))->toBe(2)
        ->and(clean_html($html))->toBe($html); // saving again changes nothing
});

it('accepts videos up to 50 MB and refuses bigger ones with a clear message', function () {
    $this->actingAs(adminUser('editor'));
    $this->post('/admin/media', ['files' => [UploadedFile::fake()->create('short.mp4', 20 * 1024, 'video/mp4')]])->assertOk();
    expect(Media::where('kind', 'video')->count())->toBe(1);

    $res = $this->post('/admin/media', ['files' => [UploadedFile::fake()->create('long.mp4', 55 * 1024, 'video/mp4')]]);
    expect($res->status())->toBeIn([302, 422])->and(Media::where('kind', 'video')->count())->toBe(1);
    expect(MediaService::limitMb('mp4'))->toBe(50)->and(MediaService::limitMb('pdf'))->toBe(30)->and(MediaService::limitMb('jpg'))->toBe(12);
});

it('finds uploaded and YouTube videos in an article and describes them for Google (VideoObject)', function () {
    $content = '<p>Lihat video perjalanan kami.</p><video src="/uploads/media/2026/11/umrah.mp4" controls preload="metadata"></video>'
        .'<iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ" allowfullscreen></iframe>';
    $v = ContentTools::videos($content);
    expect($v)->toHaveCount(2)->and($v[0]['type'])->toBe('file')->and($v[0]['src'])->toEndWith('/uploads/media/2026/11/umrah.mp4')
        ->and($v[1]['type'])->toBe('youtube')->and($v[1]['poster'])->toContain('dQw4w9WgXcQ');

    Post::create(['title' => 'Video Umrah', 'slug' => 'video-umrah', 'status' => 'published', 'published_at' => now()->subHour(), 'content' => clean_html($content), 'excerpt' => 'Ringkas']);
    $html = $this->get('/blog/video-umrah')->assertOk()->getContent();
    expect($html)->toContain('<video')->toContain('"@type":"VideoObject"')->toContain('"contentUrl"')->toContain('"embedUrl"')->toContain('"uploadDate"');
});

it('saves an article with a video from the admin editor', function () {
    $this->actingAs(adminUser('editor'));
    $this->post('/admin/posts', ['title' => 'Video test', 'status' => 'draft',
        'content' => '<h2>Video</h2><video controls="controls" preload="metadata" playsinline="playsinline" src="/uploads/media/v.mp4"></video>'])->assertSessionHasNoErrors();
    expect(Post::first()->content)->toContain('<video src="/uploads/media/v.mp4"');
});

it('links the blog from the header and footer menus', function () {
    $menus = collect(json_decode(file_get_contents(database_path('seed-data/menus.json')), true));
    expect($menus->where('url', '/blog')->pluck('location')->sort()->values()->all())->toBe(['footer', 'header']);
});
