<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sign-in protection without ever locking the owner out (device cookies, OWASP): trusted devices,
 * temporary blocks per IP / account+IP / device with growing durations, e-mailed login confirmations
 * for the "protected" mode, and that mode itself on the user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trusted_devices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->char('token_hash', 64)->unique();                // sha256 of the cookie value; the value itself is never stored
            $t->string('user_agent', 255)->nullable();
            $t->string('ip_last', 45)->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('two_factor_until')->nullable();      // "ingat perangkat ini 30 hari" for 2FA
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
        });

        Schema::create('login_blocks', function (Blueprint $t) {
            $t->id();
            $t->string('scope', 12);                              // ip | account_ip | device
            $t->string('key', 190)->index();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('ip', 45)->nullable();
            $t->unsignedTinyInteger('level')->default(0);         // 0 = 15 min, 1 = 30 min, 2 = 1 h … up to 24 h
            $t->timestamp('blocked_until');
            $t->timestamp('lifted_at')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index(['scope', 'key', 'blocked_until']);
        });

        Schema::create('login_confirmations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->char('token_hash', 64)->unique();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->boolean('remember')->default(false);
            $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable();
            $t->timestamp('created_at')->nullable();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->timestamp('protected_at')->nullable()->after('last_login_at'); // "protected" mode since
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('protected_at'));
        Schema::dropIfExists('login_confirmations');
        Schema::dropIfExists('login_blocks');
        Schema::dropIfExists('trusted_devices');
    }
};
