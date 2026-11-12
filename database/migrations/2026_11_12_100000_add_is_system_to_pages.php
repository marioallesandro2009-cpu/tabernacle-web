<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Built-in pages (home, about, ...) are marked so they cannot be deleted; admins may add their own pages. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $t) {
            $t->boolean('is_system')->default(false)->after('key');
        });
        DB::table('pages')->whereIn('key', ['home', 'about', 'packages', 'contact', 'blog', 'destinations'])->update(['is_system' => true]);
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $t) {
            $t->dropColumn('is_system');
        });
    }
};
