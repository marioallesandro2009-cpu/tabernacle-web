<?php

require_once __DIR__.'/../Helpers/OutreachHelpers.php';

use App\Jobs\SendLeadNotification;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\Outreach\Account;
use App\Models\Outreach\Campaign;
use App\Models\Outreach\CampaignContact;
use App\Models\Outreach\Contact;
use App\Models\Outreach\Message;
use App\Models\Outreach\Suppression;
use App\Services\Outreach\CampaignScheduler;
use App\Services\Outreach\CampaignService;
use App\Services\Outreach\ImapInbox;
use App\Services\Outreach\InboundMail;
use App\Services\Outreach\OutreachMailer;
use App\Services\Outreach\ReplyClassifier;
use App\Services\Outreach\ReplyPoller;
use App\Services\Outreach\ReplyProcessor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

/** An inbox that returns what the test puts in it, or fails on demand. */
class FakeInbox extends ImapInbox
{
    /** @var array<int, InboundMail> */
    public array $mails = [];

    public int $lastUid = 0;

    public ?Throwable $fail = null;

    public function fetch(Account $a, int $limit = 100): array
    {
        if ($this->fail) {
            throw $this->fail;
        }

        return ['mails' => $this->mails, 'last_uid' => $this->lastUid];
    }
}

function inbound(array $o = []): InboundMail
{
    static $uid = 100;
    $uid++;

    return new InboundMail(
        uid: $o['uid'] ?? $uid, messageId: $o['messageId'] ?? "in$uid@mail.test", inReplyTo: $o['inReplyTo'] ?? null, references: $o['references'] ?? [],
        fromEmail: $o['from'] ?? 'orang@gereja.test', fromName: null, subject: $o['subject'] ?? 'Re: Halo', date: Carbon::now(),
        body: $o['body'] ?? 'Terima kasih, kami tertarik.', headers: $o['headers'] ?? [], dsn: $o['dsn'] ?? '',
    );
}

beforeEach(function () {
    FakeSmtp::$sent = [];
    FakeSmtp::$fail = null;
    Cache::flush();
    app()->bind(OutreachMailer::class, fn () => new FakeMailer);
    Carbon::setTestNow(Carbon::parse('2026-11-16 09:00', 'Asia/Jakarta'));
});

afterEach(fn () => Carbon::setTestNow());

/** A running campaign where the first email to $email has already been sent. @return array{0: Campaign, 1: Contact, 2: CampaignContact, 3: Account, 4: string} */
function sentTo(string $email = 'orang@gereja.test', int $steps = 2): array
{
    $account = oAccount();
    $c = oCampaign([$account], $steps);
    $contact = oContact($email);
    app(CampaignService::class)->start($c);
    app(CampaignScheduler::class)->tick();
    runJobs();
    $cc = CampaignContact::where('contact_id', $contact->id)->first();

    return [$c, $contact, $cc, $account, (string) $cc->root_message_id];
}

// --- what is this message? ---------------------------------------------------------------

it('tells a reply, an out-of-office and a bounce apart', function () {
    $k = new ReplyClassifier;
    $kind = fn (array $o) => $k->classify(inbound($o))['kind'];

    expect($kind(['subject' => 'Re: Tur Turki', 'body' => 'Boleh kirim proposalnya?']))->toBe('reply')
        ->and($kind(['subject' => 'Re: Tur', 'headers' => ['auto-submitted' => 'auto-replied']]))->toBe('auto_reply')
        ->and($kind(['subject' => 'Re: Tur', 'headers' => ['x-autoreply' => 'yes']]))->toBe('auto_reply')
        ->and($kind(['subject' => 'Out of Office: Re: Tur']))->toBe('auto_reply')
        ->and($kind(['subject' => 'Balasan otomatis: sedang cuti']))->toBe('auto_reply')
        ->and($kind(['subject' => 'Re: Tur', 'headers' => ['auto-submitted' => 'no']]))->toBe('reply')
        ->and($kind(['from' => 'MAILER-DAEMON@mail.test', 'subject' => 'Delivery Status Notification (Failure)']))->toBe('bounce')
        ->and($kind(['from' => 'noreply@x.test', 'headers' => ['content-type' => 'multipart/report; report-type=delivery-status; boundary="b"']]))->toBe('bounce');

    $hard = $k->classify(inbound(['from' => 'mailer-daemon@x.test', 'dsn' => "Final-Recipient: rfc822; Mati@Gereja.test\nAction: failed\nStatus: 5.1.1\nDiagnostic-Code: smtp; 550 5.1.1 User unknown"]));
    $soft = $k->classify(inbound(['from' => 'mailer-daemon@x.test', 'dsn' => "Final-Recipient: rfc822; penuh@gereja.test\nAction: delayed\nStatus: 4.2.2"]));
    $plain = $k->classify(inbound(['from' => 'postmaster@x.test', 'body' => 'The mailbox does not exist']));
    expect($hard)->toMatchArray(['kind' => 'bounce', 'hard' => true, 'recipients' => ['mati@gereja.test']])->and($soft['hard'])->toBeFalse()->and($plain['hard'])->toBeTrue();
});

it('keeps what the person wrote and drops the quoted copy of our email', function () {
    $p = new ReplyProcessor;
    expect($p->stripQuoted("Tertarik, kirim proposal ya.\n\nSalam,\nBudi\n\nOn Mon, 16 Nov 2026 at 09:01, Tim <t@x.test> wrote:\n> Halo\n> Salam"))->toBe("Tertarik, kirim proposal ya.\n\nSalam,\nBudi")
        ->and($p->stripQuoted("Siap.\n> kutipan lama\nTerima kasih"))->toBe("Siap.\nTerima kasih");
});

// --- filing it under the right person ----------------------------------------------------

it('files a reply under the thread, stops the follow-ups, and ignores duplicates and strangers', function () {
    [$c, $contact, $cc, $account, $root] = sentTo();
    $processor = app(ReplyProcessor::class);

    $reply = $processor->process($account, inbound(['inReplyTo' => "<$root>", 'references' => ["<$root>"], 'body' => "Tertarik!\n\nOn Mon wrote:\n> Halo"]));
    expect($reply)->not->toBeNull()->and($reply->direction)->toBe('in')->and($reply->kind)->toBe('reply')->and($reply->body)->toBe('Tertarik!')
        ->and($reply->contact_id)->toBe($contact->id)->and($reply->campaign_id)->toBe($c->id)
        ->and($cc->fresh()->status)->toBe('replied')->and($cc->fresh()->next_send_at)->toBeNull();

    expect($processor->process($account, inbound(['messageId' => $reply->message_id, 'inReplyTo' => "<$root>"])))->toBeNull()   // the same email seen again
        ->and($processor->process($account, inbound(['from' => 'asing@lain.test'])))->toBeNull()                                  // nobody we wrote to
        ->and(Message::where('direction', 'in')->count())->toBe(1);

    // the follow-up on day 4 is never sent
    Carbon::setTestNow(Carbon::parse('2026-11-20 10:00', 'Asia/Jakarta'));
    app(CampaignScheduler::class)->tick();
    runJobs();
    expect(FakeSmtp::$sent)->toHaveCount(1);
});

it('finds the person by their address when the reply has no thread headers', function () {
    [, $contact, $cc, $account] = sentTo('budi@gereja.test');

    $m = app(ReplyProcessor::class)->process($account, inbound(['from' => 'Budi@Gereja.test']));
    expect($m?->contact_id)->toBe($contact->id)->and($cc->fresh()->status)->toBe('replied');
});

it('notes an out-of-office but keeps the follow-ups going; a later real reply then stops them', function () {
    [, , $cc, $account, $root] = sentTo();
    $processor = app(ReplyProcessor::class);

    $auto = $processor->process($account, inbound(['inReplyTo' => "<$root>", 'subject' => 'Out of Office', 'headers' => ['auto-submitted' => 'auto-replied']]));
    expect($auto->kind)->toBe('auto_reply')->and($cc->fresh()->status)->toBe('auto_replied');

    Carbon::setTestNow(Carbon::parse('2026-11-20 10:00', 'Asia/Jakarta'));
    app(CampaignScheduler::class)->tick();
    runJobs();
    expect(FakeSmtp::$sent)->toHaveCount(2)->and($cc->fresh()->current_step)->toBe(2)->and($cc->fresh()->status)->toBe('auto_replied');

    $processor->process($account, inbound(['inReplyTo' => "<$root>"]));
    expect($cc->fresh()->status)->toBe('replied');
});

it('turns a permanent bounce into a do-not-contact entry, and treats a temporary one as a note only', function () {
    [, $contact, $cc, $account, $root] = sentTo('mati@gereja.test');
    $dsn = "Final-Recipient: rfc822; mati@gereja.test\nAction: failed\nStatus: 5.1.1\n\nMessage-ID: <$root>\nSubject: Halo Mati";

    $m = app(ReplyProcessor::class)->process($account, inbound(['from' => 'mailer-daemon@mail.test', 'subject' => 'Undelivered Mail Returned to Sender', 'dsn' => $dsn]));
    expect($m->kind)->toBe('bounce')->and($m->campaign_contact_id)->toBe($cc->id)->and($cc->fresh()->status)->toBe('bounced')->and($cc->fresh()->next_send_at)->toBeNull()
        ->and(Suppression::where('email', 'mati@gereja.test')->value('reason'))->toBe('bounced')->and($contact->fresh()->suppressed_reason)->toBe('bounced');

    [, $c2, $cc2, $account2, $root2] = sentTo('penuh@gereja.test');
    app(ReplyProcessor::class)->process($account2, inbound(['from' => 'mailer-daemon@mail.test', 'dsn' => "Final-Recipient: rfc822; penuh@gereja.test\nAction: delayed\nStatus: 4.2.2\nMessage-ID: <$root2>"]));
    expect(Suppression::where('email', 'penuh@gereja.test')->exists())->toBeFalse()->and($cc2->fresh()->status)->toBe('sent')
        ->and(Message::where('kind', 'bounce')->count())->toBe(2);
});

// --- reading the inboxes -----------------------------------------------------------------

it('reads the inbox of every active account, remembers where it stopped, and reports a broken one', function () {
    [, , $cc, $account, $root] = sentTo();
    $inactive = oAccount(['is_active' => false]);
    $noImap = oAccount(['imap_host' => null]);
    $fake = new FakeInbox;
    $fake->mails = [inbound(['uid' => 55, 'inReplyTo' => "<$root>"]), inbound(['uid' => 56, 'from' => 'asing@lain.test'])];
    $fake->lastUid = 56;
    $poller = new ReplyPoller($fake);

    $r = $poller->pollAll();
    expect($r)->toBe(['accounts' => 1, 'stored' => 1, 'errors' => 0])->and($account->fresh()->imap_last_uid)->toBe(56)->and($account->fresh()->imap_ok)->toBeTrue()
        ->and($account->fresh()->imap_polled_at)->not->toBeNull()->and($cc->fresh()->status)->toBe('replied')
        ->and($inactive->fresh()->imap_polled_at)->toBeNull()->and($noImap->fresh()->imap_polled_at)->toBeNull();

    $fake->fail = new RuntimeException('Authentication failed (AUTHENTICATIONFAILED)');
    expect($poller->pollAll())->toBe(['accounts' => 1, 'stored' => 0, 'errors' => 1]);
    $account->refresh();
    expect($account->imap_ok)->toBeFalse()->and($account->imap_error)->toContain('Login ditolak')->and($account->imap_last_uid)->toBe(56);
});

it('parses mail headers, including folded ones', function () {
    $h = (new ImapInbox)->parseHeaders("Subject: Re: Halo\r\nAuto-Submitted: auto-replied\r\nContent-Type: multipart/report;\r\n report-type=delivery-status;\r\n boundary=\"x\"\r\nX-Failed-Recipients: a@b.test\r\n");
    expect($h['auto-submitted'])->toBe('auto-replied')->and($h['content-type'])->toContain('report-type=delivery-status')->and($h['x-failed-recipients'])->toBe('a@b.test');
});

// --- the Replies page --------------------------------------------------------------------

it('lists replies, filters by label, and keeps bounces and out-of-offices on their own tabs', function () {
    $this->actingAs(adminUser());
    [, $contact, , $account, $root] = sentTo('budi@gereja.test');
    $p = app(ReplyProcessor::class);
    $p->process($account, inbound(['from' => 'budi@gereja.test', 'inReplyTo' => "<$root>", 'body' => 'Kami tertarik, mohon proposal.']));
    $p->process($account, inbound(['from' => 'budi@gereja.test', 'inReplyTo' => "<$root>", 'subject' => 'Out of Office', 'headers' => ['auto-submitted' => 'auto-replied']]));

    $this->get('/admin/outreach/replies')->assertOk()->assertInertia(fn ($page) => $page->component('Admin/Outreach/Replies')
        ->has('messages.data', 1)->where('messages.data.0.name', $contact->name)->where('selected.body', 'Kami tertarik, mohon proposal.')
        ->where('counts.reply', 1)->where('counts.auto', 1)->where('counts.unlabeled', 1)->has('selected.thread', 2));
    $this->get('/admin/outreach/replies?tab=auto')->assertInertia(fn ($page) => $page->has('messages.data', 1));
    $this->get('/admin/outreach/replies?label=interested')->assertInertia(fn ($page) => $page->has('messages.data', 0));
});

it('labels a reply; "not interested" also puts the person on the do-not-contact list', function () {
    $this->actingAs(adminUser());
    [, $contact, , $account, $root] = sentTo('budi@gereja.test');
    $m = app(ReplyProcessor::class)->process($account, inbound(['from' => 'budi@gereja.test', 'inReplyTo' => "<$root>"]));

    $this->post('/admin/outreach/replies/'.$m->id.'/label', ['label' => 'interested'])->assertRedirect();
    expect($m->fresh()->label)->toBe('interested')->and($contact->fresh()->suppressed_at)->toBeNull();
    $this->post('/admin/outreach/replies/'.$m->id.'/label', ['label' => 'bukan-label'])->assertSessionHasErrors('label');
    $this->post('/admin/outreach/replies/'.$m->id.'/label', ['label' => 'not_interested'])->assertRedirect();
    expect($m->fresh()->label)->toBe('not_interested')->and($contact->fresh()->suppressed_reason)->toBe('manual')->and(Suppression::where('email', 'budi@gereja.test')->exists())->toBeTrue();
    $this->post('/admin/outreach/replies/'.$m->id.'/label', ['label' => null])->assertRedirect();
    expect($m->fresh()->label)->toBeNull();
});

it('turns a reply into one lead in the Leads module, only once, and notifies the lead recipients', function () {
    Queue::fake([SendLeadNotification::class]);
    $this->actingAs(adminUser());
    [$c, $contact, , $account, $root] = sentTo('budi@gereja.test');
    $contact->update(['name' => 'Pdt. Budi', 'phone' => '0812345678', 'organization' => 'GKI Sejahtera', 'title' => 'Ketua']);
    $m = app(ReplyProcessor::class)->process($account, inbound(['from' => 'budi@gereja.test', 'inReplyTo' => "<$root>", 'body' => 'Kami tertarik untuk 40 orang.']));

    $this->post('/admin/outreach/replies/'.$m->id.'/lead')->assertRedirect();
    $lead = Lead::first();
    expect(Lead::count())->toBe(1)->and($lead->source)->toBe('outreach')->and($lead->status)->toBe('baru')->and($lead->name)->toBe('Pdt. Budi')->and($lead->email)->toBe('budi@gereja.test')
        ->and($lead->phone)->toBe('0812345678')->and($lead->message)->toBe('Kami tertarik untuk 40 orang.')->and($lead->notes)->toContain('Tur Turki')->toContain('GKI Sejahtera')
        ->and($m->fresh()->lead_id)->toBe($lead->id)->and($m->fresh()->label)->toBe('interested')->and(LeadEvent::where('lead_id', $lead->id)->count())->toBe(1);
    Queue::assertPushed(SendLeadNotification::class, 1);

    $this->post('/admin/outreach/replies/'.$m->id.'/lead')->assertRedirect('/admin/leads/'.$lead->id);
    expect(Lead::count())->toBe(1);
    Queue::assertPushed(SendLeadNotification::class, 1);
});

it('"check now" reads the inboxes at once, and cannot be hammered', function () {
    $this->actingAs(adminUser());
    [, , $cc, $account, $root] = sentTo();
    $fake = new FakeInbox;
    $fake->mails = [inbound(['inReplyTo' => "<$root>"])];
    $fake->lastUid = 120;
    app()->instance(ImapInbox::class, $fake);

    $this->post('/admin/outreach/replies/poll')->assertRedirect()->assertSessionHas('success');
    expect($cc->fresh()->status)->toBe('replied')->and($account->fresh()->imap_last_uid)->toBe(120);
    $this->post('/admin/outreach/replies/poll')->assertSessionHas('error');
});
