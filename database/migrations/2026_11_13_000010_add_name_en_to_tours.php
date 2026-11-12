<?php

use App\Models\TourCategory;
use Database\Seeders\ContentSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** English package name (optional; empty = the Indonesian name is shown on the English site). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $t) {
            $t->string('name_en')->nullable()->after('name');
        });
        if (Schema::hasTable('tour_categories') && TourCategory::count() > 0) {
            (new ContentSeeder)->importEnglish(); // fills only empty English fields
        }
    }

    public function down(): void
    {
        Schema::table('tours', fn (Blueprint $t) => $t->dropColumn('name_en'));
    }
};
