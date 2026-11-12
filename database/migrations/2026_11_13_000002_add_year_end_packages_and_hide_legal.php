<?php

use App\Models\Section;
use App\Models\TourCategory;
use App\Support\Content;
use Database\Seeders\ContentSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * For sites that are already installed:
 *  - adds three new packages (Umrah 18 Dec 2026, Year End Korea Winter, West Coast USA) if missing;
 *  - hides the "Legalitas" section by default (an admin can switch it back on in Halaman & section).
 * Fresh installs get the same from the seeder, so this does nothing when the tables are still empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tour_categories') || TourCategory::count() === 0) {
            return; // fresh install: the seeder handles everything
        }

        (new ContentSeeder)->importTours([
            '9d7n-umrah-reguler-18-desember-2026',
            '6d4n-year-end-korea-winter-experience',
            '6d5n-west-coast-usa-tour',
        ]);

        Section::where('type', 'legal')->update(['is_visible' => false]);
        Content::bump();
    }

    public function down(): void
    {
        // content change only
    }
};
