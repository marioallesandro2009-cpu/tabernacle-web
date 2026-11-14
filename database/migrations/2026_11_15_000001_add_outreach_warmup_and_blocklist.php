<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Warm-up per sender account (the daily limit grows week by week up to daily_limit) and a list of
 * blocked domains (competitors, existing clients) that no campaign may ever write to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_accounts', function (Blueprint $t) {
            $t->boolean('warmup_enabled')->default(true)->after('daily_limit');
            $t->unsignedSmallInteger('warmup_start')->default(10)->after('warmup_enabled');
            $t->unsignedSmallInteger('warmup_step')->default(5)->after('warmup_start');
            $t->date('warmup_started_on')->nullable()->after('warmup_step'); // the day of the first email sent
        });

        Schema::create('outreach_blocked_domains', function (Blueprint $t) {
            $t->id();
            $t->string('domain', 190)->unique();
            $t->string('note', 255)->nullable();
            $t->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_blocked_domains');
        Schema::table('outreach_accounts', function (Blueprint $t) {
            $t->dropColumn(['warmup_enabled', 'warmup_start', 'warmup_step', 'warmup_started_on']);
        });
    }
};
