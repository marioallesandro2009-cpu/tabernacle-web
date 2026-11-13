<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outreach (cold email) module, phase 1: sender accounts, segments, contacts, imports and the
 * do-not-contact list. Campaigns and messages (with their `channel` column) come with phase 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_accounts', function (Blueprint $t) {
            $t->id();
            $t->string('channel', 20)->default('email');
            $t->string('name', 120);                       // sender display name
            $t->string('email', 190)->unique();
            $t->string('smtp_host', 190);
            $t->unsignedSmallInteger('smtp_port')->default(465);
            $t->string('smtp_encryption', 10)->default('ssl');   // ssl | tls | none
            $t->string('imap_host', 190)->nullable();
            $t->unsignedSmallInteger('imap_port')->default(993);
            $t->string('imap_encryption', 10)->default('ssl');
            $t->string('username', 190);
            $t->text('password');                           // encrypted with Crypt (model cast)
            $t->unsignedInteger('daily_limit')->default(30);
            $t->boolean('is_active')->default(true);
            $t->boolean('smtp_ok')->nullable();
            $t->timestamp('smtp_tested_at')->nullable();
            $t->string('smtp_error', 500)->nullable();
            $t->boolean('imap_ok')->nullable();
            $t->timestamp('imap_tested_at')->nullable();
            $t->string('imap_error', 500)->nullable();
            $t->unsignedBigInteger('imap_last_uid')->default(0);
            $t->timestamp('imap_polled_at')->nullable();
            $t->timestamps();
        });

        Schema::create('outreach_segments', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120)->unique();
            $t->string('description', 255)->nullable();
            $t->timestamps();
        });

        Schema::create('outreach_imports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('filename', 255);
            $t->string('path', 500);
            $t->string('status', 20)->default('uploaded');  // uploaded | processing | done | failed
            $t->json('mapping')->nullable();
            $t->unsignedBigInteger('default_segment_id')->nullable();
            $t->unsignedInteger('total_rows')->default(0);
            $t->unsignedInteger('created_count')->default(0);
            $t->unsignedInteger('duplicate_count')->default(0);
            $t->unsignedInteger('empty_count')->default(0);
            $t->unsignedInteger('suppressed_count')->default(0);
            $t->string('error', 500)->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
        });

        Schema::create('outreach_contacts', function (Blueprint $t) {
            $t->id();
            $t->string('name', 160)->nullable();
            $t->string('title', 160)->nullable();
            $t->string('organization', 190)->nullable();
            $t->string('email', 190)->nullable()->unique();   // null = no email (e.g. phone only)
            $t->string('phone', 60)->nullable();
            $t->foreignId('segment_id')->nullable()->constrained('outreach_segments')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->string('validation_status', 20)->default('pending'); // pending | valid | empty | invalid_format | no_mx
            $t->string('validation_note', 255)->nullable();
            $t->timestamp('validated_at')->nullable();
            $t->timestamp('suppressed_at')->nullable();
            $t->string('suppressed_reason', 20)->nullable();         // unsubscribed | bounced | manual
            $t->string('unsub_token', 40)->unique();
            $t->foreignId('import_id')->nullable()->constrained('outreach_imports')->nullOnDelete();
            $t->timestamps();
            $t->index('validation_status');
            $t->index('organization');
        });

        Schema::create('outreach_suppressions', function (Blueprint $t) {
            $t->id();
            $t->string('email', 190)->unique();
            $t->string('reason', 20)->default('manual');     // unsubscribed | bounced | manual
            $t->string('note', 255)->nullable();
            $t->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['outreach_suppressions', 'outreach_contacts', 'outreach_imports', 'outreach_segments', 'outreach_accounts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
