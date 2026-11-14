<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OutreachDigestMail extends Mailable
{
    /** @param array<string, mixed> $digest from App\Services\Outreach\Digest::build() */
    public function __construct(public array $digest) {}

    public function envelope(): Envelope
    {
        $from = $this->digest['from']->copy()->timezone('Asia/Jakarta')->locale('id');
        $to = $this->digest['to']->copy()->timezone('Asia/Jakarta')->locale('id');

        return new Envelope(subject: 'Ringkasan Outreach mingguan: '.$from->isoFormat('D MMM').' – '.$to->isoFormat('D MMM YYYY'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.outreach-digest', with: ['d' => $this->digest, 'adminUrl' => url(admin_path().'/outreach/campaigns'), 'repliesUrl' => url(admin_path().'/outreach/replies')]);
    }
}
