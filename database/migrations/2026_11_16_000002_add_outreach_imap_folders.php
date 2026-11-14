<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Reading the Spam/Junk folder too: the last UID handled per folder, and where each message was found. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_accounts', function (Blueprint $t) {
            $t->json('imap_folder_uids')->nullable()->after('imap_last_uid'); // {"INBOX": 120, "INBOX.Spam": 8}
        });
        Schema::table('outreach_messages', function (Blueprint $t) {
            $t->string('folder', 120)->nullable()->after('imap_uid');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_messages', fn (Blueprint $t) => $t->dropColumn('folder'));
        Schema::table('outreach_accounts', fn (Blueprint $t) => $t->dropColumn('imap_folder_uids'));
    }
};
