<?php

require_once __DIR__.'/../Helpers/OutreachHelpers.php';

use App\Mail\ForwardedReplyMail;
use App\Mail\OutreachReplyMail;
use App\Mail\RelayConfirmationMail;
use App\Models\LeadRecipient;
use App\Models\Outreach\Account;
use App\Models\Outreach\CampaignContact;
use App\Models\Outreach\Message;
use App\Models\Outreach\Segment;
use App\Services\Outreach\CampaignScheduler;
use App\Services\Outreach\CampaignService;
use App\Services\Outreach\InboundMail;
use App\Services\Outreach\OutreachMailer;
use App\Services\Outreach\RelayProcessor;
use App\Services\Outreach\ReplyProcessor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    FakeSmtp::$sent = [];
    FakeSmtp::$fail = null;
    Cache::flush();
    app()->bind(OutreachMailer::class, fn () => new FakeMailer);
    Carbon::setTestNow(Carbon::parse('2026-11-16 09:00', 'Asia/Jakarta'));
    Mail::fake();
});

afterEach(fn () => Carbon::setTestNow());

const GOOD_AUTH = 'p3plibsmtp01.prod.phx3.secureserver.net; spf=pass smtp.mailfrom=gmail.test; dkim=pass header.d=gmail.test; dmarc=pass header.from=gmail.test';

/** A prospect who got the first campaign email from $account. @return array{0: CampaignContact, 1: Account} */
function prospect(array $account = []): array
{
    $a = oAccount($account + ['email' => 'tim@tabernacle.test', 'name' => 'Tim Tabernacle']);
    $c = oCampaign([$a], 2);
    $seg = Segment::create(['name' => 'Gereja']);
    oContact('pdt@gereja.test', ['name' => 'Pdt. Yosua', 'organization' => 'GKJ Sion', 'segment_id' => $seg->id]);
    app(CampaignService::class)->start($c);
    app(CampaignScheduler::class)->tick();
    runJobs();
    FakeSmtp::$sent = [];

    return [CampaignContact::first(), $a];
}

function prospectReply(string $body = 'Kami tertarik, boleh minta proposal?', ?string $inReplyTo = null): InboundMail
{
    static $n = 0;
    $n++;

    return new InboundMail(uid: 700 + $n, messageId: "balas$n@gereja.test", inReplyTo: $inReplyTo ?? CampaignContact::first()->root_message_id, references: [],
        fromEmail: 'pdt@gereja.test', fromName: 'Pdt. Yosua', subject: 'Re: Halo Pdt. Yosua', date: now(), body: $body."\n\nOn Mon, Tim wrote:\n> Salam");
}

function relayMail(string $subject, string $body, string $from = 'boss@gmail.test', string $auth = GOOD_AUTH, bool $attachments = false): InboundMail
{
    static $n = 0;
    $n++;

    return new InboundMail(uid: 900 + $n, messageId: "relay$n@gmail.test", inReplyTo: null, references: [], fromEmail: $from, fromName: 'Boss',
        subject: $subject, date: now(), body: $body, headers: ['authentication-results' => $auth], hasAttachments: $attachments);
}

// --- 1a: forwarding -------------------------------------------------------------------------

it('forwards a real reply to the personal addresses, with the conversation code in the subject', function () {
    [$cc, $a] = prospect(['forward_enabled' => true, 'forward_to' => "boss@gmail.test\nwakil@gmail.test"]);
    LeadRecipient::create(['email' => 'boss@gmail.test', 'name' => 'Boss', 'is_active' => true]);
    LeadRecipient::create(['email' => 'sales@kita.test', 'name' => 'Sales', 'is_active' => true]);

    $reply = app(ReplyProcessor::class)->process($a, prospectReply());
    $token = $cc->fresh()->thread_token;
    expect($token)->toMatch('/^[A-HJKMNP-Z2-9]{10}$/');

    Mail::assertSent(ForwardedReplyMail::class, 2);
    Mail::assertSent(ForwardedReplyMail::class, function (ForwardedReplyMail $m) use ($token, $cc) {
        $html = $m->render();

        return $m->hasTo('boss@gmail.test') && $m->envelope()->subject === "[Balasan] Re: Halo Pdt. Yosua – Pdt. Yosua [#$token]"
            && $m->envelope()->replyTo === [] // no relay: answering it goes to the personal mailbox only
            && str_contains($html, 'Kami tertarik, boleh minta proposal?') && ! str_contains($html, '&gt; Salam')
            && str_contains($html, 'GKJ Sion') && str_contains($html, 'Tur Turki') && str_contains($html, 'Gereja')
            && str_contains($html, 'EMAIL TERAKHIR KITA') && str_contains($html, 'Salam, Pdt. Yosua')
            && str_contains($html, '/admin/outreach/threads/'.$cc->id);
    });
    // the team alert goes only to whoever does not already get the forwarded copy
    Mail::assertSent(OutreachReplyMail::class, fn ($m) => $m->hasTo('sales@kita.test'));
    Mail::assertNotSent(OutreachReplyMail::class, fn ($m) => $m->hasTo('boss@gmail.test'));

    // a second reply in the same conversation keeps the same code
    app(ReplyProcessor::class)->process($a, prospectReply('Satu lagi.', $reply->message_id));
    expect($cc->fresh()->thread_token)->toBe($token);
});

it('does not forward bounces, out-of-office mail, or when forwarding is off', function () {
    [, $a] = prospect(['forward_enabled' => false, 'forward_to' => 'boss@gmail.test']);
    app(ReplyProcessor::class)->process($a, prospectReply());
    Mail::assertNotSent(ForwardedReplyMail::class);

    $a->update(['forward_enabled' => true]);
    app(ReplyProcessor::class)->process($a, new InboundMail(uid: 1, messageId: 'ooo@x', inReplyTo: null, references: [], fromEmail: 'pdt@gereja.test', fromName: null,
        subject: 'Automatic reply: cuti', date: now(), body: 'Sedang cuti', headers: ['auto-submitted' => 'auto-replied']));
    Mail::assertNotSent(ForwardedReplyMail::class);
});

// --- 1b: replying from the website ------------------------------------------------------------

it('answers from the website in the same thread, without the unsubscribe footer, and keeps a draft while typing', function () {
    [$cc, $a] = prospect();
    $in = app(ReplyProcessor::class)->process($a, prospectReply());
    $admin = adminUser();
    $this->actingAs($admin);

    $d = $this->putJson("/admin/outreach/threads/{$cc->id}/draft", ['body' => 'Terima kasih Pak, prop'])->assertOk()->json();
    $this->putJson("/admin/outreach/threads/{$cc->id}/draft", ['body' => 'Terima kasih Pak, proposal kami lampirkan di tautan ini.', 'draft_id' => $d['id']])->assertOk();
    expect(Message::where('kind', Message::MANUAL)->count())->toBe(1)->and(Message::find($d['id'])->reply_status)->toBe('draft');
    $this->get("/admin/outreach/threads/{$cc->id}")->assertOk()->assertSee('proposal kami lampirkan', false);

    $this->post("/admin/outreach/threads/{$cc->id}/send", ['body' => "Terima kasih Pak.\nProposal kami kirim besok.", 'draft_id' => $d['id']])->assertSessionHas('success');
    expect(FakeSmtp::$sent)->toHaveCount(1);
    /** @var Email $mail */
    $mail = FakeSmtp::$sent[0];
    $h = $mail->getHeaders();
    expect($mail->getSubject())->toBe('Re: Halo Pdt. Yosua')->and($mail->getTextBody())->toBe("Terima kasih Pak.\nProposal kami kirim besok.")
        ->and($mail->getFrom()[0]->getAddress())->toBe('tim@tabernacle.test')
        ->and($h->get('In-Reply-To')->getBodyAsString())->toBe('<'.$in->message_id.'>')
        ->and($h->get('References')->getBodyAsString())->toContain($cc->root_message_id)->toContain($in->message_id)
        ->and($h->has('List-Unsubscribe'))->toBeFalse()->and($h->has('Reply-To'))->toBeFalse();

    $m = Message::find($d['id']);
    expect($m->reply_status)->toBe('sent')->and($m->user_id)->toBe($admin->id)->and($m->source)->toBe('website')->and($m->message_id)->not->toBeNull()
        ->and(collect($m->status_log)->pluck('status')->all())->toBe(['draft', 'queued', 'sent']);
    // manual replies never eat the campaign's daily limit
    expect(app(CampaignScheduler::class)->sentToday($a))->toBe(1);

    // the prospect answers our manual reply: it is filed under the same conversation
    $next = app(ReplyProcessor::class)->process($a, prospectReply('Baik, ditunggu.', $m->message_id));
    expect($next->campaign_contact_id)->toBe($cc->id);
});

it('keeps a failed send with its reason and sends it again on request', function () {
    [$cc, $a] = prospect();
    $this->actingAs(adminUser());
    FakeSmtp::$fail = new RuntimeException('Connection could not be established with host "smtp"');
    $this->post("/admin/outreach/threads/{$cc->id}/send", ['body' => 'Halo lagi.']);
    $m = Message::where('kind', Message::MANUAL)->first();
    expect($m->reply_status)->toBe('failed')->and($m->error)->toContain('Connection could not be established');

    FakeSmtp::$fail = null;
    $this->post("/admin/outreach/manual-replies/{$m->id}/retry")->assertSessionHas('success');
    expect($m->fresh()->reply_status)->toBe('sent')->and(FakeSmtp::$sent)->toHaveCount(1)
        ->and(collect($m->fresh()->status_log)->pluck('status')->all())->toBe(['draft', 'queued', 'failed', 'queued', 'sent']);
    $this->post("/admin/outreach/manual-replies/{$m->id}/retry")->assertStatus(422); // only failed replies can be retried
});

it('marks our manual reply as bounced when its bounce report comes back', function () {
    [$cc, $a] = prospect();
    $this->actingAs(adminUser());
    $this->post("/admin/outreach/threads/{$cc->id}/send", ['body' => 'Halo lagi.']);
    $m = Message::where('kind', Message::MANUAL)->first();
    app(ReplyProcessor::class)->process($a, new InboundMail(uid: 50, messageId: 'dsn@mx', inReplyTo: null, references: [], fromEmail: 'mailer-daemon@mx.gereja.test', fromName: null,
        subject: 'Undelivered Mail Returned to Sender', date: now(), body: 'Message-ID: <'.$m->message_id.'>', headers: ['content-type' => 'multipart/report; report-type=delivery-status'],
        dsn: "Final-Recipient: rfc822; pdt@gereja.test\nAction: failed\nStatus: 5.1.1\nMessage-ID: <{$m->message_id}>"));
    expect($m->fresh()->reply_status)->toBe('bounced');
});

// --- 1c / 1e: answering from a personal mailbox --------------------------------------------

it('passes an answer from a personal mailbox straight to the prospect in "direct" mode', function () {
    [$cc, $a] = prospect(['forward_enabled' => true, 'forward_to' => 'boss@gmail.test', 'relay_enabled' => true, 'relay_senders' => 'Boss@Gmail.test', 'relay_mode' => 'direct']);
    $in = app(ReplyProcessor::class)->process($a, prospectReply());
    $token = $cc->fresh()->thread_token;
    Mail::assertSent(ForwardedReplyMail::class, fn ($m) => $m->envelope()->replyTo[0]->address === 'tim@tabernacle.test' && str_contains($m->render(), 'membalas email ini'));
    $replies = Message::where('direction', 'in')->count();

    $body = "Baik Pak, proposal saya kirim besok pagi.\n\nSalam\n--\nBoss\nDirektur\n\nSent from my iPhone\n\nPada Sen, 16 Nov 2026 pukul 09.10, Tabernacle <contact@x> menulis:\n> [Balasan] ...";
    $m = app(ReplyProcessor::class)->process($a, relayMail("Re: [Balasan] Re: Halo Pdt. Yosua – Pdt. Yosua [#$token]", $body));

    expect($m->kind)->toBe(Message::MANUAL)->and($m->direction)->toBe('out')->and($m->source)->toBe('email')->and($m->relay_from)->toBe('boss@gmail.test')
        ->and($m->fresh()->reply_status)->toBe('sent')->and($m->body)->toBe("Baik Pak, proposal saya kirim besok pagi.\n\nSalam")
        ->and(collect($m->fresh()->status_log)->pluck('status')->all())->toBe(['received', 'queued', 'sent'])
        ->and(Message::where('direction', 'in')->count())->toBe($replies); // never filed as a prospect reply
    expect(FakeSmtp::$sent)->toHaveCount(1)->and(FakeSmtp::$sent[0]->getTo()[0]->getAddress())->toBe('pdt@gereja.test')
        ->and(FakeSmtp::$sent[0]->getHeaders()->get('In-Reply-To')->getBodyAsString())->toBe('<'.$in->message_id.'>')
        ->and(FakeSmtp::$sent[0]->getTextBody())->not->toContain('Sent from my iPhone')->not->toContain('[Balasan]');
    Mail::assertSent(RelayConfirmationMail::class, fn ($c) => $c->hasTo('boss@gmail.test') && str_starts_with($c->envelope()->subject, '[Terkirim]'));

    // the same relay email read twice is handled once
    expect(app(ReplyProcessor::class)->process($a, relayMail("Re: [#$token]", 'x')))->not->toBeNull(); // a new one
    $same = Message::where('kind', Message::MANUAL)->first();
    expect(app(RelayProcessor::class)->process($a, new InboundMail(uid: 1, messageId: $same->inbound_message_id, inReplyTo: null, references: [], fromEmail: 'boss@gmail.test', fromName: null, subject: "[#$token]", date: now(), body: 'lagi', headers: ['authentication-results' => GOOD_AUTH])))->toBeNull();
});

it('keeps an answer from a personal mailbox as a draft in "review" mode, to be checked and sent from the website', function () {
    [$cc, $a] = prospect(['forward_enabled' => true, 'forward_to' => 'boss@gmail.test', 'relay_enabled' => true, 'relay_senders' => 'boss@gmail.test', 'relay_mode' => 'review']);
    app(ReplyProcessor::class)->process($a, prospectReply());
    $token = $cc->fresh()->thread_token;

    $m = app(ReplyProcessor::class)->process($a, relayMail("Re: [#$token]", "Draf jawaban dari HP.\n\nDikirim dari Yahoo Mail di Android", attachments: true));
    expect($m->fresh()->reply_status)->toBe('draft')->and(FakeSmtp::$sent)->toBeEmpty()->and($m->body)->toBe('Draf jawaban dari HP.');
    Mail::assertSent(RelayConfirmationMail::class, fn ($c) => str_starts_with($c->envelope()->subject, '[Disimpan sebagai draf]') && str_contains($c->render(), 'Lampiran di email Anda tidak ikut dikirim'));

    $this->actingAs(adminUser());
    $this->get('/admin/outreach/replies?tab=ours&status=draft')->assertOk()->assertSee('Draf jawaban dari HP.', false);
    $this->post("/admin/outreach/manual-replies/{$m->id}/send", ['body' => 'Draf jawaban dari HP, sudah dirapikan.'])->assertSessionHas('success');
    expect($m->fresh()->reply_status)->toBe('sent')->and(FakeSmtp::$sent[0]->getTextBody())->toBe('Draf jawaban dari HP, sudah dirapikan.');
});

it('rejects a relay that fails any of the checks, and only answers senders that are on the list', function (string $case) {
    [$cc, $a] = prospect(['forward_enabled' => true, 'forward_to' => 'boss@gmail.test', 'relay_enabled' => true, 'relay_senders' => 'boss@gmail.test', 'relay_mode' => 'direct']);
    app(ReplyProcessor::class)->process($a, prospectReply());
    $token = $cc->fresh()->thread_token;
    [$mail, $reason, $confirm] = match ($case) {
        'stranger' => [relayMail("Re: [#$token]", 'Saya bukan tim.', from: 'orang@lain.test', auth: 'mx; spf=pass smtp.mailfrom=lain.test'), 'tidak ada di daftar', false],
        'no token' => [relayMail('Re: halo', 'Tanpa kode.'), 'tidak ada di subjek', true],
        'unknown token' => [relayMail('Re: [#ABCDEFGHJK]', 'Kode salah.'), 'tidak dikenal', true],
        'spf fail' => [relayMail("Re: [#$token]", 'Palsu.', auth: 'mx; spf=fail smtp.mailfrom=gmail.test; dkim=none'), 'SPF/DKIM', true],
        'other domain signed' => [relayMail("Re: [#$token]", 'Palsu.', auth: 'mx; spf=softfail smtp.mailfrom=gmail.test; dkim=pass header.d=penipu.test'), 'SPF/DKIM', true],
        'no header' => [relayMail("Re: [#$token]", 'Tanpa header.', auth: ''), 'SPF/DKIM', true],
        'empty body' => [relayMail("Re: [#$token]", "\nSent from my iPhone"), 'kosong', true],
        'relay off' => [relayMail("Re: [#$token]", 'Relay dimatikan.'), 'tidak diaktifkan', true],
    };
    if ($case === 'relay off') {
        $a->update(['relay_enabled' => false]);
    }
    $m = app(ReplyProcessor::class)->process($a, $mail);

    expect($m->fresh()->reply_status)->toBe('rejected')->and($m->error)->toContain($reason)->and(FakeSmtp::$sent)->toBeEmpty();
    $confirm ? Mail::assertSent(RelayConfirmationMail::class, fn ($c) => str_starts_with($c->envelope()->subject, '[Ditolak]') && str_contains($c->render(), $reason))
        : Mail::assertNotSent(RelayConfirmationMail::class);
})->with(['stranger', 'no token', 'unknown token', 'spf fail', 'other domain signed', 'no header', 'empty body', 'relay off']);

it('reads Authentication-Results for the sender domain, by SPF or by DKIM', function () {
    $r = app(RelayProcessor::class);
    $mail = fn (string $auth) => new InboundMail(uid: 1, messageId: null, inReplyTo: null, references: [], fromEmail: 'boss@gmail.test', fromName: null, subject: '', date: null, body: '', headers: ['authentication-results' => $auth]);
    expect($r->authenticated($mail('mx.secureserver.net; spf=pass (domain of boss@gmail.test) smtp.mailfrom=boss@gmail.test'), 'boss@gmail.test'))->toBeTrue()
        ->and($r->authenticated($mail('mx; spf=neutral smtp.mailfrom=gmail.test; dkim=pass (2048-bit key) header.d=gmail.test header.s=20230601'), 'boss@gmail.test'))->toBeTrue()
        ->and($r->authenticated($mail('mx; dkim=pass header.i=@gmail.test'), 'boss@gmail.test'))->toBeTrue()
        ->and($r->authenticated($mail('mx; spf=pass smtp.mailfrom=notgmail.test'), 'boss@gmail.test'))->toBeFalse()
        ->and($r->authenticated($mail('mx; dkim=fail header.d=gmail.test'), 'boss@gmail.test'))->toBeFalse();
});

// --- 1d: settings, pages --------------------------------------------------------------------

it('saves forwarding and relay settings from the account form and refuses combinations that cannot work', function () {
    $this->actingAs(adminUser());
    $base = ['name' => 'Tim', 'email' => 'tim@tabernacle.test', 'smtp_host' => 'smtpout.secureserver.net', 'smtp_port' => 465, 'smtp_encryption' => 'ssl',
        'imap_host' => 'imap.secureserver.net', 'imap_port' => 993, 'imap_encryption' => 'ssl', 'username' => 'tim@tabernacle.test', 'password' => 'uji-123', 'daily_limit' => 30, 'is_active' => true];

    $this->post('/admin/outreach/accounts', $base + ['relay_enabled' => true, 'relay_senders' => 'boss@gmail.test'])->assertSessionHasErrors('relay_enabled');
    $this->post('/admin/outreach/accounts', $base + ['forward_enabled' => true, 'forward_to' => ''])->assertSessionHasErrors('forward_to');
    $this->post('/admin/outreach/accounts', $base + ['forward_enabled' => true, 'forward_to' => 'bukan-email'])->assertSessionHasErrors('forward_to');
    $this->post('/admin/outreach/accounts', $base + ['forward_enabled' => true, 'forward_to' => 'TIM@tabernacle.test'])->assertSessionHasErrors('forward_to');
    $this->post('/admin/outreach/accounts', $base + ['forward_enabled' => true, 'forward_to' => 'boss@gmail.test', 'relay_enabled' => true, 'relay_senders' => ''])->assertSessionHasErrors('relay_senders');

    $this->post('/admin/outreach/accounts', $base + ['forward_enabled' => true, 'forward_to' => 'Boss@Gmail.test, wakil@gmail.test', 'relay_enabled' => true, 'relay_senders' => 'boss@gmail.test', 'relay_mode' => 'direct'])->assertSessionHasNoErrors();
    $a = Account::firstWhere('email', 'tim@tabernacle.test');
    expect($a->forwardList())->toBe(['boss@gmail.test', 'wakil@gmail.test'])->and($a->relaySenderList())->toBe(['boss@gmail.test'])->and($a->relay_mode)->toBe('direct');
    $this->get('/admin/outreach/accounts')->assertOk()->assertSee('"relay_mode":"direct"', false);
});

it('shows our replies with their source and status, the summary cards, and the contact page', function () {
    [$cc, $a] = prospect(['forward_enabled' => true, 'forward_to' => 'boss@gmail.test', 'relay_enabled' => true, 'relay_senders' => 'boss@gmail.test']);
    app(ReplyProcessor::class)->process($a, prospectReply());
    $token = $cc->fresh()->thread_token;
    app(ReplyProcessor::class)->process($a, relayMail("Re: [#$token]", 'Draf dari email pribadi.'));
    app(ReplyProcessor::class)->process($a, relayMail('Re: tanpa kode', 'Ditolak.'));
    $this->actingAs(adminUser());
    $this->post("/admin/outreach/threads/{$cc->id}/send", ['body' => 'Dari website.']);

    $page = $this->get('/admin/outreach/replies')->assertOk();
    expect($page->viewData('page')['props']['summary'])->toBe(['draft' => 1, 'failed' => 0, 'rejected' => 1]);
    $this->get('/admin/outreach/replies?tab=ours')->assertOk()->assertSee('dari website')->assertSee('dari email pribadi')->assertSee('Draf dari email pribadi.');
    $this->get('/admin/outreach/replies?tab=ours&source=website')->assertOk()->assertSee('Dari website.')->assertDontSee('Draf dari email pribadi.');
    $this->get('/admin/outreach/replies?tab=ours&status=rejected')->assertOk()->assertSee('Ditolak.')->assertDontSee('Dari website.');

    $contactPage = $this->get('/admin/outreach/contacts/'.$cc->contact_id)->assertOk();
    $thread = $contactPage->viewData('page')['props']['threads'][0];
    expect(collect($thread['messages'])->pluck('reply_status')->filter()->values()->all())->toBe(['draft', 'sent'])
        ->and(collect($thread['messages'])->pluck('direction')->all())->toBe(['out', 'in', 'out', 'out']);
    $filtered = $this->get('/admin/outreach/contacts/'.$cc->contact_id.'?status=draft')->viewData('page')['props']['threads'][0]['messages'];
    expect($filtered)->toHaveCount(1);

    $this->actingAs(adminUser('editor', ['email' => 'ed@kita.test']))->get("/admin/outreach/threads/{$cc->id}")->assertForbidden();
});
