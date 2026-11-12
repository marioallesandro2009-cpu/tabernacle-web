<?php

use App\Jobs\SendLeadNotification;
use App\Mail\NewLeadMail;
use App\Models\Lead;
use App\Models\LeadRecipient;
use App\Support\Settings;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

function leadPayload(array $o = []): array
{
    return array_merge(['name' => 'Budi Santoso', 'phone' => '0812-9797-1563', 'email' => 'budi@example.test', 'pax' => 2, 'message' => 'Mau tanya paket Jepang'], $o);
}

it('stores a valid lead with a normalised phone number and records its origin', function () {
    Queue::fake();
    $this->postJson('/leads', leadPayload(['utm_source' => 'instagram', 'page_url' => 'https://x.test/packages/jepang']))->assertOk()->assertJson(['ok' => true]);
    $lead = Lead::first();
    expect($lead->phone)->toBe('6281297971563')->and($lead->type)->toBe('form')->and($lead->status)->toBe('baru')
        ->and($lead->utm_source)->toBe('instagram')->and($lead->page_url)->toBe('https://x.test/packages/jepang')
        ->and($lead->ip_hash)->not->toContain('127.0.0.1')->and($lead->events()->count())->toBe(1);
    Queue::assertPushed(SendLeadNotification::class);
});

it('accepts common Indonesian phone formats', function (string $raw) {
    Queue::fake();
    $this->postJson('/leads', leadPayload(['phone' => $raw]))->assertOk();
    expect(Lead::first()->phone)->toBe('6281297971563');
})->with(['08129797 1563', '+62 812-9797-1563', '6281297971563', '81297971563']);

it('rejects invalid phone numbers and missing fields', function () {
    Queue::fake();
    $this->postJson('/leads', leadPayload(['phone' => '12345']))->assertStatus(422)->assertJsonValidationErrors('phone');
    $this->postJson('/leads', leadPayload(['phone' => '0212345678']))->assertStatus(422);
    $this->postJson('/leads', leadPayload(['name' => '']))->assertStatus(422)->assertJsonValidationErrors('name');
    $this->postJson('/leads', leadPayload(['email' => 'not-an-email']))->assertStatus(422);
    expect(Lead::count())->toBe(0);
});

it('silently drops honeypot submissions', function () {
    Queue::fake();
    $this->postJson('/leads', leadPayload(['website' => 'http://spam.test']))->assertOk();
    expect(Lead::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('strips markup from visitor text', function () {
    Queue::fake();
    $this->postJson('/leads', leadPayload(['name' => 'Budi <script>alert(1)</script>', 'message' => '<b>halo</b><img src=x onerror=alert(1)>']))->assertOk();
    $l = Lead::first();
    expect($l->name)->not->toContain('<')->and($l->message)->not->toContain('<');
});

it('rate limits repeated submissions per IP', function () {
    Queue::fake();
    $codes = [];
    foreach (range(1, 7) as $i) {
        $codes[] = $this->postJson('/leads', leadPayload())->status();
    }
    expect($codes)->toContain(429)->and(Lead::count())->toBeLessThanOrEqual(5);
});

it('records a WhatsApp click as a wa_click lead and ignores bots', function () {
    $this->post('/track/wa', ['label' => 'Hero', 'page_url' => 'https://x.test/'])->assertNoContent();
    expect(Lead::where('type', 'wa_click')->count())->toBe(1);
    $this->withHeaders(['User-Agent' => 'Googlebot/2.1'])->post('/track/wa', ['label' => 'Hero'])->assertNoContent();
    expect(Lead::where('type', 'wa_click')->count())->toBe(1);
});

it('emails every active recipient and skips inactive ones', function () {
    Mail::fake();
    foreach (range(1, 3) as $i) {
        LeadRecipient::create(['email' => "sales$i@example.test", 'is_active' => true]);
    }
    LeadRecipient::create(['email' => 'off@example.test', 'is_active' => false]);
    $this->postJson('/leads', leadPayload())->assertOk();
    Mail::assertSent(NewLeadMail::class, 3);
    Mail::assertSent(NewLeadMail::class, fn ($m) => $m->hasTo('sales2@example.test'));
    Mail::assertNotSent(NewLeadMail::class, fn ($m) => $m->hasTo('off@example.test'));
});

it('does not send mail when notifications are switched off', function () {
    Mail::fake();
    LeadRecipient::create(['email' => 'sales@example.test', 'is_active' => true]);
    Settings::set('lead_notify_enabled', false);
    Settings::forget();
    $this->postJson('/leads', leadPayload())->assertOk();
    Mail::assertNothingSent();
    expect(Lead::count())->toBe(1);
});

it('limits notification recipients to 10', function () {
    $this->actingAs(adminUser('admin'));
    foreach (range(1, 10) as $i) {
        $this->post('/admin/recipients', ['email' => "r$i@example.test"])->assertSessionHas('success');
    }
    $this->post('/admin/recipients', ['email' => 'eleventh@example.test'])->assertSessionHas('error');
    expect(LeadRecipient::count())->toBe(10);
});

it('validates and de-duplicates recipient emails', function () {
    $this->actingAs(adminUser('admin'));
    $this->post('/admin/recipients', ['email' => 'not-valid'])->assertSessionHasErrors('email');
    $this->post('/admin/recipients', ['email' => 'Same@Example.test'])->assertSessionHas('success');
    $this->post('/admin/recipients', ['email' => 'same@example.test'])->assertSessionHasErrors('email');
    expect(LeadRecipient::count())->toBe(1)->and(LeadRecipient::first()->email)->toBe('same@example.test');
});

it('moves a lead through the pipeline and keeps history', function () {
    $admin = adminUser('admin');
    $this->actingAs($admin);
    $lead = Lead::create(['type' => 'form', 'name' => 'A', 'phone' => '6281297971563', 'status' => 'baru']);
    $this->put("/admin/leads/{$lead->id}", ['status' => 'negosiasi', 'assigned_to' => $admin->id, 'notes' => 'follow up'])->assertSessionHasNoErrors();
    $this->post("/admin/leads/{$lead->id}/note", ['note' => 'Sudah ditelepon'])->assertSessionHasNoErrors();
    $this->put("/admin/leads/{$lead->id}", ['status' => 'bukan-status'])->assertSessionHasErrors('status');
    expect($lead->fresh()->status)->toBe('negosiasi')->and($lead->events()->pluck('event')->all())->toContain('status', 'assign', 'note');
    $this->get("/admin/leads/{$lead->id}")->assertOk();
});

it('filters leads and exports csv, xlsx and pdf', function () {
    $this->actingAs(adminUser('admin'));
    Lead::create(['type' => 'form', 'name' => 'Cari Saya', 'phone' => '6281297971563', 'status' => 'baru', 'source' => 'organic']);
    Lead::create(['type' => 'form', 'name' => 'Lainnya', 'phone' => '6281297971564', 'status' => 'lunas', 'source' => 'direct']);
    $this->get('/admin/leads?status=lunas')->assertOk()->assertSee('Lainnya')->assertDontSee('Cari Saya');
    $this->get('/admin/leads/export/csv?q=Cari')->assertOk();
    $this->get('/admin/leads/export/xlsx')->assertOk();
    $this->get('/admin/leads/export/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->get('/admin/leads/export/doc')->assertNotFound();
});

it('prevents spreadsheet formula injection in exports', function () {
    Lead::create(['type' => 'form', 'name' => '=HYPERLINK("http://evil.test")', 'phone' => '6281297971563', 'status' => 'baru']);
    $sheet = new App\Exports\LeadsSheet(Lead::with('tour')->get());
    expect($sheet->array()[1][2])->toStartWith("'=");
});
