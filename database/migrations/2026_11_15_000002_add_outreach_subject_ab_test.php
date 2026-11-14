<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A/B test of the first email's subject: a second subject per campaign, and which one each person got. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_campaigns', function (Blueprint $t) {
            $t->string('subject_b', 500)->nullable()->after('name');
        });
        Schema::table('outreach_campaign_contacts', function (Blueprint $t) {
            $t->char('variant', 1)->nullable()->after('current_step'); // A | B, set when the first email goes out
        });
    }

    public function down(): void
    {
        Schema::table('outreach_campaign_contacts', fn (Blueprint $t) => $t->dropColumn('variant'));
        Schema::table('outreach_campaigns', fn (Blueprint $t) => $t->dropColumn('subject_b'));
    }
};
