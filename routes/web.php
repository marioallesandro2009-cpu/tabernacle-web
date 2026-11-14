<?php

use App\Http\Controllers\Site\BlogController;
use App\Http\Controllers\Site\LangController;
use App\Http\Controllers\Site\LeadController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\SeoController;
use App\Http\Controllers\Site\ServiceController;
use App\Http\Controllers\Site\UnsubscribeController;
use Illuminate\Support\Facades\Route;

/*
| Public website, twice: Indonesian at the plain address, English under /en (named "en.*").
| Page views are counted by the "track" middleware.
*/
$pages = function (string $prefix, string $name) {
    Route::middleware('track')->prefix($prefix)->name($name)->group(function () {
        Route::get('/', [PageController::class, 'home'])->name('home');
        Route::get('/about', [PageController::class, 'about'])->name('about');
        Route::get('/contact', [PageController::class, 'contact'])->name('contact');
        Route::get('/packages', [PageController::class, 'packages'])->name('packages');
        Route::get('/packages/{slug}', [PageController::class, 'tour'])->where('slug', '[a-z0-9\-]+')->defaults('track_type', 'tour')->name('tour');
        Route::get('/destinations', [PageController::class, 'destinations'])->name('destinations');
        Route::get('/destinations/{slug}', [PageController::class, 'destination'])->where('slug', '[a-z0-9\-]+')->defaults('track_type', 'destination')->name('destination');
        Route::get('/blog', [BlogController::class, 'index'])->name('blog');
        Route::get('/blog/{slug}', [BlogController::class, 'show'])->where('slug', '[a-z0-9\-]+')->defaults('track_type', 'post')->name('post');
        Route::get('/layanan', [ServiceController::class, 'pillars'])->name('pillars');
        Route::get('/layanan/{slug}', [ServiceController::class, 'pillar'])->where('slug', '[a-z0-9\-]+')->defaults('track_type', 'pillar')->name('pillar');
        Route::get('/studi-kasus', [ServiceController::class, 'cases'])->name('cases');
        Route::get('/studi-kasus/{slug}', [ServiceController::class, 'case'])->where('slug', '[a-z0-9\-]+')->defaults('track_type', 'case')->name('case');
    });
};
$pages('', '');
$pages('en', 'en.');

Route::get('/lang/{lang}', [LangController::class, 'switch'])->name('lang');
Route::post('/leads', [LeadController::class, 'store'])->middleware('throttle:lead')->name('leads.store');
Route::post('/track/wa', [LeadController::class, 'waClick'])->middleware('throttle:wa')->name('track.wa');

// unsubscribe link in outreach emails
Route::get('/u/{token}', [UnsubscribeController::class, 'show'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:30,1');
Route::post('/u/{token}', [UnsubscribeController::class, 'confirm'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:30,1');

Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);
Route::get('/robots.txt', [SeoController::class, 'robots']);
Route::get('/feed.xml', [SeoController::class, 'feed']);
Route::get('/en/feed.xml', [SeoController::class, 'feed']);

require __DIR__.'/admin.php';

// the panel moved (ADMIN_PATH): the old address must not even hint that something is there
if (config('admin.path') !== 'admin') {
    Route::any('/admin/{any?}', fn () => abort(404))->where('any', '.*');
}

// Pages created in the admin (e.g. /syarat-ketentuan). Registered last so it never shadows the routes above.
$customSlug = '(?!admin$)(?!en$)(?!'.preg_quote(explode('/', config('admin.path'))[0], '/').'$)[a-z0-9]+(?:-[a-z0-9]+)*';
Route::get('/en/{slug}', [PageController::class, 'custom'])->where('slug', $customSlug)->middleware('track')->name('en.page.custom');
Route::get('/{slug}', [PageController::class, 'custom'])->where('slug', $customSlug)->middleware('track')->name('page.custom');

// Unknown URLs still pass through the web middleware, so the redirect manager can catch old links.
Route::fallback(fn () => abort(404));
