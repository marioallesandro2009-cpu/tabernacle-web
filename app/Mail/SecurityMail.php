<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Shared shape of the sign-in security emails: a title, a few lines, an optional button. */
abstract class SecurityMail extends Mailable
{
    abstract protected function subjectLine(): string;

    /** @return array{title: string, lines: array<int, string>, rows?: array<string, string>, button?: array{0: string, 1: string}, note?: string} */
    abstract protected function body(): array;

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function content(): Content
    {
        return new Content(view: 'mail.security', with: $this->body());
    }
}
