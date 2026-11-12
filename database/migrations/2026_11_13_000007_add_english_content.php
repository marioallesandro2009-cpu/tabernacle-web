<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * English versions of the long package and article texts. The Indonesian columns stay the
 * main version; an empty English field falls back to Indonesian on the English site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $t) {
            $t->json('itinerary_en')->nullable()->after('itinerary');
            $t->string('dates_label_en', 500)->nullable()->after('dates_label');
            $t->json('includes_en')->nullable()->after('includes');
            $t->json('excludes_en')->nullable()->after('excludes');
            $t->text('terms_en')->nullable()->after('terms');
        });
        Schema::table('posts', function (Blueprint $t) {
            $t->string('title_en')->nullable()->after('title');
            $t->text('excerpt_en')->nullable()->after('excerpt');
            $t->longText('content_en')->nullable()->after('content');
            $t->string('meta_title_en')->nullable()->after('meta_title');
            $t->text('meta_description_en')->nullable()->after('meta_description');
            $t->json('faq_en')->nullable()->after('faq');
        });
        Schema::table('post_categories', function (Blueprint $t) {
            $t->string('name_en')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('tours', fn (Blueprint $t) => $t->dropColumn(['itinerary_en', 'dates_label_en', 'includes_en', 'excludes_en', 'terms_en']));
        Schema::table('posts', fn (Blueprint $t) => $t->dropColumn(['title_en', 'excerpt_en', 'content_en', 'meta_title_en', 'meta_description_en', 'faq_en']));
        Schema::table('post_categories', fn (Blueprint $t) => $t->dropColumn('name_en'));
    }
};
