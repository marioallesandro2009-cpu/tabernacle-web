<?php

use App\Models\TourCategory;
use Database\Seeders\ContentSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * For installed sites: adds the starter FAQ (only if the FAQ list is empty) and shows it on
 * the home and contact pages. Fresh installs get the same from the seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tour_categories') || TourCategory::count() === 0) {
            return;
        }
        $seeder = new ContentSeeder;
        $seeder->importFaqs();
        $seeder->addFaqSection(['home', 'contact']);
    }

    public function down(): void
    {
        // content change only
    }
};
