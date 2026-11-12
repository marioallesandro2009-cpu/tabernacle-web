<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Audit log: remember which browser/device each action came from, and speed up the login-history view. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $t) {
            $t->string('user_agent', 255)->nullable()->after('ip');
            $t->index(['action', 'created_at']);
            $t->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $t) {
            $t->dropIndex(['action', 'created_at']);
            $t->dropIndex(['user_id', 'created_at']);
            $t->dropColumn('user_agent');
        });
    }
};
