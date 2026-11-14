<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Content structure of an established company: one pillar page per service line (linking its
 * packages, destinations, articles and case studies), a case-study module, and the trust pages
 * (privacy, terms, careers, press, partners) as empty, inactive templates for the admin to fill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pillars', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 120)->unique();
            $t->json('title');
            $t->json('intro')->nullable();
            $t->unsignedBigInteger('cover_id')->nullable();
            $t->json('tour_category_ids')->nullable();   // packages of these categories
            $t->json('post_category_ids')->nullable();   // articles of these categories
            $t->string('keywords', 500)->nullable();      // more articles/destinations: words separated by commas
            $t->json('meta_title')->nullable();
            $t->json('meta_description')->nullable();
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('is_active')->default(false);
            $t->timestamps();
        });

        Schema::create('case_studies', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 160)->unique();
            $t->json('title');
            $t->string('client', 190)->nullable();
            $t->unsignedBigInteger('client_logo_id')->nullable();
            $t->foreignId('pillar_id')->nullable()->constrained()->nullOnDelete();
            $t->json('objective')->nullable();           // what the program was for
            $t->unsignedInteger('participants')->nullable();
            $t->json('period')->nullable();              // "Maret 2026"
            $t->unsignedBigInteger('cover_id')->nullable();
            $t->json('gallery')->nullable();             // [mediaId, …]
            $t->json('results')->nullable();             // outcome
            $t->json('meta_title')->nullable();
            $t->json('meta_description')->nullable();
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('case_study_destination', function (Blueprint $t) {
            $t->foreignId('case_study_id')->constrained()->cascadeOnDelete();
            $t->foreignId('destination_id')->constrained()->cascadeOnDelete();
            $t->primary(['case_study_id', 'destination_id']);
        });

        // the four service lines, as inactive shells: their texts are written in the admin
        $now = now();
        foreach ([
            ['corporate-mice', 'Corporate & MICE', 'Corporate & MICE'],
            ['studi-banding', 'Program Studi Banding & Delegasi', 'Study Visits & Delegations'],
            ['wisata-rohani', 'Wisata Rohani', 'Pilgrimage Tours'],
            ['tour-reguler', 'Tour Reguler', 'Regular Tours'],
        ] as $i => [$slug, $id, $en]) {
            DB::table('pillars')->insert(['slug' => $slug, 'title' => json_encode(['id' => $id, 'en' => $en]), 'sort' => $i + 1, 'is_active' => false, 'created_at' => $now, 'updated_at' => $now]);
        }

        // trust pages: empty and inactive until the admin fills them in (an existing page with the same address is kept)
        foreach ([
            ['kebijakan-privasi', 'Kebijakan Privasi', 'Privacy Policy'],
            ['syarat-ketentuan', 'Syarat & Ketentuan', 'Terms & Conditions'],
            ['karier', 'Karier', 'Careers'],
            ['media', 'Media & Press', 'Media & Press'],
            ['mitra', 'Mitra', 'Partners'],
        ] as [$slug, $id, $en]) {
            if (DB::table('pages')->where('slug', $slug)->exists()) {
                continue;
            }
            $pageId = DB::table('pages')->insertGetId(['key' => 'trust-'.$slug, 'slug' => $slug, 'title' => json_encode(['id' => $id, 'en' => $en]),
                'is_active' => false, 'is_system' => false, 'noindex' => false, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('sections')->insert(['page_id' => $pageId, 'type' => 'text_block', 'sort' => 1, 'is_visible' => true,
                'data' => json_encode(['title_a' => ['id' => $id, 'en' => $en], 'body' => ['id' => '', 'en' => '']]), 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('pages')->where('key', 'like', 'trust-%')->where('is_active', false)->delete();
        Schema::dropIfExists('case_study_destination');
        Schema::dropIfExists('case_studies');
        Schema::dropIfExists('pillars');
    }
};
