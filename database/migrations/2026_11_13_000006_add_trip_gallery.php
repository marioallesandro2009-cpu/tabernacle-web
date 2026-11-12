<?php

use App\Models\MenuItem;
use App\Models\TourCategory;
use App\Support\Content;
use Database\Seeders\ContentSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * For installed sites: adds the China Immersion Program 2026 photo albums, a "Galeri" page
 * (/galeri) that shows them, and a menu link. Everything is skipped if it already exists.
 * Fresh installs get the same from the seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tour_categories') || TourCategory::count() === 0) {
            return; // fresh install: the seeder does it
        }
        $seeder = new ContentSeeder;
        $seeder->importGalleries();
        $seeder->importPages(['gallery']);
        $seeder->importPosts(); // starter blog articles (skips any slug that already exists)

        foreach (['header', 'footer'] as $location) {
            if (MenuItem::where('location', $location)->where('url', '/galeri')->exists()) {
                continue;
            }
            $before = MenuItem::where('location', $location)->whereNull('parent_id')->where('url', 'like', '%/blog%')->first()
                ?? MenuItem::where('location', $location)->whereNull('parent_id')->where('url', 'like', '%contact%')->first();
            $sort = $before ? $before->sort : ((int) MenuItem::where('location', $location)->max('sort') + 1);
            MenuItem::where('location', $location)->whereNull('parent_id')->where('sort', '>=', $sort)->increment('sort');
            MenuItem::create(['location' => $location, 'label' => ['en' => 'Gallery', 'id' => 'Galeri'], 'url' => '/galeri', 'sort' => $sort, 'is_active' => true, 'new_tab' => false]);
        }
        Content::bump();
    }

    public function down(): void
    {
        // content change only
    }
};
