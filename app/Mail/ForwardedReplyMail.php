<?php

namespace App\Mail;

use App\Models\Outreach\Message;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;

/**
 * A prospect's reply, forwarded to a personal mailbox. When answering from the personal mailbox is
 * allowed, Reply-To points at the sender account itself, so the answer lands in the inbox the system
 * reads, and the [#TOKEN] in the subject tells it which conversation the answer belongs to.
 */
class ForwardedReplyMail extends Mailable
{
    public function __construct(public Message $reply, public string $token, public ?Message $lastSent = null) {}

    public function envelope(): Envelope
    {
        $c = $this->reply->contact;
        $who = $c ? ($c->name ?: (string) $c->email) : 'prospek';
        $subject = trim((string) $this->reply->subject) ?: '(tanpa subjek)';
        $account = $this->reply->account;

        return new Envelope(
            subject: '[Balasan] '.$subject.' – '.$who.' [#'.$this->token.']',
            replyTo: $account && $account->relay_enabled ? [new Address($account->email, $account->name)] : [],
        );
    }

    public function content(): Content
    {
        $r = $this->reply;
        $last = $this->lastSent;

        return new Content(view: 'mail.outreach-forward', with: [
            'r' => $r, 'contact' => $r->contact, 'campaign' => $r->campaign, 'segment' => $r->contact?->segment?->name,
            'last' => $last, 'lastBody' => $last ? Str::limit(trim((string) $last->body), 1500) : null,
            'relay' => (bool) $r->account?->relay_enabled, 'token' => $this->token,
            'threadUrl' => url('/admin/outreach/threads/'.$r->campaign_contact_id),
        ]);
    }
}
