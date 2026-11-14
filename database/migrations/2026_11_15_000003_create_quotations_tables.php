<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Price quotations (penawaran harga) made in the admin, often from a lead, edited freely and
 * downloaded as PDF. Once a quotation has been sent, editing it keeps the old version as a revision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $t) {
            $t->id();
            $t->string('number', 60)->unique();                  // TW/QUO/2026/11/001
            $t->unsignedSmallInteger('revision')->default(0);    // 0 = original, 1 = Rev. 1, …
            $t->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('tour_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status', 12)->default('draft')->index(); // draft | sent | accepted | rejected
            $t->char('currency', 3)->default('IDR');
            $t->date('issued_on');
            $t->date('valid_until')->nullable();
            $t->string('client_name', 160);
            $t->string('client_company', 190)->nullable();
            $t->string('client_email', 190)->nullable();
            $t->string('client_phone', 60)->nullable();
            $t->text('client_address')->nullable();
            $t->string('title', 255);                            // what is offered, e.g. the tour name
            $t->date('travel_date')->nullable();
            $t->unsignedSmallInteger('pax')->nullable();
            $t->json('items');                                   // [{description, qty, unit, price}]
            $t->decimal('discount', 15, 2)->default(0);
            $t->decimal('tax_percent', 5, 2)->default(0);
            $t->string('tax_label', 40)->nullable();             // "PPN" …
            $t->text('notes')->nullable();
            $t->text('terms')->nullable();
            $t->text('payment_info')->nullable();
            $t->string('signatory_name', 120)->nullable();
            $t->string('signatory_title', 120)->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('quotation_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('revision');
            $t->json('snapshot');                                // the whole quotation as it was
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->nullable();
            $t->unique(['quotation_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_revisions');
        Schema::dropIfExists('quotations');
    }
};
