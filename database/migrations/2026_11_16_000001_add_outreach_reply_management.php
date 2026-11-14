<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Handling replies from the website: forwarding a prospect's reply to personal addresses, answering
 * from the website, answering from a personal mailbox (relay), and the status of each of our replies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_accounts', function (Blueprint $t) {
            $t->boolean('forward_enabled')->default(false)->after('warmup_started_on');
            $t->text('forward_to')->nullable()->after('forward_enabled');          // addresses, one per line
            $t->boolean('relay_enabled')->default(false)->after('forward_to');
            $t->text('relay_senders')->nullable()->after('relay_enabled');         // personal addresses allowed to answer
            $t->string('relay_mode', 10)->default('review')->after('relay_senders'); // direct | review
        });

        Schema::table('outreach_campaign_contacts', function (Blueprint $t) {
            $t->string('thread_token', 12)->nullable()->unique()->after('root_message_id'); // [#TOKEN] in forwarded subjects
        });

        Schema::table('outreach_messages', function (Blueprint $t) {
            $t->string('reply_status', 12)->nullable()->index()->after('status'); // our manual replies: draft | received | rejected | queued | sent | failed | bounced
            $t->json('status_log')->nullable()->after('reply_status');            // [{status, at, reason}]
            $t->string('source', 10)->nullable()->after('status_log');            // website | email
            $t->foreignId('user_id')->nullable()->after('source')->constrained('users')->nullOnDelete(); // the admin who wrote it
            $t->string('relay_from', 190)->nullable()->after('user_id');          // personal address it came from
            $t->string('inbound_message_id', 255)->nullable()->index()->after('relay_from'); // Message-ID of the relay email
        });
    }

    public function down(): void
    {
        Schema::table('outreach_messages', function (Blueprint $t) {
            $t->dropConstrainedForeignId('user_id');
            $t->dropIndex(['reply_status']);
            $t->dropIndex(['inbound_message_id']);
            $t->dropColumn(['reply_status', 'status_log', 'source', 'relay_from', 'inbound_message_id']);
        });
        Schema::table('outreach_campaign_contacts', function (Blueprint $t) {
            $t->dropUnique(['thread_token']);
            $t->dropColumn('thread_token');
        });
        Schema::table('outreach_accounts', function (Blueprint $t) {
            $t->dropColumn(['forward_enabled', 'forward_to', 'relay_enabled', 'relay_senders', 'relay_mode']);
        });
    }
};
