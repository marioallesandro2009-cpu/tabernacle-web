<?php

require_once __DIR__.'/../Helpers/OutreachHelpers.php';

use App\Models\Outreach\Campaign;
use App\Models\Outreach\CampaignContact;
use App\Models\Outreach\Message;
use App\Models\Outreach\Segment;
use App\Models\Outreach\Template;
use App\Services\Outreach\CampaignScheduler;
use App\Services\Outreach\CampaignService;
use App\Services\Outreach\OutreachMailer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    FakeSmtp::$sent = [];
    FakeSmtp::$fail = null;
    Cache::flush();
    app()->bind(OutreachMailer::class, fn () => new FakeMailer);
    Carbon::setTestNow(Carbon::parse('2026-11-16 09:00', 'Asia/Jakarta')); // a Monday morning in Jakarta
});

afterEach(fn () => Carbon::setTestNow());

it('sends a chosen template from a chosen account to exactly the ticked contacts', function () {
    $this->actingAs(adminUser());
    $seg = Segment::create(['name' => 'Sekolah']);
    $a = oContact('a@sekolah.test', ['segment_id' => $seg->id]);
    $b = oContact('b@sekolah.test', ['segment_id' => $seg->id]);
    $notTicked = oContact('c@sekolah.test', ['segment_id' => $seg->id]);
    $bad = oContact('bad@sekolah.test', ['validation_status' => 'no_mx']);
    $account = oAccount();
    $t = Template::create(['name' => 'Penawaran', 'subject' => 'Halo {organisasi}', 'body' => 'Isi']);

    $check = $this->postJson('/admin/outreach/contacts/send-check', ['ids' => [$a->id, $b->id, $bad->id]])->assertOk()->json();
    expect($check)->toMatchArray(['total' => 3, 'sendable' => 2, 'invalid' => 1, 'busy' => 0]);

    $this->post('/admin/outreach/contacts/send', ['ids' => [$a->id, $b->id, $bad->id], 'account_id' => $account->id, 'template_id' => $t->id])
        ->assertRedirect()->assertSessionHas('success');

    $c = Campaign::where('manual', true)->firstOrFail();
    expect($c->status)->toBe('running')->and($c->accounts->pluck('id')->all())->toBe([$account->id])
        ->and($c->steps()->first()->template_id)->toBe($t->id)
        ->and(CampaignContact::where('campaign_id', $c->id)->where('status', 'queued')->pluck('contact_id')->sort()->values()->all())->toBe([$a->id, $b->id])
        ->and(CampaignContact::where('campaign_id', $c->id)->where('contact_id', $notTicked->id)->exists())->toBeFalse();

    // the emails still go out one at a time, not all at once
    expect(app(CampaignScheduler::class)->tick())->toBe(1)->and(Message::where('campaign_id', $c->id)->count())->toBe(1);
});

it('never writes twice to someone who is already in another campaign', function () {
    $this->actingAs(adminUser());
    $account = oAccount();
    $seg = Segment::create(['name' => 'Gereja']);
    $busy = oContact('busy@gereja.test', ['segment_id' => $seg->id]);
    $free = oContact('free@gereja.test');
    $running = oCampaign([$account], 1, ['segment_id' => $seg->id]);
    app(CampaignService::class)->start($running);
    $t = Template::create(['name' => 'Gereja', 'subject' => 'Halo', 'body' => 'Isi']);

    expect($this->postJson('/admin/outreach/contacts/send-check', ['ids' => [$busy->id, $free->id]])->json('busy'))->toBe(1);
    $this->post('/admin/outreach/contacts/send', ['ids' => [$busy->id, $free->id], 'account_id' => $account->id, 'template_id' => $t->id])->assertSessionHas('success');

    $manual = Campaign::where('manual', true)->firstOrFail();
    expect(CampaignContact::where('campaign_id', $manual->id)->pluck('contact_id')->all())->toBe([$free->id]);

    // nobody left to write to: nothing is created
    $this->post('/admin/outreach/contacts/send', ['ids' => [$busy->id], 'account_id' => $account->id, 'template_id' => $t->id])->assertSessionHas('error');
    expect(Campaign::where('manual', true)->count())->toBe(1);
});

it('keeps a hand-picked campaign to its own people when it is resumed', function () {
    $this->actingAs(adminUser());
    $account = oAccount();
    $picked = oContact('picked@pt.test');
    oContact('other@pt.test');
    $t = Template::create(['name' => 'Korporat', 'subject' => 'Halo', 'body' => 'Isi']);
    $this->post('/admin/outreach/contacts/send', ['ids' => [$picked->id], 'account_id' => $account->id, 'template_id' => $t->id]);
    $c = Campaign::where('manual', true)->firstOrFail();

    app(CampaignService::class)->pause($c);
    app(CampaignService::class)->resume($c->fresh());

    expect($c->fresh()->status)->toBe('running')->and(CampaignContact::where('campaign_id', $c->id)->pluck('contact_id')->all())->toBe([$picked->id]);
});

it('refuses an inactive sender account', function () {
    $this->actingAs(adminUser());
    $account = oAccount(['is_active' => false]);
    $contact = oContact('x@pt.test');
    $t = Template::create(['name' => 'T', 'subject' => 'Halo', 'body' => 'Isi']);

    $this->post('/admin/outreach/contacts/send', ['ids' => [$contact->id], 'account_id' => $account->id, 'template_id' => $t->id])->assertSessionHasErrors('account_id');
    expect(Campaign::count())->toBe(0);
});
