<?php

use App\Models\Page;
use App\Support\Content;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the "Jajaran petinggi" (leadership) section to the About page, right after the CEO
 * section. It lists the members from the Tim module and stays hidden while that list is empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }
        $page = Page::where('key', 'about')->first();
        if (! $page || $page->sections()->where('type', 'team')->exists()) {
            return;
        }
        $ceo = $page->sections()->where('type', 'ceo')->orderBy('sort')->first();
        $sort = $ceo ? $ceo->sort + 1 : ((int) $page->sections()->max('sort') + 1);
        $page->sections()->where('sort', '>=', $sort)->increment('sort');
        $page->sections()->create([
            'type' => 'team', 'name' => 'Jajaran petinggi', 'sort' => $sort, 'is_visible' => true,
            'data' => ['kicker' => ['en' => 'Our team', 'id' => 'Tim kami'], 'title_a' => ['en' => 'Our', 'id' => 'Jajaran'], 'title_b' => ['en' => 'leadership', 'id' => 'petinggi']],
        ]);
        Content::bump();
    }

    public function down(): void
    {
        // content change only
    }
};
