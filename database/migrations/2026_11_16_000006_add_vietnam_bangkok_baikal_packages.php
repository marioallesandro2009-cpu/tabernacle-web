<?php

use App\Models\TourCategory;
use App\Support\Content;
use Database\Seeders\ContentSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * For sites that are already installed: adds four packages (Heritage Rails & Halong Sails,
 * Hanoi – Sapa, Year End Bangkok Pattaya, Magical Winter Lake Baikal + Beijing) and the
 * Russia destination if missing, then fills their English texts. Fresh installs get the
 * same from the seeder, so this does nothing when the tables are still empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tour_categories') || TourCategory::count() === 0) {
            return;
        }

        $seeder = new ContentSeeder;
        $seeder->importTours([
            '4d3n-heritage-rails-and-halong-sails',
            '5d4n-hanoi-sapa-by-sleeper-train-and-limousine-bus',
            '5d4n-year-end-bangkok-pattaya',
            '10d8n-magical-winter-lake-baikal-beijing',
        ]);
        $seeder->importEnglish();
        Content::bump();
    }

    public function down(): void
    {
        // content change only
    }
};
