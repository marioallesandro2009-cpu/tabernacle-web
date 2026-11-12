<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_active')->default(true)->after('password');
            $t->unsignedBigInteger('avatar_id')->nullable()->after('is_active');
            $t->text('two_factor_secret')->nullable();
            $t->timestamp('two_factor_confirmed_at')->nullable();
            $t->timestamp('last_login_at')->nullable();
        });

        Schema::create('media', function (Blueprint $t) {
            $t->id();
            $t->string('folder', 80)->default('umum')->index();
            $t->string('kind', 12)->default('image'); // image | file | video
            $t->string('path');
            $t->json('variants')->nullable();          // {"480": "path", "960": "path", ...}
            $t->string('name');
            $t->string('mime', 100)->nullable();
            $t->unsignedBigInteger('size')->default(0);
            $t->unsignedInteger('width')->nullable();
            $t->unsignedInteger('height')->nullable();
            $t->string('alt', 255)->nullable();
            $t->timestamps();
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->longText('value')->nullable();
            $t->timestamps();
        });

        Schema::create('ui_strings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->string('group', 40)->default('umum')->index();
            $t->text('text_en')->nullable();
            $t->text('text_id')->nullable();
            $t->timestamps();
        });

        Schema::create('pages', function (Blueprint $t) {
            $t->id();
            $t->string('key', 40)->unique();            // home, about, packages, contact, blog, destinations
            $t->string('slug', 120)->unique();
            $t->json('title')->nullable();
            $t->json('meta_title')->nullable();
            $t->json('meta_description')->nullable();
            $t->unsignedBigInteger('og_image_id')->nullable();
            $t->boolean('noindex')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('sections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('page_id')->constrained()->cascadeOnDelete();
            $t->string('type', 40);
            $t->string('name', 120)->nullable();
            $t->json('data')->nullable();
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('is_visible')->default(true);
            $t->timestamps();
            $t->index(['page_id', 'sort']);
        });

        Schema::create('menu_items', function (Blueprint $t) {
            $t->id();
            $t->string('location', 20)->index();         // header | footer
            $t->unsignedBigInteger('parent_id')->nullable()->index();
            $t->json('label');
            $t->string('url', 500);
            $t->boolean('new_tab')->default(false);
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('tour_categories', function (Blueprint $t) {
            $t->id();
            $t->json('name');
            $t->string('slug', 120)->unique();
            $t->string('kind', 20)->default('group');    // group | fit | private | worship
            $t->boolean('flights_included')->default(false);
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('destinations', function (Blueprint $t) {
            $t->id();
            $t->json('name');
            $t->string('slug', 120)->unique();
            $t->string('region', 30)->default('asia')->index();
            $t->json('intro')->nullable();
            $t->unsignedBigInteger('cover_id')->nullable();
            $t->json('meta_title')->nullable();
            $t->json('meta_description')->nullable();
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('tours', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->nullable()->constrained('tour_categories')->nullOnDelete();
            $t->string('name');
            $t->string('slug', 160)->unique();
            $t->string('duration', 20)->nullable();
            $t->string('route', 500)->nullable();
            $t->json('area_label')->nullable();           // "Western Europe" / "Eropa Barat"
            $t->string('region', 30)->default('asia')->index();
            $t->json('highlights')->nullable();           // ["Blue Mosque", ...]
            $t->json('description')->nullable();          // translatable intro
            $t->unsignedBigInteger('cover_id')->nullable();
            $t->string('cover_credit', 500)->nullable();
            $t->json('gallery')->nullable();              // [mediaId, ...]
            $t->unsignedBigInteger('pdf_id')->nullable();
            $t->json('itinerary')->nullable();            // [{n,title,meals,text,hotel}]
            $t->string('dates_label', 255)->nullable();
            $t->json('departures')->nullable();           // [{date,price_adult,price_child,single_supp,quota,remaining,status,note}]
            $t->unsignedBigInteger('price_from')->nullable();
            $t->unsignedSmallInteger('min_pax')->nullable();
            $t->unsignedBigInteger('dp_min')->nullable();
            $t->boolean('flights_included')->default(false);
            $t->json('includes')->nullable();
            $t->json('excludes')->nullable();
            $t->longText('terms')->nullable();            // catatan & syarat (HTML bersih)
            $t->boolean('has_shopping')->default(false);
            $t->json('promo_label')->nullable();
            $t->boolean('is_featured')->default(false);
            $t->unsignedInteger('featured_sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort')->default(0);
            $t->json('meta_title')->nullable();
            $t->json('meta_description')->nullable();
            $t->unsignedBigInteger('og_image_id')->nullable();
            $t->unsignedBigInteger('views')->default(0);
            $t->timestamps();
        });

        Schema::create('destination_tour', function (Blueprint $t) {
            $t->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $t->foreignId('destination_id')->constrained()->cascadeOnDelete();
            $t->primary(['tour_id', 'destination_id']);
        });

        Schema::create('testimonials', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->unsignedBigInteger('photo_id')->nullable();
            $t->string('trip')->nullable();
            $t->json('content');
            $t->unsignedTinyInteger('rating')->default(5);
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('galleries', function (Blueprint $t) {
            $t->id();
            $t->json('title');
            $t->string('trip')->nullable();
            $t->json('description')->nullable();
            $t->unsignedBigInteger('cover_id')->nullable();
            $t->json('items')->nullable();                // [{media_id, caption}]
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('faqs', function (Blueprint $t) {
            $t->id();
            $t->json('question');
            $t->json('answer');
            $t->string('group', 80)->nullable();
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('team_members', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->json('role')->nullable();
            $t->json('bio')->nullable();
            $t->unsignedBigInteger('photo_id')->nullable();
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('redirects', function (Blueprint $t) {
            $t->id();
            $t->string('from_path', 500)->unique();
            $t->string('to_url', 1000);
            $t->unsignedSmallInteger('code')->default(301);
            $t->unsignedBigInteger('hits')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('leads', function (Blueprint $t) {
            $t->id();
            $t->string('type', 12)->default('form')->index();  // form | wa_click
            $t->string('name')->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('email')->nullable();
            $t->foreignId('tour_id')->nullable()->constrained()->nullOnDelete();
            $t->string('tour_name')->nullable();
            $t->unsignedSmallInteger('pax')->nullable();
            $t->date('plan_date')->nullable();
            $t->text('message')->nullable();
            $t->string('status', 20)->default('baru')->index();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->string('page_url', 500)->nullable();
            $t->string('referrer', 500)->nullable();
            $t->string('source', 40)->nullable()->index();
            $t->string('utm_source')->nullable();
            $t->string('utm_medium')->nullable();
            $t->string('utm_campaign')->nullable();
            $t->string('ip_hash', 64)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->timestamps();
            $t->index('created_at');
        });

        Schema::create('lead_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('event', 30);                        // status | note | assign | created
            $t->string('from_value')->nullable();
            $t->string('to_value')->nullable();
            $t->text('note')->nullable();
            $t->timestamps();
        });

        Schema::create('lead_recipients', function (Blueprint $t) {
            $t->id();
            $t->string('email')->unique();
            $t->string('name')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('page_views', function (Blueprint $t) {
            $t->id();
            $t->date('day')->index();
            $t->timestamp('created_at')->nullable();
            $t->string('path', 500);
            $t->string('page_type', 20)->default('page');   // page | tour | post | destination
            $t->unsignedBigInteger('ref_id')->nullable();
            $t->string('visitor_hash', 64)->index();
            $t->boolean('is_entry')->default(false);
            $t->string('referrer_host', 190)->nullable();
            $t->string('source', 40)->default('direct');
            $t->string('utm_source', 100)->nullable();
            $t->string('utm_medium', 100)->nullable();
            $t->string('utm_campaign', 100)->nullable();
            $t->string('device', 12)->default('desktop');
            $t->string('browser', 30)->nullable();
        });

        Schema::create('analytics_daily', function (Blueprint $t) {
            $t->id();
            $t->date('day');
            $t->string('dimension', 20);                    // total | path | tour | post | source | device | browser | entry
            $t->string('dim_key', 190);
            $t->unsignedInteger('views')->default(0);
            $t->unsignedInteger('visitors')->default(0);
            $t->unique(['day', 'dimension', 'dim_key']);
            $t->index(['dimension', 'day']);
        });

        Schema::create('post_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug', 160)->unique();
            $t->text('description')->nullable();
            $t->timestamps();
        });

        Schema::create('tags', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug', 160)->unique();
            $t->timestamps();
        });

        Schema::create('posts', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('slug', 190)->unique();
            $t->text('excerpt')->nullable();
            $t->longText('content')->nullable();
            $t->unsignedBigInteger('cover_id')->nullable();
            $t->foreignId('category_id')->nullable()->constrained('post_categories')->nullOnDelete();
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 12)->default('draft')->index(); // draft | scheduled | published
            $t->timestamp('published_at')->nullable()->index();
            $t->string('focus_keyword')->nullable();
            $t->string('meta_title')->nullable();
            $t->string('meta_description', 400)->nullable();
            $t->unsignedBigInteger('og_image_id')->nullable();
            $t->json('faq')->nullable();                     // [{q,a}]
            $t->unsignedTinyInteger('seo_score')->default(0);
            $t->unsignedSmallInteger('reading_time')->default(1);
            $t->unsignedBigInteger('views')->default(0);
            $t->timestamps();
        });

        Schema::create('post_tag', function (Blueprint $t) {
            $t->foreignId('post_id')->constrained()->cascadeOnDelete();
            $t->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $t->primary(['post_id', 'tag_id']);
        });

        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 30);
            $t->string('subject_type', 80)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('subject_label')->nullable();
            $t->json('changes')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        foreach (['activity_logs', 'post_tag', 'posts', 'tags', 'post_categories', 'analytics_daily', 'page_views',
            'lead_recipients', 'lead_events', 'leads', 'redirects', 'team_members', 'faqs', 'galleries', 'testimonials',
            'destination_tour', 'tours', 'destinations', 'tour_categories', 'menu_items', 'sections', 'pages',
            'ui_strings', 'settings', 'media'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['is_active', 'avatar_id', 'two_factor_secret', 'two_factor_confirmed_at', 'last_login_at']);
        });
    }
};
