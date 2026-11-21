<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A "manual" campaign is made from contacts ticked on the Kontak page: it holds exactly those
 * people, so starting or resuming it never pulls in the rest of a segment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_campaigns', function (Blueprint $t) {
            $t->boolean('manual')->default(false)->after('segment_id');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_campaigns', function (Blueprint $t) {
            $t->dropColumn('manual');
        });
    }
};
