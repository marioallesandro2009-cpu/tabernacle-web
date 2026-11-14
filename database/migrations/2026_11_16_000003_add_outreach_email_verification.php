<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stronger checks of contact addresses: role addresses (info@, sales@ …) flagged as a warning, and
 * the result of an email verification service (ZeroBounce, Reoon). A campaign may allow catch-all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_contacts', function (Blueprint $t) {
            $t->boolean('is_role')->default(false)->index()->after('validated_at');
            $t->string('verify_status', 12)->nullable()->index()->after('is_role'); // valid | invalid | catch_all | unknown | disposable
            $t->string('verify_note', 255)->nullable()->after('verify_status');
            $t->string('verify_provider', 20)->nullable()->after('verify_note');
            $t->timestamp('verified_at')->nullable()->after('verify_provider');
        });
        Schema::table('outreach_campaigns', function (Blueprint $t) {
            $t->boolean('allow_catch_all')->default(false)->after('gap_max_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_campaigns', fn (Blueprint $t) => $t->dropColumn('allow_catch_all'));
        Schema::table('outreach_contacts', function (Blueprint $t) {
            $t->dropIndex(['is_role']);
            $t->dropIndex(['verify_status']);
            $t->dropColumn(['is_role', 'verify_status', 'verify_note', 'verify_provider', 'verified_at']);
        });
    }
};
