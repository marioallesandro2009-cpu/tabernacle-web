<?php

use App\Models\TourCategory;
use Database\Seeders\ContentSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/** Adds the "Seven Churches, Türkiye" chapter (9 photos) to the Journeys section on the home page. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tour_categories') || TourCategory::count() === 0) {
            return; // fresh install: the seeder does it
        }
        (new ContentSeeder)->addJourneyChapters();
    }

    public function down(): void
    {
        // content change only
    }
};
