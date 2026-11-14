<?php

require_once __DIR__.'/../Helpers/OutreachHelpers.php';

use App\Exports\CampaignResultsSheet;
use App\Jobs\Outreach\SendReplyAlert;
use App\Mail\OutreachReplyMail;
use App\Models\LeadRecipient;
use App\Models\Outreach\Account;
use App\Models\Outreach\BlockedDomain;
use App\Models\Outreach\Campaign;
use App\Models\Outreach\CampaignContact;
use App\Models\Outreach\Contact;
use App\Models\Outreach\ContactImport;
use App\Models\Outreach\Message;
use App\Models\Outreach\Suppression;
use App\Models\Outreach\Template;
use App\Services\Outreach\CampaignScheduler;
use App\Services\Outreach\CampaignService;
use App\Services\Outreach\ContactImporter;
use App\Services\Outreach\Digest;
use App\Services\Outreach\InboundMail;
use App\Services\Outreach\OutreachMailer;
use App\Services\Outreach\ReplyProcessor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    FakeSmtp::$sent = [];
    FakeSmtp::$fail = null;
    Cache::flush();
    app()->bind(OutreachMailer::class, fn () => new FakeMailer);
    Carbon::setTestNow(Carbon::parse('2026-11-16 09:00', 'Asia/Jakarta')); // Monday
});

afterEach(fn () => Carbon::setTestNow());

/** Ticks the scheduler and sends what it planned, $times times, five minutes apart. */
function sendRounds(int $times): void
{
    for ($i = 0; $i < $times; $i++) {
        app(CampaignScheduler::class)->tick();
        runJobs();
        Carbon::setTestNow(now()->addMinutes(5));
    }
}

function replyFrom(string $email, string $body = 'Kami tertarik, mohon info harganya.'): InboundMail
{
    static $uid = 500;
    $uid++;

    return new InboundMail(uid: $uid, messageId: "r$uid@mail.test", inReplyTo: null, references: [], fromEmail: $email, fromName: null,
        subject: 'Re: Halo', date: Carbon::now(), body: $body, headers: [], dsn: '');
}

// --- warm-up -------------------------------------------------------------------------------

it('grows the daily limit week by week during warm-up, and stops at the maximum', function () {
    $a = oAccount(['daily_limit' => 25, 'warmup_enabled' => true, 'warmup_start' => 10, 'warmup_step' => 5]);
    expect($a->limitToday())->toBe(10)->and($a->warmupWeek())->toBe(1)->and($a->warmingUp())->toBeTrue();

    $a->update(['warmup_started_on' => '2026-11-16']);
    expect($a->fresh()->limitToday())->toBe(10);
    expect($a->fresh()->limitToday(Carbon::parse('2026-11-22 23:00', 'Asia/Jakarta')))->toBe(10);   // day 7: still week 1
    expect($a->fresh()->limitToday(Carbon::parse('2026-11-23 08:00', 'Asia/Jakarta')))->toBe(15);   // week 2
    expect($a->fresh()->limitToday(Carbon::parse('2026-11-30 08:00', 'Asia/Jakarta')))->toBe(20);   // week 3
    expect($a->fresh()->limitToday(Carbon::parse('2026-12-14 08:00', 'Asia/Jakarta')))->toBe(25)    // capped
        ->and($a->fresh()->warmingUp(Carbon::parse('2026-12-14 08:00', 'Asia/Jakarta')))->toBeFalse();

    $a->update(['warmup_enabled' => false]);
    expect($a->fresh()->limitToday())->toBe(25);
});

it('sends only the warm-up amount, and starts week 1 on the day of the first email', function () {
    Queue::fake();
    $a = oAccount(['daily_limit' => 40, 'warmup_enabled' => true, 'warmup_start' => 2, 'warmup_step' => 3]);
    $c = oCampaign([$a], 1);
    foreach (range(1, 8) as $i) {
        oContact("w$i@gereja.test");
    }
    app(CampaignService::class)->start($c);
    sendRounds(6);
    expect(Message::where('status', 'sent')->count())->toBe(2)->and($a->fresh()->warmup_started_on->toDateString())->toBe('2026-11-16');

    // a week later the account may send 2 + 3 = 5
    Carbon::setTestNow(Carbon::parse('2026-11-23 09:00', 'Asia/Jakarta'));
    sendRounds(8);
    expect(Message::where('status', 'sent')->count())->toBe(7);
});

it('saves the warm-up settings from the account form and shows today\'s limit', function () {
    $this->actingAs(adminUser());
    $payload = ['name' => 'Tim', 'email' => 'tim@domain-kita.test', 'smtp_host' => 'smtp.x.test', 'smtp_port' => 465, 'smtp_encryption' => 'ssl',
        'imap_host' => 'imap.x.test', 'imap_port' => 993, 'imap_encryption' => 'ssl', 'username' => 'tim@domain-kita.test', 'password' => 'uji-123',
        'daily_limit' => 40, 'is_active' => true, 'warmup_enabled' => true, 'warmup_start' => 8, 'warmup_step' => 4];
    $this->post('/admin/outreach/accounts', $payload)->assertSessionHasNoErrors();
    $a = Account::firstWhere('email', 'tim@domain-kita.test');
    expect($a->warmup_start)->toBe(8)->and($a->warmup_step)->toBe(4)->and($a->limitToday())->toBe(8);

    $this->get('/admin/outreach/accounts')->assertOk()->assertSee('"limit_today":8', false)->assertSee('"warming_up":true', false);
    $this->post('/admin/outreach/accounts', ['warmup_start' => 0] + $payload + ['email' => 'lain@domain-kita.test'])->assertSessionHasErrors('warmup_start');
});

// --- reply alert ---------------------------------------------------------------------------

it('emails the team the moment a real reply comes in, but not for an out-of-office or a stranger', function () {
    Mail::fake();
    LeadRecipient::create(['email' => 'sales@kita.test', 'name' => 'Sales', 'is_active' => true]);
    LeadRecipient::create(['email' => 'off@kita.test', 'name' => 'Off', 'is_active' => false]);
    $a = oAccount();
    $c = oCampaign([$a], 2);
    $contact = oContact('pdt@gereja.test', ['name' => 'Pdt. Yosua', 'organization' => 'GKJ Sion']);
    app(CampaignService::class)->start($c);
    sendRounds(1);

    app(ReplyProcessor::class)->process($a, replyFrom('pdt@gereja.test'));
    Mail::assertSent(OutreachReplyMail::class, function (OutreachReplyMail $m) {
        $html = $m->render();

        return $m->hasTo('sales@kita.test') && str_contains($m->envelope()->subject, 'Pdt. Yosua (GKJ Sion)')
            && str_contains($html, 'mohon info harganya') && str_contains($html, 'Tur Turki') && str_contains($html, '/admin/outreach/replies');
    });
    Mail::assertSent(OutreachReplyMail::class, 1);

    // an out-of-office and a message from someone we never wrote to: no alert
    Mail::fake();
    app(ReplyProcessor::class)->process($a, new InboundMail(uid: 9001, messageId: 'ooo@mail.test', inReplyTo: null, references: [], fromEmail: 'pdt@gereja.test', fromName: null,
        subject: 'Automatic reply: Halo', date: now(), body: 'Saya sedang cuti.', headers: ['auto-submitted' => 'auto-replied'], dsn: ''));
    app(ReplyProcessor::class)->process($a, replyFrom('asing@lain.test'));
    Mail::assertNothingSent();
});

it('falls back to the weekly-summary recipients when no lead recipient is set', function () {
    adminUser('super_admin', ['email' => 'owner@kita.test']);
    expect(SendReplyAlert::recipients(app(Digest::class)))->toBe(['owner@kita.test']);
    LeadRecipient::create(['email' => 'Sales@Kita.test', 'name' => 'Sales', 'is_active' => true]);
    expect(SendReplyAlert::recipients(app(Digest::class)))->toBe(['sales@kita.test']);
});

// --- blocked domains ------------------------------------------------------------------------

it('normalises and checks domains, subdomains included', function () {
    expect(BlockedDomain::normalize(' Budi@Mail.Kompetitor.COM '))->toBe('mail.kompetitor.com')
        ->and(BlockedDomain::normalize('https://www.kompetitor.com/tentang'))->toBe('kompetitor.com')
        ->and(BlockedDomain::normalize('@kompetitor.com'))->toBe('kompetitor.com')
        ->and(BlockedDomain::isValidDomain('kompetitor.com'))->toBeTrue()->and(BlockedDomain::isValidDomain('bukan domain'))->toBeFalse();

    BlockedDomain::create(['domain' => 'kompetitor.com', 'created_at' => now()]);
    expect(BlockedDomain::blocks('a@kompetitor.com'))->toBeTrue()->and(BlockedDomain::blocks('a@mail.kompetitor.com'))->toBeTrue()
        ->and(BlockedDomain::blocks('a@bukankompetitor.com'))->toBeFalse()->and(BlockedDomain::blocks(null))->toBeFalse();
});

it('blocks a domain from the admin: its contacts stop at once and cannot be imported as sendable', function () {
    Queue::fake();
    $this->actingAs(adminUser());
    $a = oAccount();
    $c = oCampaign([$a], 2);
    oContact('satu@kompetitor.com');
    oContact('dua@cabang.kompetitor.com');
    $ok = oContact('jemaat@gereja.test');
    app(CampaignService::class)->start($c);
    sendRounds(1); // one of them already got the first email

    $this->post('/admin/outreach/blocklist/domains', ['domain' => 'Info@Kompetitor.com', 'note' => 'Kompetitor'])->assertSessionHasNoErrors();
    expect(BlockedDomain::pluck('domain')->all())->toBe(['kompetitor.com'])
        ->and(Contact::where('suppressed_reason', BlockedDomain::REASON)->count())->toBe(2)
        ->and($ok->fresh()->suppressed_at)->toBeNull()
        ->and(CampaignContact::whereHas('contact', fn ($q) => $q->where('email', 'like', '%kompetitor.com'))->pluck('status')->unique()->all())->toBe(['invalid']);

    $this->post('/admin/outreach/blocklist/domains', ['domain' => 'kompetitor.com'])->assertSessionHasErrors('domain');
    $this->post('/admin/outreach/blocklist/domains', ['domain' => 'bukan domain'])->assertSessionHasErrors('domain');

    // the importer and the single-contact form both respect it
    $import = ContactImport::create(['filename' => 'x.csv', 'path' => 'x', 'status' => 'processing']);
    $seen = [];
    (fn () => $this->chunk($import, ['email' => 0], [['baru@kompetitor.com'], ['baru@gereja.test']], $seen))->call(app(ContactImporter::class));
    expect(Contact::firstWhere('email', 'baru@kompetitor.com')->suppressed_reason)->toBe(BlockedDomain::REASON)
        ->and(Contact::firstWhere('email', 'baru@gereja.test')->suppressed_at)->toBeNull();
    $this->post('/admin/outreach/contacts', ['email' => 'form@kompetitor.com'])->assertSessionHasNoErrors();
    expect(Contact::firstWhere('email', 'form@kompetitor.com')->suppressed_reason)->toBe(BlockedDomain::REASON);

    $this->get('/admin/outreach/blocklist')->assertOk()->assertSee('kompetitor.com');

    // unblocking releases them for new campaigns, but not someone who opted out
    Suppression::add('satu@kompetitor.com', 'unsubscribed');
    $this->delete('/admin/outreach/blocklist/domains/'.BlockedDomain::first()->id)->assertRedirect();
    expect(Contact::firstWhere('email', 'dua@cabang.kompetitor.com')->suppressed_at)->toBeNull()
        ->and(Contact::firstWhere('email', 'satu@kompetitor.com')->suppressed_at)->not->toBeNull();
});

it('re-checks the domain right before sending', function () {
    Queue::fake();
    $a = oAccount();
    $c = oCampaign([$a], 1);
    oContact('x@kompetitor.com');
    app(CampaignService::class)->start($c);
    app(CampaignScheduler::class)->tick();
    BlockedDomain::create(['domain' => 'kompetitor.com', 'created_at' => now()]); // added behind the system's back
    runJobs();
    expect(FakeSmtp::$sent)->toBeEmpty()->and(Message::first()->status)->toBe('cancelled')->and(Message::first()->error)->toContain('daftar blokir');
});

it('adds and releases single addresses; opt-outs and bounces cannot be released', function () {
    $this->actingAs(adminUser());
    $c = oContact('manual@gereja.test');
    $this->post('/admin/outreach/blocklist/emails', ['emails' => "Manual@Gereja.test, bukan-email\nlain@gereja.test"])->assertSessionHasNoErrors();
    expect(Suppression::pluck('email')->sort()->values()->all())->toBe(['lain@gereja.test', 'manual@gereja.test'])->and($c->fresh()->suppressed_reason)->toBe('manual');

    $this->delete('/admin/outreach/blocklist/emails/'.Suppression::firstWhere('email', 'manual@gereja.test')->id)->assertRedirect();
    expect($c->fresh()->suppressed_at)->toBeNull()->and(Suppression::where('email', 'manual@gereja.test')->exists())->toBeFalse();

    Suppression::add('pergi@gereja.test', 'unsubscribed');
    $this->delete('/admin/outreach/blocklist/emails/'.Suppression::firstWhere('email', 'pergi@gereja.test')->id)->assertSessionHas('error');
    expect(Suppression::where('email', 'pergi@gereja.test')->exists())->toBeTrue();

    $this->get('/admin/outreach/blocklist?reason=unsubscribed')->assertOk()->assertSee('pergi@gereja.test')->assertDontSee('lain@gereja.test');
});

it('keeps the block list for Super Admin and Admin only', function () {
    $this->actingAs(adminUser('editor', ['email' => 'ed@kita.test']))->get('/admin/outreach/blocklist')->assertForbidden();
});

// --- A/B subject test ----------------------------------------------------------------------

it('sends subject A to half the people and subject B to the other half, and counts replies per subject', function () {
    Queue::fake();
    $a = oAccount(['daily_limit' => 100]);
    $c = oCampaign([$a], 2);
    $c->update(['subject_b' => 'Pertanyaan singkat untuk {organisasi}']);
    foreach (range(1, 4) as $i) {
        oContact("ab$i@gereja.test", ['organization' => "Gereja $i"]);
    }
    app(CampaignService::class)->start($c);
    sendRounds(8);

    $subjects = collect(FakeSmtp::$sent)->map(fn ($m) => $m->getSubject());
    expect($subjects)->toHaveCount(4)
        ->and($subjects->filter(fn ($s) => str_starts_with($s, 'Halo '))->count())->toBe(2)
        ->and($subjects->filter(fn ($s) => str_starts_with($s, 'Pertanyaan singkat untuk Gereja'))->count())->toBe(2)
        ->and(CampaignContact::pluck('variant')->sort()->values()->all())->toBe(['A', 'A', 'B', 'B']);

    CampaignContact::where('variant', 'B')->first()->update(['status' => 'replied']);
    $v = collect(app(CampaignService::class)->variants($c->fresh()))->keyBy('variant');
    expect($v['A'])->toMatchArray(['subject' => 'Halo {nama}', 'started' => 2, 'replied' => 0, 'rate' => 0.0, 'best' => false])
        ->and($v['B'])->toMatchArray(['subject' => 'Pertanyaan singkat untuk {organisasi}', 'started' => 2, 'replied' => 1, 'rate' => 50.0, 'best' => false]); // too few to name a winner

    // the follow-up keeps the template subject for everyone
    Carbon::setTestNow(Carbon::parse('2026-11-20 13:00', 'Asia/Jakarta'));
    FakeSmtp::$sent = [];
    sendRounds(10);
    $follow = collect(FakeSmtp::$sent)->map(fn ($m) => $m->getSubject());
    expect($follow)->toHaveCount(3)->and($follow->every(fn ($s) => str_starts_with($s, 'Menyusul untuk Gereja')))->toBeTrue();
});

it('leaves A/B out when there is no second subject, and checks its variables in the form', function () {
    $a = oAccount();
    $c = oCampaign([$a], 1);
    expect(app(CampaignService::class)->variants($c))->toBe([]);

    $this->actingAs(adminUser());
    $t = Template::first();
    $payload = ['name' => 'AB', 'account_ids' => [$a->id], 'window_start' => '08:00', 'window_end' => '17:00', 'send_days' => [1, 2], 'gap_min' => 1, 'gap_max' => 4,
        'steps' => [['template_id' => $t->id, 'day_offset' => 0], ['template_id' => null, 'day_offset' => 4], ['template_id' => null, 'day_offset' => 10]]];
    $this->post('/admin/outreach/campaigns', $payload + ['subject_b' => 'Halo {namaa}'])->assertSessionHasErrors('subject_b');
    $this->post('/admin/outreach/campaigns', $payload + ['subject_b' => '  Untuk {nama}  '])->assertSessionHasNoErrors();
    $new = Campaign::latest('id')->first();
    expect($new->subject_b)->toBe('Untuk {nama}');
    $this->get('/admin/outreach/campaigns/'.$new->id.'/edit')->assertOk()->assertSee('Untuk {nama}');
    $this->get('/admin/outreach/campaigns/'.$new->id)->assertOk()->assertSee('"variants":[{"variant":"A"', false);
});

// --- Excel export --------------------------------------------------------------------------

it('exports one row per person with what was sent and how they answered', function () {
    Queue::fake();
    $a = oAccount();
    $c = oCampaign([$a], 2);
    oContact('jawab@gereja.test', ['name' => 'Pdt. Jawab', 'organization' => '=HYPERLINK("x")']);
    oContact('diam@gereja.test', ['name' => 'Bu Diam']);
    oContact('rusak', ['validation_status' => 'invalid_format']);
    app(CampaignService::class)->start($c);
    sendRounds(3);
    $cc = CampaignContact::whereHas('contact', fn ($q) => $q->where('email', 'jawab@gereja.test'))->first();
    $reply = app(ReplyProcessor::class)->process($a, replyFrom('jawab@gereja.test', 'Boleh, kirim proposalnya.'));
    $reply->update(['label' => 'interested']);

    $rows = (new CampaignResultsSheet($c))->array();
    expect($rows[0])->toContain('Nama')->toContain('Status')->toContain('Isi balasan')->not->toContain('Versi subjek')->and($rows)->toHaveCount(4);
    $byEmail = collect($rows)->slice(1)->keyBy(3);
    expect($byEmail['jawab@gereja.test'])->toContain('Pdt. Jawab')->toContain('Dibalas')->toContain('Tertarik')->toContain('Boleh, kirim proposalnya.')->toContain("'=HYPERLINK(\"x\")")
        ->and($byEmail['jawab@gereja.test'][6])->toBe(1)
        ->and($byEmail['diam@gereja.test'])->toContain('Terkirim')
        ->and($byEmail['rusak'])->toContain('Tidak valid');

    $this->actingAs(adminUser());
    $res = $this->get('/admin/outreach/campaigns/'.$c->id.'/export')->assertOk();
    expect($res->headers->get('content-disposition'))->toContain('kampanye-tur-turki-')->toContain('.xlsx');
});
