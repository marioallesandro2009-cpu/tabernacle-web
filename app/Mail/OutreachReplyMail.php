<?php

namespace App\Mail;

use App\Models\Outreach\Message;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** "Someone answered a campaign email": sent the moment the inbox reader files a real reply. */
class OutreachReplyMail extends Mailable
{
    public function __construct(public Message $reply) {}

    public function envelope(): Envelope
    {
        $c = $this->reply->contact;
        $who = $c ? ($c->name ?: ($c->email ?? 'seseorang')) : 'seseorang';

        return new Envelope(subject: 'Balasan baru Outreach: '.$who.($c?->organization ? ' ('.$c->organization.')' : ''));
    }

    public function content(): Content
    {
        $body = trim((string) $this->reply->body);

        return new Content(view: 'mail.outreach-reply', with: [
            'm' => $this->reply,
            'contact' => $this->reply->contact,
            'campaign' => $this->reply->campaign,
            'snippet' => mb_strlen($body) > 800 ? mb_substr($body, 0, 800).'…' : $body,
            'repliesUrl' => url('/admin/outreach/replies'),
        ]);
    }
}
