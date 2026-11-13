<?php

require_once __DIR__.'/../Helpers/OutreachHelpers.php';

use App\Jobs\Outreach\SendOutreachMessage;
use App\Models\Outreach\Account;
use App\Models\Outreach\Campaign;
use App\Models\Outreach\CampaignContact;
use App\Models\Outreach\Contact;
use App\Models\Outreach\Message;
use App\Models\Outreach\Segment;
use App\Models\Outreach\Suppression;
use App\Models\Outreach\Template;
use App\Models\Setting;
use App\Services\Outreach\CampaignScheduler;
use App\Services\Outreach\CampaignService;
use App\Services\Outreach\OutreachMailer;
use App\Services\Outreach\TemplateRenderer;
use App\Support\Content;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    FakeSmtp::$sent = [];
    FakeSmtp::$fail = null;
    Cache::flush();
    app()->bind(OutreachMailer::class, fn () => new FakeMailer);
    Carbon::setTestNow(Carbon::parse('2026-11-16 09:00', 'Asia/Jakarta')); // a Monday morning in Jakarta
});

afterEach(fn () => Carbon::setTestNow());

// --- the text of the email ---------------------------------------------------------------

it('fills the variables and adds the footer with company details and the unsubscribe link', function () {
    config(['mail.from.address' => 'hello@tabernaclewaystour.test']);
    Setting::updateOrCreate(['key' => 'company_name'], ['value' => json_encode('PT Jalan Elyon Eskol Indowisata')]);
    Setting::updateOrCreate(['key' => 'address_footer'], ['value' => json_encode("Graha Global BSD\nTangerang Selatan")]);
    Content::bump();
    $contact = oContact('budi@gereja.test', ['name' => 'Pdt. Budi', 'title' => 'Ketua Majelis', 'organization' => 'GKI Sejahtera']);
    [$subject, $body] = app(TemplateRenderer::class)->render("Tur untuk\n{organisasi}", 'Halo {nama}, {jabatan} {organisasi}. Dari {nama_pengirim}. {salah}', $contact, oAccount(['name' => 'Tim Tabernacle']));

    expect($subject)->toBe('Tur untuk GKI Sejahtera')
        ->and($body)->toContain('Halo Pdt. Budi, Ketua Majelis GKI Sejahtera. Dari Tim Tabernacle.')->not->toContain('{salah}')
        ->and($body)->toContain('PT Jalan Elyon Eskol Indowisata')->toContain('Graha Global BSD, Tangerang Selatan')->toContain('hello@tabernaclewaystour.test')
        ->and($body)->toContain('/u/'.$contact->unsub_token);
    // no name: a polite fallback
    expect(app(TemplateRenderer::class)->fill('Halo {nama}', oContact('x@y.test', ['name' => null]), null))->toBe('Halo Bapak/Ibu');
});

// --- starting a campaign -----------------------------------------------------------------

it('starts a campaign: valid contacts wait in line, bad or opted-out ones never get an email', function () {
    $seg = Segment::create(['name' => 'Gereja']);
    $ok = oContact('ok@gereja.test', ['segment_id' => $seg->id]);
    $bad = oContact('bad@gereja.test', ['segment_id' => $seg->id, 'validation_status' => 'no_mx']);
    $out = oContact('out@gereja.test', ['segment_id' => $seg->id, 'suppressed_at' => now(), 'suppressed_reason' => 'unsubscribed']);
    $bounced = oContact('bounced@gereja.test', ['segment_id' => $seg->id, 'suppressed_at' => now(), 'suppressed_reason' => 'bounced']);
    oContact('other@pt.test'); // another segment
    $c = oCampaign([oAccount()], 2, ['segment_id' => $seg->id]);

    app(CampaignService::class)->start($c);

    $status = fn (Contact $x) => CampaignContact::where('campaign_id', $c->id)->where('contact_id', $x->id)->value('status');
    expect($c->fresh()->status)->toBe('running')->and(CampaignContact::where('campaign_id', $c->id)->count())->toBe(4)
        ->and($status($ok))->toBe('queued')->and($status($bad))->toBe('invalid')->and($status($out))->toBe('unsubscribed')->and($status($bounced))->toBe('bounced');
});

it('refuses to start without a template or an active sender', function () {
    $c = Campaign::create(['name' => 'Kosong', 'send_days' => [1], 'window_start' => '08:00', 'window_end' => '17:00']);
    expect(fn () => app(CampaignService::class)->start($c))->toThrow(DomainException::class, 'template');
    $c = oCampaign([oAccount(['is_active' => false])]);
    expect(fn () => app(CampaignService::class)->start($c))->toThrow(DomainException::class, 'akun pengirim');
});

// --- scheduling --------------------------------------------------------------------------

it('only schedules inside the sending hours and on sending days (Jakarta time)', function () {
    Queue::fake();
    $c = oCampaign([oAccount()]);
    oContact('a@gereja.test');
    app(CampaignService::class)->start($c);

    foreach (['2026-11-14 10:00' => 'Saturday', '2026-11-16 07:59' => 'before 08:00', '2026-11-16 17:00' => 'closing time', '2026-11-16 02:30' => '02:30 WIB'] as $at => $why) {
        Carbon::setTestNow(Carbon::parse($at, 'Asia/Jakarta'));
        expect(app(CampaignScheduler::class)->tick())->toBe(0, $why);
    }
    Carbon::setTestNow(Carbon::parse('2026-11-16 08:00', 'Asia/Jakarta'));
    expect(app(CampaignScheduler::class)->tick())->toBe(1);
    Queue::assertPushed(SendOutreachMessage::class, 1);
});

it('gives each sender account one email at a time, spaced 1-4 minutes apart, and spreads people over the accounts', function () {
    Queue::fake();
    [$a, $b] = [oAccount(), oAccount()];
    $c = oCampaign([$a, $b], 1);
    foreach (range(1, 6) as $i) {
        oContact("p$i@gereja.test");
    }
    app(CampaignService::class)->start($c);

    expect(app(CampaignScheduler::class)->tick())->toBe(2)                 // one per account
        ->and(app(CampaignScheduler::class)->tick())->toBe(0);             // both still have a pending email
    expect(Message::distinct()->pluck('account_id')->sort()->values()->all())->toBe([$a->id, $b->id])
        ->and(CampaignContact::whereNotNull('account_id')->count())->toBe(2);

    // once the first two are sent, the next ones are placed a random 60-240 seconds after the previous
    runJobs();
    Carbon::setTestNow(now()->addMinute());
    app(CampaignScheduler::class)->tick();
    foreach ([$a, $b] as $acc) {
        $times = Message::where('account_id', $acc->id)->orderBy('scheduled_at')->pluck('scheduled_at');
        expect($times)->toHaveCount(2)->and($times[1]->diffInSeconds($times[0], true))->toBeGreaterThanOrEqual(60)->toBeLessThanOrEqual(240);
    }
});

it('never goes past the daily limit of an account', function () {
    Queue::fake();
    $a = oAccount(['daily_limit' => 2]);
    $c = oCampaign([$a], 1);
    foreach (range(1, 5) as $i) {
        oContact("d$i@gereja.test");
    }
    app(CampaignService::class)->start($c);
    for ($i = 0; $i < 6; $i++) {
        app(CampaignScheduler::class)->tick();
        runJobs();
        Carbon::setTestNow(now()->addMinutes(5));
    }
    expect(Message::where('status', 'sent')->count())->toBe(2)->and(CampaignContact::where('status', 'queued')->count())->toBe(3);

    // next day, the account may send again
    Carbon::setTestNow(Carbon::parse('2026-11-17 09:00', 'Asia/Jakarta'));
    app(CampaignScheduler::class)->tick();
    runJobs();
    expect(Message::where('status', 'sent')->count())->toBe(3);
});

// --- sending -----------------------------------------------------------------------------

it('sends the first email, then the follow-up on its day, from the same account and in the same thread', function () {
    [$a, $b] = [oAccount(['name' => 'Tim A']), oAccount(['name' => 'Tim B'])];
    $c = oCampaign([$a, $b], 2);
    $contact = oContact('budi@gereja.test', ['name' => 'Pdt. Budi', 'organization' => 'GKI Sejahtera']);
    app(CampaignService::class)->start($c);

    app(CampaignScheduler::class)->tick();
    runJobs();
    $cc = CampaignContact::first();
    expect(FakeSmtp::$sent)->toHaveCount(1);
    /** @var Email $mail */
    $mail = FakeSmtp::$sent[0];
    expect($mail->getSubject())->toBe('Halo Pdt. Budi')->and($mail->getTextBody())->toContain('Salam, Pdt. Budi (Ketua) di GKI Sejahtera.')->toContain('/u/'.$contact->unsub_token)
        ->and($mail->getHeaders()->get('List-Unsubscribe')->getBodyAsString())->toContain('/u/'.$contact->unsub_token)
        ->and($mail->getHeaders()->has('List-Unsubscribe-Post'))->toBeTrue()
        ->and($mail->getHeaders()->has('In-Reply-To'))->toBeFalse()
        ->and($cc->status)->toBe('sent')->and($cc->current_step)->toBe(1)->and($cc->root_message_id)->not->toBeNull()
        ->and($cc->next_send_at->equalTo($cc->first_sent_at->copy()->addDays(4)))->toBeTrue();
    $sender = $cc->account_id;

    // nothing is due yet
    Carbon::setTestNow(Carbon::parse('2026-11-18 10:00', 'Asia/Jakarta'));
    app(CampaignScheduler::class)->tick();
    runJobs();
    expect(FakeSmtp::$sent)->toHaveCount(1);

    // day 4 (Friday): the follow-up goes from the same account, as a reply in the same thread
    Carbon::setTestNow(Carbon::parse('2026-11-20 09:30', 'Asia/Jakarta'));
    app(CampaignScheduler::class)->tick();
    runJobs();
    $follow = FakeSmtp::$sent[1];
    expect(FakeSmtp::$sent)->toHaveCount(2)->and($follow->getSubject())->toBe('Menyusul untuk GKI Sejahtera')
        ->and($follow->getHeaders()->get('In-Reply-To')->getBodyAsString())->toContain($cc->fresh()->root_message_id)
        ->and($cc->fresh()->account_id)->toBe($sender)->and($cc->fresh()->current_step)->toBe(2)->and($cc->fresh()->next_send_at)->toBeNull();

    // the whole sequence is done, so the campaign closes itself
    app(CampaignScheduler::class)->tick();
    expect($c->fresh()->status)->toBe('completed');
});

it('stops the follow-ups when the person replied, bounced or opted out', function () {
    $c = oCampaign([oAccount()], 3);
    $people = [oContact('reply@gereja.test'), oContact('bounce@gereja.test'), oContact('stop@gereja.test'), oContact('go@gereja.test')];
    app(CampaignService::class)->start($c);
    for ($i = 0; $i < 4; $i++) {
        app(CampaignScheduler::class)->tick();
        runJobs();
        Carbon::setTestNow(now()->addMinutes(5));
    }
    expect(FakeSmtp::$sent)->toHaveCount(4);

    CampaignContact::where('contact_id', $people[0]->id)->update(['status' => 'replied', 'next_send_at' => null]);   // what phase 3 does on a reply
    Suppression::add('bounce@gereja.test', 'bounced');
    Suppression::add('stop@gereja.test', 'unsubscribed');

    Carbon::setTestNow(Carbon::parse('2026-11-20 10:00', 'Asia/Jakarta'));
    for ($i = 0; $i < 6; $i++) {
        app(CampaignScheduler::class)->tick();
        runJobs();
        Carbon::setTestNow(now()->addMinutes(5));
    }
    $to = collect(FakeSmtp::$sent)->map(fn (Email $m) => $m->getTo()[0]->getAddress());
    expect($to->filter(fn ($x) => $x === 'go@gereja.test'))->toHaveCount(2)             // got the follow-up
        ->and($to->filter(fn ($x) => in_array($x, ['reply@gereja.test', 'bounce@gereja.test', 'stop@gereja.test'], true)))->toHaveCount(3) // only the first email, no more
        ->and(CampaignContact::where('contact_id', $people[1]->id)->value('status'))->toBe('bounced')
        ->and(CampaignContact::where('contact_id', $people[2]->id)->value('status'))->toBe('unsubscribed');
});

it('re-checks right before sending: a paused campaign or a closed window cancels the scheduled email', function () {
    Queue::fake();
    $c = oCampaign([oAccount()], 1);
    oContact('a@gereja.test');
    app(CampaignService::class)->start($c);
    app(CampaignScheduler::class)->tick();

    app(CampaignService::class)->pause($c);
    runJobs();
    expect(FakeSmtp::$sent)->toBeEmpty()->and(Message::first()->status)->toBe('cancelled')->and(Message::first()->error)->toContain('tidak berjalan');

    app(CampaignService::class)->resume($c->fresh());
    app(CampaignScheduler::class)->tick();
    Carbon::setTestNow(Carbon::parse('2026-11-16 18:00', 'Asia/Jakarta')); // the hours ended meanwhile
    runJobs();
    expect(FakeSmtp::$sent)->toBeEmpty()->and(Message::latest('id')->first()->error)->toContain('jam kirim');
});

it('rests a failing account, gives up on a person after three failures, and treats an unknown mailbox as a bounce', function () {
    $a = oAccount();
    $c = oCampaign([$a], 1);
    $p = oContact('p@gereja.test');
    app(CampaignService::class)->start($c);

    FakeSmtp::$fail = new RuntimeException('Connection refused');
    app(CampaignScheduler::class)->tick();
    runJobs();
    expect(Message::first()->status)->toBe('failed')->and(Cache::has('outreach:cooldown:'.$a->id))->toBeTrue()
        ->and(app(CampaignScheduler::class)->tick())->toBe(0)                    // the account is resting
        ->and(CampaignContact::first()->status)->toBe('queued');
    foreach ([1, 2] as $i) {
        Cache::flush();
        Carbon::setTestNow(now()->addMinutes(20));
        app(CampaignScheduler::class)->tick();
        runJobs();
    }
    expect(Message::where('status', 'failed')->count())->toBe(3)->and(CampaignContact::first()->status)->toBe('failed');

    // a server answering "550 user unknown" means the address is dead
    FakeSmtp::$fail = new RuntimeException('Expected response code "250/251/252" but got code "550", with message "550 5.1.1 User unknown".');
    $dead = Segment::create(['name' => 'Alamat mati']);
    $q = oContact('mati@gereja.test', ['segment_id' => $dead->id]);
    $c2 = oCampaign([$a], 1, ['segment_id' => $dead->id]);
    app(CampaignService::class)->start($c2);
    Cache::flush();
    Carbon::setTestNow(Carbon::parse('2026-11-17 09:00', 'Asia/Jakarta'));
    app(CampaignScheduler::class)->tick();
    runJobs();
    expect(CampaignContact::where('campaign_id', $c2->id)->where('contact_id', $q->id)->value('status'))->toBe('bounced')
        ->and(Suppression::where('email', 'mati@gereja.test')->value('reason'))->toBe('bounced')->and($q->fresh()->suppressed_reason)->toBe('bounced');
});

// --- unsubscribing -----------------------------------------------------------------------

it('unsubscribes only after the confirmation, and stops the campaigns', function () {
    $c = oCampaign([oAccount()], 2);
    $p = oContact('pergi@gereja.test');
    app(CampaignService::class)->start($c);
    app(CampaignScheduler::class)->tick();
    runJobs();

    $this->get('/u/'.$p->unsub_token)->assertOk()->assertSee('Berhenti berlangganan?')->assertSee('p****@gereja.test', false)->assertDontSee('pergi@gereja.test');
    expect($p->fresh()->suppressed_at)->toBeNull();     // opening the link (mail scanners do) changes nothing

    $this->post('/u/'.$p->unsub_token)->assertOk()->assertSee('sudah berhenti berlangganan');
    expect($p->fresh()->suppressed_reason)->toBe('unsubscribed')->and(Suppression::where('email', 'pergi@gereja.test')->exists())->toBeTrue()
        ->and(CampaignContact::first()->status)->toBe('unsubscribed')->and(CampaignContact::first()->next_send_at)->toBeNull();
    $this->get('/u/'.$p->unsub_token)->assertOk()->assertSee('sudah berhenti');
    $this->get('/u/'.str_repeat('z', 40))->assertNotFound();
});

// --- the admin screens -------------------------------------------------------------------

it('creates a campaign from the form and rejects a broken setup', function () {
    $this->actingAs(adminUser());
    $a = oAccount();
    $t1 = Template::create(['name' => 'Satu', 'subject' => 'S', 'body' => 'B']);
    $t2 = Template::create(['name' => 'Dua', 'subject' => 'S2', 'body' => 'B2']);
    $good = ['name' => 'Kampanye Gereja', 'segment_id' => null, 'account_ids' => [$a->id], 'window_start' => '08:00', 'window_end' => '17:00', 'send_days' => [1, 2, 3, 4, 5], 'gap_min' => 1, 'gap_max' => 4,
        'steps' => [['template_id' => $t1->id, 'day_offset' => 0], ['template_id' => $t2->id, 'day_offset' => 4], ['template_id' => null, 'day_offset' => 10]]];

    $this->post('/admin/outreach/campaigns', array_replace($good, ['steps' => [['template_id' => null, 'day_offset' => 0], ['template_id' => $t2->id, 'day_offset' => 4], ['template_id' => null, 'day_offset' => 10]]]))->assertSessionHasErrors('steps');
    $this->post('/admin/outreach/campaigns', array_replace($good, ['steps' => [['template_id' => $t1->id, 'day_offset' => 0], ['template_id' => $t2->id, 'day_offset' => 0], ['template_id' => null, 'day_offset' => 10]]]))->assertSessionHasErrors('steps');
    $this->post('/admin/outreach/campaigns', array_replace($good, ['account_ids' => []]))->assertSessionHasErrors('account_ids');
    $this->post('/admin/outreach/campaigns', array_replace($good, ['window_end' => '07:00']))->assertSessionHasErrors('window_end');
    $this->post('/admin/outreach/campaigns', array_replace($good, ['gap_min' => 5, 'gap_max' => 2]))->assertSessionHasErrors('gap_max');
    expect(Campaign::count())->toBe(0);

    $this->post('/admin/outreach/campaigns', $good)->assertRedirect();
    $c = Campaign::first();
    expect($c->status)->toBe('draft')->and($c->steps)->toHaveCount(2)->and($c->steps[1]->day_offset)->toBe(4)->and($c->gap_max_seconds)->toBe(240)->and($c->accounts)->toHaveCount(1);

    // start, then it cannot be edited until paused
    oContact('x@gereja.test');
    $this->post('/admin/outreach/campaigns/'.$c->id.'/start')->assertRedirect()->assertSessionHas('success');
    expect($c->fresh()->status)->toBe('running');
    $this->get('/admin/outreach/campaigns/'.$c->id.'/edit')->assertRedirect('/admin/outreach/campaigns/'.$c->id);
    $this->delete('/admin/outreach/campaigns/'.$c->id)->assertSessionHas('error');
    $this->get('/admin/outreach/campaigns/'.$c->id)->assertOk()->assertInertia(fn ($p) => $p->component('Admin/Outreach/CampaignShow')->where('stats.contacts', 1)->where('campaign.status', 'running'));
    $this->post('/admin/outreach/campaigns/'.$c->id.'/pause')->assertRedirect();
    $this->get('/admin/outreach/campaigns/'.$c->id.'/edit')->assertOk();
    $this->delete('/admin/outreach/campaigns/'.$c->id)->assertRedirect('/admin/outreach/campaigns');
    expect(Campaign::count())->toBe(0);
});

it('manages templates: unknown variables are rejected, the preview shows the real email, running campaigns protect their templates', function () {
    $this->actingAs(adminUser());
    $seg = Segment::create(['name' => 'Gereja']);
    $this->post('/admin/outreach/templates', ['name' => 'Salah', 'subject' => 'Halo {nam}', 'body' => 'Isi'])->assertSessionHasErrors('body');
    $this->post('/admin/outreach/templates', ['name' => 'Gereja 1', 'segment_id' => $seg->id, 'subject' => 'Tur ziarah {organisasi}', 'body' => "Shalom {nama},\n\n\"Berbahagialah orang yang...\"", 'is_active' => true])->assertRedirect();
    $t = Template::first();
    expect($t->segment_id)->toBe($seg->id);

    $this->postJson('/admin/outreach/templates/preview', ['subject' => $t->subject, 'body' => $t->body])->assertOk()
        ->assertJsonPath('subject', 'Tur ziarah GKI Contoh')->assertJsonPath('to', 'budi@contoh.example')->assertJsonFragment(['body' => "Shalom Pdt. Budi Santoso,\n\n\"Berbahagialah orang yang...\"".app(TemplateRenderer::class)->footer(new Contact(['unsub_token' => str_repeat('x', 40)]))]);

    $c = oCampaign([oAccount()], 1);
    $c->steps()->update(['template_id' => $t->id]);
    oContact('a@gereja.test');
    app(CampaignService::class)->start($c);
    $this->delete('/admin/outreach/templates/'.$t->id)->assertSessionHas('error');
    app(CampaignService::class)->pause($c);
    $this->delete('/admin/outreach/templates/'.$t->id)->assertSessionHas('success');
    expect(Template::whereKey($t->id)->exists())->toBeFalse();
});
