<?php

use App\Models\TourCategory;
use Database\Seeders\ContentSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * For installed sites: fills the new English fields of the seeded packages and articles.
 * Only empty English fields are filled. Fresh installs get the same from the seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tour_categories') || TourCategory::count() === 0) {
            return;
        }
        (new ContentSeeder)->importEnglish();
    }

    public function down(): void
    {
        // content change only
    }
};
