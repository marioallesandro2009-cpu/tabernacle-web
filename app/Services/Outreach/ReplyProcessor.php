<?php

namespace App\Services\Outreach;

use App\Jobs\Outreach\ForwardReply;
use App\Jobs\Outreach\SendReplyAlert;
use App\Models\Outreach\Account;
use App\Models\Outreach\CampaignContact;
use App\Models\Outreach\Contact;
use App\Models\Outreach\Message;
use App\Models\Outreach\Suppression;

/**
 * Files an inbox message under the right campaign contact: a reply stops the follow-ups, a
 * bounce puts the address on the do-not-contact list, an out-of-office is only noted.
 */
class ReplyProcessor
{
    public function __construct(protected ReplyClassifier $classifier = new ReplyClassifier, protected RelayProcessor $relay = new RelayProcessor) {}

    /** @return Message|null null when the message was already seen or has nothing to do with our emails */
    public function process(Account $account, InboundMail $mail): ?Message
    {
        if ($mail->messageId && Message::where('direction', 'in')->where('account_id', $account->id)->where('message_id', $mail->messageId)->exists()) {
            return null; // seen before
        }
        // an answer from one of our own personal mailboxes is never a prospect's reply
        if ($this->relay->isCandidate($account, $mail) && ! $this->classifier->isBounce($mail)) {
            return $this->relay->process($account, $mail);
        }
        $class = $this->classifier->classify($mail);

        return match ($class['kind']) {
            'bounce' => $this->bounce($account, $mail, $class),
            'auto_reply' => $this->autoReply($account, $mail),
            default => $this->reply($account, $mail),
        };
    }

    protected function reply(Account $account, InboundMail $mail): ?Message
    {
        $cc = $this->thread($account, $mail);
        if (! $cc) {
            return null; // not an answer to one of our emails
        }
        if (in_array($cc->status, ['queued', 'sent', 'auto_replied'], true)) {
            $cc->update(['status' => 'replied', 'next_send_at' => null]);
        }

        $message = $this->store($account, $mail, 'reply', $cc, $this->stripQuoted($mail->body));
        SendReplyAlert::dispatch($message->id); // tell the team right away
        if ($account->forwardList()) {
            ForwardReply::dispatch($message->id); // and the personal mailboxes set on this account
        }

        return $message;
    }

    protected function autoReply(Account $account, InboundMail $mail): ?Message
    {
        $cc = $this->thread($account, $mail);
        if (! $cc) {
            return null;
        }
        if ($cc->status === 'sent') {
            $cc->update(['status' => 'auto_replied']); // follow-ups continue: the person is just away
        }

        return $this->store($account, $mail, 'auto_reply', $cc, $this->stripQuoted($mail->body));
    }

    /** @param  array{hard: bool, recipients: array<int, string>}  $class */
    protected function bounce(Account $account, InboundMail $mail, array $class): ?Message
    {
        [$cc, $bounced] = $this->threadFromReport($account, $mail);
        if ($bounced && $bounced->kind === Message::MANUAL && $bounced->reply_status === 'sent') {
            $bounced->moveTo('bounced', mb_substr(trim(preg_replace('/\s+/', ' ', $mail->subject) ?? ''), 0, 200) ?: 'Email ditolak server tujuan');
        }
        $emails = $class['recipients'];
        if ($cc && ! $emails && $cc->contact?->email) {
            $emails = [$cc->contact->email];
        }
        $note = mb_substr(trim(preg_replace('/\s+/', ' ', $mail->subject) ?? ''), 0, 200);
        if ($class['hard']) {
            foreach ($emails as $email) {
                Suppression::add($email, 'bounced', $note ?: 'Email ditolak server tujuan');
            }
        }
        if (! $cc && $emails) {
            $contact = Contact::where('email', $emails[0])->first();
            $cc = $contact ? CampaignContact::where('contact_id', $contact->id)->where('account_id', $account->id)->latest('id')->first() : null;
        }
        if (! $cc && ! $emails) {
            return null; // a delivery report we cannot tie to anyone
        }

        return $this->store($account, $mail, 'bounce', $cc, ($class['hard'] ? '[Permanen] ' : '[Sementara] ').$note, $emails[0] ?? null);
    }

    /** The campaign contact this message answers: by thread headers first, then by who sent it. */
    protected function thread(Account $account, InboundMail $mail): ?CampaignContact
    {
        $ids = array_values(array_filter([InboundMail::cleanId($mail->inReplyTo), ...array_map([InboundMail::class, 'cleanId'], $mail->references)]));
        if ($ids) {
            $out = Message::where('direction', 'out')->whereIn('message_id', $ids)->whereNotNull('campaign_contact_id')->first();
            if ($out) {
                return CampaignContact::with('contact')->find($out->campaign_contact_id);
            }
        }
        $contact = Contact::where('email', mb_strtolower($mail->fromEmail))->first();

        return $contact ? CampaignContact::with('contact')->where('contact_id', $contact->id)->where('account_id', $account->id)->whereNotNull('first_sent_at')->orderByDesc('last_sent_at')->first() : null;
    }

    /**
     * A bounce quotes our original email, so its Message-ID leads back to the right person (and to
     * the exact email that bounced).
     *
     * @return array{0: ?CampaignContact, 1: ?Message}
     */
    protected function threadFromReport(Account $account, InboundMail $mail): array
    {
        preg_match_all('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}@[A-Za-z0-9.\-]+/', $mail->dsn."\n".$mail->body, $m);
        if ($m[0]) {
            $out = Message::where('direction', 'out')->where('account_id', $account->id)->whereIn('message_id', array_unique($m[0]))->whereNotNull('campaign_contact_id')->first();
            if ($out) {
                return [CampaignContact::with('contact')->find($out->campaign_contact_id), $out];
            }
        }

        return [null, null];
    }

    protected function store(Account $account, InboundMail $mail, string $kind, ?CampaignContact $cc, string $body, ?string $forEmail = null): Message
    {
        return Message::create([
            'channel' => 'email', 'campaign_id' => $cc?->campaign_id, 'campaign_contact_id' => $cc?->id, 'contact_id' => $cc?->contact_id,
            'account_id' => $account->id, 'direction' => 'in', 'kind' => $kind, 'subject' => mb_substr($mail->subject, 0, 500),
            'body' => mb_substr($body, 0, 20000), 'message_id' => $mail->messageId, 'in_reply_to' => $mail->inReplyTo,
            'references' => $mail->references ? implode(' ', $mail->references) : null, 'status' => 'received',
            'sent_at' => $mail->date ?? now(), 'imap_uid' => $mail->uid, 'folder' => $mail->folder, 'error' => $forEmail,
        ]);
    }

    /** Keeps what the person wrote and drops the quoted copy of our own email below it. */
    public function stripQuoted(string $body): string
    {
        $lines = preg_split('/\R/', trim($body)) ?: [];
        $out = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*(On .{2,200} wrote:|Pada .{2,200} menulis:|-{2,}\s*(Original Message|Pesan Asli|Forwarded)|_{5,}|From:\s.+|Dari:\s.+)\s*$/i', $line)) {
                break;
            }
            if (str_starts_with(ltrim($line), '>')) {
                continue;
            }
            $out[] = rtrim($line);
        }

        return trim(implode("\n", $out)) ?: trim($body);
    }
}
