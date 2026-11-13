<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outreach phase 2: templates, campaigns (with steps and sender accounts), the state of each
 * contact inside a campaign, and every message. `channel` is stored on campaigns, templates and
 * messages (only "email" today) so another channel can be added later without a restructure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_templates', function (Blueprint $t) {
            $t->id();
            $t->string('channel', 20)->default('email');
            $t->string('name', 120);
            $t->foreignId('segment_id')->nullable()->constrained('outreach_segments')->nullOnDelete();
            $t->string('subject', 250);
            $t->text('body');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('outreach_campaigns', function (Blueprint $t) {
            $t->id();
            $t->string('channel', 20)->default('email');
            $t->string('name', 150);
            $t->foreignId('segment_id')->nullable()->constrained('outreach_segments')->nullOnDelete();   // null = every segment
            $t->string('status', 20)->default('draft');          // draft | running | paused | completed
            $t->string('window_start', 5)->default('08:00');     // WIB
            $t->string('window_end', 5)->default('17:00');
            $t->json('send_days');                                // ISO weekdays, 1 = Monday
            $t->unsignedSmallInteger('gap_min_seconds')->default(60);
            $t->unsignedSmallInteger('gap_max_seconds')->default(240);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('outreach_campaign_steps', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campaign_id')->constrained('outreach_campaigns')->cascadeOnDelete();
            $t->unsignedTinyInteger('step');                      // 1, 2, 3
            $t->foreignId('template_id')->nullable()->constrained('outreach_templates')->nullOnDelete();
            $t->unsignedSmallInteger('day_offset')->default(0);   // days after the first email
            $t->unique(['campaign_id', 'step']);
        });

        Schema::create('outreach_campaign_accounts', function (Blueprint $t) {
            $t->foreignId('campaign_id')->constrained('outreach_campaigns')->cascadeOnDelete();
            $t->foreignId('account_id')->constrained('outreach_accounts')->cascadeOnDelete();
            $t->primary(['campaign_id', 'account_id']);
        });

        Schema::create('outreach_campaign_contacts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campaign_id')->constrained('outreach_campaigns')->cascadeOnDelete();
            $t->foreignId('contact_id')->constrained('outreach_contacts')->cascadeOnDelete();
            $t->foreignId('account_id')->nullable()->constrained('outreach_accounts')->nullOnDelete(); // sender, kept for every follow-up
            $t->string('status', 20)->default('queued');   // queued | sent | replied | bounced | auto_replied | unsubscribed | invalid | failed
            $t->unsignedTinyInteger('current_step')->default(0);   // steps already sent
            $t->timestamp('next_send_at')->nullable();
            $t->timestamp('first_sent_at')->nullable();
            $t->timestamp('last_sent_at')->nullable();
            $t->string('root_message_id', 255)->nullable();        // first email, for threading
            $t->timestamps();
            $t->unique(['campaign_id', 'contact_id']);
            $t->index(['status', 'next_send_at']);
        });

        Schema::create('outreach_messages', function (Blueprint $t) {
            $t->id();
            $t->string('channel', 20)->default('email');
            $t->foreignId('campaign_id')->nullable()->constrained('outreach_campaigns')->nullOnDelete();
            $t->foreignId('campaign_contact_id')->nullable()->constrained('outreach_campaign_contacts')->nullOnDelete();
            $t->foreignId('contact_id')->nullable()->constrained('outreach_contacts')->nullOnDelete();
            $t->foreignId('account_id')->nullable()->constrained('outreach_accounts')->nullOnDelete();
            $t->string('direction', 3)->default('out');            // out | in
            $t->string('kind', 20)->default('email');              // email | reply | bounce | auto_reply
            $t->unsignedTinyInteger('step')->nullable();
            $t->string('subject', 500)->nullable();
            $t->longText('body')->nullable();
            $t->string('message_id', 255)->nullable()->index();
            $t->string('in_reply_to', 255)->nullable();
            $t->text('references')->nullable();
            $t->string('status', 20)->default('scheduled');       // scheduled | sending | sent | failed | cancelled | received
            $t->timestamp('scheduled_at')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->string('error', 500)->nullable();
            $t->string('label', 20)->nullable();                   // replies: interested | not_now | not_interested
            $t->unsignedBigInteger('lead_id')->nullable();
            $t->unsignedBigInteger('imap_uid')->nullable();
            $t->timestamps();
            $t->index(['account_id', 'scheduled_at']);
            $t->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        foreach (['outreach_messages', 'outreach_campaign_contacts', 'outreach_campaign_accounts', 'outreach_campaign_steps', 'outreach_campaigns', 'outreach_templates'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
