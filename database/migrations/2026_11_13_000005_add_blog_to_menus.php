<?php

use App\Models\MenuItem;
use App\Support\Content;
use Illuminate\Database\Migrations\Migration;

/** Puts "Blog" in the header and footer menus (before "Contact") if it is not there yet. */
return new class extends Migration
{
    public function up(): void
    {
        if (MenuItem::count() === 0) {
            return; // fresh install: menus come from the seeder
        }
        foreach (['header', 'footer'] as $location) {
            if (MenuItem::where('location', $location)->where('url', 'like', '%/blog%')->exists()) {
                continue;
            }
            $contact = MenuItem::where('location', $location)->whereNull('parent_id')->where('url', 'like', '%contact%')->first();
            $sort = $contact ? $contact->sort : ((int) MenuItem::where('location', $location)->max('sort') + 1);
            MenuItem::where('location', $location)->whereNull('parent_id')->where('sort', '>=', $sort)->increment('sort');
            MenuItem::create(['location' => $location, 'label' => ['en' => 'Blog', 'id' => 'Blog'], 'url' => '/blog', 'sort' => $sort, 'is_active' => true, 'new_tab' => false]);
        }
        Content::bump();
    }

    public function down(): void
    {
        MenuItem::where('url', '/blog')->delete();
    }
};
