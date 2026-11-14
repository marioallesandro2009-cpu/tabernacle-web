<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\LoginHistoryController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\Outreach;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\QuotationController;
use App\Http\Controllers\Admin\RecipientController;
use App\Http\Controllers\Admin\Resources;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TranslateController;
use Illuminate\Support\Facades\Route;

/*
| Admin panel (Inertia + Vue). No public registration: the first super admin is made
| with "php artisan app:create-super-admin", the rest by a super admin inside the panel.
*/
Route::prefix('admin')->middleware('inertia.admin')->group(function () {
    // --- guests ---
    Route::middleware('guest')->group(function () {
        Route::get('login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AuthController::class, 'login']);
        Route::get('two-factor', [AuthController::class, 'showTwoFactor']);
        Route::post('two-factor', [AuthController::class, 'twoFactor']);
        Route::get('forgot-password', [AuthController::class, 'showForgot']);
        Route::post('forgot-password', [AuthController::class, 'forgot']);
        Route::get('reset-password/{token}', [AuthController::class, 'showReset']);
        Route::post('reset-password', [AuthController::class, 'reset']);
    });

    // --- signed in ---
    Route::middleware(['auth', 'active.admin'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('/', [DashboardController::class, 'index']);

        Route::get('profile', [ProfileController::class, 'edit']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::put('profile/password', [ProfileController::class, 'password']);
        Route::post('profile/2fa', [ProfileController::class, 'startTwoFactor']);
        Route::post('profile/2fa/confirm', [ProfileController::class, 'confirmTwoFactor']);
        Route::delete('profile/2fa', [ProfileController::class, 'disableTwoFactor']);

        // "Terjemahkan ke Inggris" buttons in the forms
        Route::post('translate', [TranslateController::class, 'store'])->middleware('throttle:20,1');

        // generic CRUD modules: slug => [controller, permission]
        $modules = [
            'tours' => [Resources\TourController::class, 'tours.manage'],
            'destinations' => [Resources\DestinationController::class, 'tours.manage'],
            'tour-categories' => [Resources\TourCategoryController::class, 'tours.manage'],
            'posts' => [Resources\PostController::class, 'blog.manage'],
            'post-categories' => [Resources\PostCategoryController::class, 'blog.manage'],
            'tags' => [Resources\TagController::class, 'blog.manage'],
            'testimonials' => [Resources\TestimonialController::class, 'content.manage'],
            'galleries' => [Resources\GalleryController::class, 'content.manage'],
            'faqs' => [Resources\FaqController::class, 'content.manage'],
            'team' => [Resources\TeamMemberController::class, 'content.manage'],
            'menu-items' => [Resources\MenuItemController::class, 'content.manage'],
            'ui-strings' => [Resources\UiStringController::class, 'content.manage'],
            'redirects' => [Resources\RedirectController::class, 'content.manage'],
            'users' => [Resources\UserController::class, 'users.manage'],
        ];
        foreach ($modules as $slug => [$controller, $permission]) {
            Route::middleware('can:'.$permission)->prefix($slug)->group(function () use ($controller) {
                if ($controller === Resources\PostController::class) {
                    Route::post('analyze', [$controller, 'analyze']);
                    Route::get('{id}/preview', [$controller, 'preview'])->whereNumber('id');
                }
                if ($controller === Resources\UserController::class) {
                    Route::post('{id}/reset-link', [$controller, 'resetLink'])->whereNumber('id');
                }
                Route::get('/', [$controller, 'index']);
                Route::get('create', [$controller, 'create']);
                Route::post('/', [$controller, 'store']);
                Route::post('reorder', [$controller, 'reorder']);
                Route::get('{id}/edit', [$controller, 'edit'])->whereNumber('id');
                Route::put('{id}', [$controller, 'update'])->whereNumber('id');
                Route::post('{id}/toggle', [$controller, 'toggle'])->whereNumber('id');
                Route::delete('{id}', [$controller, 'destroy'])->whereNumber('id');
            });
        }

        // pages and sections
        Route::middleware('can:content.manage')->group(function () {
            Route::get('pages', [PageController::class, 'index']);
            Route::post('pages', [PageController::class, 'store']);
            Route::delete('pages/{id}', [PageController::class, 'destroy'])->whereNumber('id');
            Route::get('pages/{id}/preview', [PageController::class, 'preview'])->whereNumber('id');
            Route::get('pages/{id}/edit', [PageController::class, 'edit'])->whereNumber('id');
            Route::put('pages/{id}', [PageController::class, 'update'])->whereNumber('id');
            Route::post('pages/{id}/sections', [PageController::class, 'sectionStore'])->whereNumber('id');
            Route::post('pages/{id}/sections/reorder', [PageController::class, 'sectionReorder'])->whereNumber('id');
            Route::get('sections/{id}/edit', [PageController::class, 'sectionEdit'])->whereNumber('id');
            Route::put('sections/{id}', [PageController::class, 'sectionUpdate'])->whereNumber('id');
            Route::post('sections/{id}/toggle', [PageController::class, 'sectionToggle'])->whereNumber('id');
            Route::delete('sections/{id}', [PageController::class, 'sectionDestroy'])->whereNumber('id');
        });

        // media library (editors need it too)
        Route::middleware('can:media.manage')->prefix('media')->group(function () {
            Route::get('/', [MediaController::class, 'index']);
            Route::get('list', [MediaController::class, 'list']);
            Route::post('/', [MediaController::class, 'store']);
            Route::post('purge-unused', [MediaController::class, 'purgeUnused']);
            Route::put('{id}', [MediaController::class, 'update'])->whereNumber('id');
            Route::get('{id}/usage', [MediaController::class, 'usage'])->whereNumber('id');
            Route::delete('{id}', [MediaController::class, 'destroy'])->whereNumber('id');
        });

        // leads
        Route::middleware('can:leads.manage')->group(function () {
            Route::get('leads', [LeadController::class, 'index']);
            Route::post('leads/bulk', [LeadController::class, 'bulk']);
            Route::get('leads/export/{format}', [LeadController::class, 'export'])->middleware('can:leads.export')->where('format', 'xlsx|csv|pdf');
            Route::get('leads/{id}', [LeadController::class, 'show'])->whereNumber('id');
            Route::put('leads/{id}', [LeadController::class, 'update'])->whereNumber('id');
            Route::post('leads/{id}/note', [LeadController::class, 'note'])->whereNumber('id');
            Route::delete('leads/{id}', [LeadController::class, 'destroy'])->whereNumber('id');
            // price quotations (penawaran harga)
            Route::get('quotations', [QuotationController::class, 'index']);
            Route::get('quotations/create', [QuotationController::class, 'create']);
            Route::post('quotations', [QuotationController::class, 'store']);
            Route::get('quotations/{id}/edit', [QuotationController::class, 'edit'])->whereNumber('id');
            Route::put('quotations/{id}', [QuotationController::class, 'update'])->whereNumber('id');
            Route::post('quotations/{id}/status', [QuotationController::class, 'status'])->whereNumber('id');
            Route::post('quotations/{id}/duplicate', [QuotationController::class, 'duplicate'])->whereNumber('id');
            Route::delete('quotations/{id}', [QuotationController::class, 'destroy'])->whereNumber('id');
            Route::get('quotations/{id}/pdf', [QuotationController::class, 'pdf'])->whereNumber('id');
            Route::get('quotations/{id}/revisions/{revision}/pdf', [QuotationController::class, 'revisionPdf'])->whereNumber(['id', 'revision']);

            Route::get('recipients', [RecipientController::class, 'index']);
            Route::post('recipients', [RecipientController::class, 'store']);
            Route::put('recipients/{id}', [RecipientController::class, 'update'])->whereNumber('id');
            Route::post('recipients/{id}/test', [RecipientController::class, 'test'])->whereNumber('id');
            Route::delete('recipients/{id}', [RecipientController::class, 'destroy'])->whereNumber('id');
        });

        // outreach (cold email campaigns): Super Admin and Admin
        Route::middleware('can:outreach.manage')->prefix('outreach')->group(function () {
            Route::get('/', fn () => redirect('/admin/outreach/contacts'));
            Route::get('accounts', [Outreach\AccountController::class, 'index']);
            Route::post('accounts', [Outreach\AccountController::class, 'store']);
            Route::put('accounts/{id}', [Outreach\AccountController::class, 'update'])->whereNumber('id');
            Route::post('accounts/{id}/test', [Outreach\AccountController::class, 'test'])->whereNumber('id');
            Route::delete('accounts/{id}', [Outreach\AccountController::class, 'destroy'])->whereNumber('id');
            Route::get('segments', [Outreach\SegmentController::class, 'index']);
            Route::post('segments', [Outreach\SegmentController::class, 'store']);
            Route::put('segments/{id}', [Outreach\SegmentController::class, 'update'])->whereNumber('id');
            Route::delete('segments/{id}', [Outreach\SegmentController::class, 'destroy'])->whereNumber('id');
            Route::get('contacts', [Outreach\ContactController::class, 'index']);
            Route::post('contacts', [Outreach\ContactController::class, 'store']);
            Route::post('contacts/bulk', [Outreach\ContactController::class, 'bulk']);
            Route::put('contacts/{id}', [Outreach\ContactController::class, 'update'])->whereNumber('id');
            Route::delete('contacts/{id}', [Outreach\ContactController::class, 'destroy'])->whereNumber('id');
            Route::get('import', [Outreach\ImportController::class, 'create']);
            Route::post('import', [Outreach\ImportController::class, 'store']);
            Route::get('import/{id}/mapping', [Outreach\ImportController::class, 'mapping'])->whereNumber('id');
            Route::post('import/{id}/run', [Outreach\ImportController::class, 'run'])->whereNumber('id');
            Route::get('import/{id}', [Outreach\ImportController::class, 'show'])->whereNumber('id');
            Route::get('replies', [Outreach\ReplyController::class, 'index']);
            Route::post('replies/poll', [Outreach\ReplyController::class, 'poll']);
            Route::post('replies/{id}/label', [Outreach\ReplyController::class, 'label'])->whereNumber('id');
            Route::post('replies/{id}/lead', [Outreach\ReplyController::class, 'lead'])->whereNumber('id');
            Route::get('templates', [Outreach\TemplateController::class, 'index']);
            Route::post('templates', [Outreach\TemplateController::class, 'store']);
            Route::post('templates/preview', [Outreach\TemplateController::class, 'preview']);
            Route::put('templates/{id}', [Outreach\TemplateController::class, 'update'])->whereNumber('id');
            Route::delete('templates/{id}', [Outreach\TemplateController::class, 'destroy'])->whereNumber('id');
            Route::get('blocklist', [Outreach\BlocklistController::class, 'index']);
            Route::post('blocklist/domains', [Outreach\BlocklistController::class, 'storeDomain']);
            Route::delete('blocklist/domains/{id}', [Outreach\BlocklistController::class, 'destroyDomain'])->whereNumber('id');
            Route::post('blocklist/emails', [Outreach\BlocklistController::class, 'storeEmails']);
            Route::delete('blocklist/emails/{id}', [Outreach\BlocklistController::class, 'destroyEmail'])->whereNumber('id');
            Route::get('campaigns', [Outreach\CampaignController::class, 'index']);
            Route::get('campaigns/create', [Outreach\CampaignController::class, 'create']);
            Route::post('campaigns', [Outreach\CampaignController::class, 'store']);
            Route::get('campaigns/{id}', [Outreach\CampaignController::class, 'show'])->whereNumber('id');
            Route::get('campaigns/{id}/edit', [Outreach\CampaignController::class, 'edit'])->whereNumber('id');
            Route::put('campaigns/{id}', [Outreach\CampaignController::class, 'update'])->whereNumber('id');
            Route::get('campaigns/{id}/export', [Outreach\CampaignController::class, 'export'])->whereNumber('id');
            Route::post('campaigns/{id}/start', [Outreach\CampaignController::class, 'start'])->whereNumber('id');
            Route::post('campaigns/{id}/pause', [Outreach\CampaignController::class, 'pause'])->whereNumber('id');
            Route::post('campaigns/{id}/resume', [Outreach\CampaignController::class, 'resume'])->whereNumber('id');
            Route::delete('campaigns/{id}', [Outreach\CampaignController::class, 'destroy'])->whereNumber('id');
        });

        // system
        Route::middleware('can:settings.manage')->group(function () {
            Route::post('stats/reset', [DashboardController::class, 'resetStats']);
            Route::get('settings', [SettingsController::class, 'index']);
            Route::put('settings', [SettingsController::class, 'update']);
        });
        Route::get('activity', [ActivityController::class, 'index'])->middleware('can:logs.view');
        Route::get('logins', [LoginHistoryController::class, 'index'])->middleware('can:logs.view');
        Route::get('logins/export', [LoginHistoryController::class, 'export'])->middleware('can:logs.view');
        Route::middleware('can:backup.manage')->prefix('backup')->group(function () {
            Route::get('/', [BackupController::class, 'index']);
            Route::post('/', [BackupController::class, 'store']);
            Route::get('{name}/download', [BackupController::class, 'download']);
            Route::delete('{name}', [BackupController::class, 'destroy']);
        });
    });
});
