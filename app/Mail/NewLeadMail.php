<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NewLeadMail extends Mailable
{
    public function __construct(public Lead $lead) {}

    public function envelope(): Envelope
    {
        $who = $this->lead->name ?: 'Pengunjung';

        return new Envelope(
            subject: 'Lead baru: '.$who.($this->lead->tour_name ? ' | '.$this->lead->tour_name : ''),
            replyTo: $this->lead->email ? [$this->lead->email] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.new-lead', with: ['lead' => $this->lead, 'adminUrl' => url('/admin/leads/'.$this->lead->id)]);
    }
}
