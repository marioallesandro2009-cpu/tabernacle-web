<?php

require_once __DIR__.'/../Helpers/OutreachHelpers.php';

use App\Models\Outreach\Account;
use App\Models\Outreach\CampaignContact;
use App\Models\Outreach\Message;
use App\Services\Outreach\CampaignScheduler;
use App\Services\Outreach\CampaignService;
use App\Services\Outreach\ConnectionTester;
use App\Services\Outreach\ImapInbox;
use App\Services\Outreach\InboundMail;
use App\Services\Outreach\OutreachMailer;
use App\Services\Outreach\ReplyPoller;
use App\Services\Outreach\ReplyProcessor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/** An inbox with an INBOX and a spam folder, returning what the test puts in each. */
class FolderInbox extends ImapInbox
{
    /** @var array<int, InboundMail> */
    public array $mails = [];

    /** @var array<string, int> */
    public array $uids = [];

    public function fetch(Account $a, int $limit = 100): array
    {
        return ['mails' => $this->mails, 'last_uid' => $this->uids['INBOX'] ?? 0, 'uids' => $this->uids];
    }
}

beforeEach(function () {
    FakeSmtp::$sent = [];
    FakeSmtp::$fail = null;
    Cache::flush();
    Mail::fake();
    app()->bind(OutreachMailer::class, fn () => new FakeMailer);
    Carbon::setTestNow(Carbon::parse('2026-11-16 09:00', 'Asia/Jakarta'));
});

afterEach(fn () => Carbon::setTestNow());

function spamMail(int $uid, string $from, string $subject, string $body, array $o = []): InboundMail
{
    return new InboundMail(uid: $uid, messageId: "spam$uid@mail.test", inReplyTo: $o['inReplyTo'] ?? null, references: [], fromEmail: $from, fromName: null,
        subject: $subject, date: now(), body: $body, headers: $o['headers'] ?? [], dsn: $o['dsn'] ?? '', folder: $o['folder'] ?? 'INBOX.Spam');
}

it('finds the spam folder by the \Junk flag first, then by its name', function () {
    expect(ImapInbox::junkFolders(['INBOX' => [], 'INBOX.Sent' => ['\Sent'], 'Bulk Mail' => [], 'Spam-Archive' => []]))->toBe(['Bulk Mail'])
        ->and(ImapInbox::junkFolders(['INBOX' => [], 'INBOX.spam' => ['\HasNoChildren'], 'Junk' => ['\Junk']]))->toBe(['Junk'])
        ->and(ImapInbox::junkFolders(['[Gmail]' => ['\Noselect'], '[Gmail]/Spam' => [], 'INBOX.Junk E-mail' => []]))->toBe(['[Gmail]/Spam', 'INBOX.Junk E-mail'])
        ->and(ImapInbox::junkFolders(['INBOX' => [], 'Drafts' => ['\Drafts'], 'Arsip' => []]))->toBe([]);
});

it('handles replies, bounces and out-of-office mail found in Spam like those in the inbox, and remembers each folder', function () {
    $a = oAccount(['imap_folder_uids' => ['INBOX' => 40]]);
    $c = oCampaign([$a], 2);
    oContact('balas@gereja.test');
    oContact('cuti@gereja.test');
    oContact('mati@gereja.test');
    app(CampaignService::class)->start($c);
    for ($i = 0; $i < 4; $i++) {
        app(CampaignScheduler::class)->tick();
        runJobs();
        Carbon::setTestNow(now()->addMinutes(5));
    }
    $root = fn (string $email) => CampaignContact::whereHas('contact', fn ($q) => $q->where('email', $email))->value('root_message_id');

    $inbox = new FolderInbox;
    $inbox->uids = ['INBOX' => 42, 'INBOX.Spam' => 7];
    $inbox->mails = [
        spamMail(5, 'balas@gereja.test', 'Re: Halo', 'Kami tertarik.', ['inReplyTo' => $root('balas@gereja.test')]),
        spamMail(6, 'cuti@gereja.test', 'Automatic reply: Halo', 'Sedang cuti.', ['headers' => ['auto-submitted' => 'auto-replied']]),
        spamMail(7, 'mailer-daemon@mx.test', 'Undelivered', 'x', ['headers' => ['content-type' => 'multipart/report; report-type=delivery-status'],
            'dsn' => "Final-Recipient: rfc822; mati@gereja.test\nAction: failed\nStatus: 5.1.1\nMessage-ID: <".$root('mati@gereja.test').'>']),
        spamMail(41, 'balas@gereja.test', 'Re: Halo', 'Dari inbox.', ['folder' => 'INBOX', 'inReplyTo' => $root('balas@gereja.test')]),
    ];
    $poller = new ReplyPoller($inbox, app(ReplyProcessor::class), app(ConnectionTester::class));
    expect($poller->poll($a))->toBe(4);

    $in = Message::where('direction', 'in')->orderBy('id')->get();
    expect($in->pluck('kind')->all())->toBe(['reply', 'auto_reply', 'bounce', 'reply'])
        ->and($in->pluck('folder')->all())->toBe(['INBOX.Spam', 'INBOX.Spam', 'INBOX.Spam', 'INBOX'])
        ->and(CampaignContact::whereHas('contact', fn ($q) => $q->where('email', 'balas@gereja.test'))->value('status'))->toBe('replied')
        ->and(CampaignContact::whereHas('contact', fn ($q) => $q->where('email', 'mati@gereja.test'))->value('status'))->toBe('bounced')
        ->and($a->fresh()->imap_folder_uids)->toBe(['INBOX' => 42, 'INBOX.Spam' => 7])->and($a->fresh()->imap_last_uid)->toBe(42);

    $this->actingAs(adminUser());
    $page = $this->get('/admin/outreach/replies')->assertOk();
    $items = collect($page->viewData('page')['props']['messages']['data']);
    expect($items->pluck('spam')->all())->toBe([false, true]); // newest first: the inbox one, then the one from Spam
    $this->get('/admin/outreach/replies?id='.$in[0]->id)->assertOk()->assertSee('"folder":"INBOX.Spam"', false);
});

it('accepts an answer from a personal mailbox that landed in Spam', function () {
    $a = oAccount(['email' => 'tim@tabernacle.test', 'forward_enabled' => true, 'forward_to' => 'boss@gmail.test', 'relay_enabled' => true, 'relay_senders' => 'boss@gmail.test', 'relay_mode' => 'review']);
    $c = oCampaign([$a], 1);
    oContact('pdt@gereja.test');
    app(CampaignService::class)->start($c);
    app(CampaignScheduler::class)->tick();
    runJobs();
    $cc = CampaignContact::first();
    $token = $cc->token();

    $m = app(ReplyProcessor::class)->process($a, spamMail(9, 'boss@gmail.test', "Re: [#$token]", 'Jawaban dari HP.', ['headers' => ['authentication-results' => 'mx; dkim=pass header.d=gmail.test']]));
    expect($m->reply_status)->toBe('draft')->and($m->folder)->toBe('INBOX.Spam');
});
