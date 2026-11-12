<?php

use App\Http\Controllers\Site\BlogController;
use App\Http\Controllers\Site\LangController;
use App\Http\Controllers\Site\LeadController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\SeoController;
use Illuminate\Support\Facades\Route;

/*
| Public website. Page views are counted by the "track" middleware.
*/
Route::middleware('track')->group(function () {
    Route::get('/', [PageController::class, 'home'])->name('home');
    Route::get('/about', [PageController::class, 'about'])->name('about');
    Route::get('/contact', [PageController::class, 'contact'])->name('contact');
    Route::get('/packages', [PageController::class, 'packages'])->name('packages');
    Route::get('/packages/{slug}', [PageController::class, 'tour'])->where('slug', '[a-z0-9\-]+')->defaults('track_type', 'tour')->name('tour');
    Route::get('/destinations', [PageController::class, 'destinations'])->name('destinations');
    Route::get('/destinations/{slug}', [PageController::class, 'destination'])->where('slug', '[a-z0-9\-]+')->defaults('track_type', 'destination')->name('destination');
    Route::get('/blog', [BlogController::class, 'index'])->name('blog');
    Route::get('/blog/{slug}', [BlogController::class, 'show'])->where('slug', '[a-z0-9\-]+')->defaults('track_type', 'post')->name('post');
});

Route::get('/lang/{lang}', [LangController::class, 'switch'])->name('lang');
Route::post('/leads', [LeadController::class, 'store'])->middleware('throttle:lead')->name('leads.store');
Route::post('/track/wa', [LeadController::class, 'waClick'])->middleware('throttle:wa')->name('track.wa');

Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);
Route::get('/robots.txt', [SeoController::class, 'robots']);
Route::get('/feed.xml', [SeoController::class, 'feed']);

require __DIR__.'/admin.php';

// Pages created in the admin (e.g. /syarat-ketentuan). Registered last so it never shadows the routes above.
Route::get('/{slug}', [PageController::class, 'custom'])
    ->where('slug', '(?!admin$)[a-z0-9]+(?:-[a-z0-9]+)*')->middleware('track')->name('page.custom');

// Unknown URLs still pass through the web middleware, so the redirect manager can catch old links.
Route::fallback(fn () => abort(404));
