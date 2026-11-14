<?php

namespace App\Mail;

use App\Models\Outreach\Message;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The answer to a reply sent from a personal mailbox: what the system did with it. */
class RelayConfirmationMail extends Mailable
{
    public function __construct(public Message $m, public bool $attachmentsIgnored = false) {}

    /** @return array{0: string, 1: string} [short word for the subject, sentence for the body] */
    public function outcome(): array
    {
        $reason = (string) $this->m->error;

        return match ($this->m->reply_status) {
            'sent' => ['Terkirim', 'Balasan Anda sudah dikirim ke prospek dari akun pengirim, di percakapan yang sama.'],
            'draft' => ['Disimpan sebagai draf', 'Balasan Anda disimpan sebagai draf. Tinjau dan kirim dari website.'],
            'queued' => ['Diterima', 'Balasan Anda sedang dalam antrean kirim.'],
            'failed' => ['Gagal', 'Balasan Anda gagal dikirim: '.$reason.' Buka website untuk mengirim ulang.'],
            'rejected' => ['Ditolak', 'Balasan Anda tidak diteruskan ke prospek: '.$reason],
            default => ['Diproses', 'Balasan Anda sudah diterima sistem.'],
        };
    }

    public function envelope(): Envelope
    {
        $who = $this->m->contact?->name ?: ($this->m->contact?->email ?: 'prospek');

        return new Envelope(subject: '['.$this->outcome()[0].'] Balasan Anda untuk '.$who);
    }

    public function content(): Content
    {
        [$word, $sentence] = $this->outcome();

        return new Content(view: 'mail.outreach-relay-confirmation', with: [
            'word' => $word, 'sentence' => $sentence, 'm' => $this->m, 'attachmentsIgnored' => $this->attachmentsIgnored,
            'threadUrl' => $this->m->campaign_contact_id ? url(admin_path().'/outreach/threads/'.$this->m->campaign_contact_id) : url(admin_path().'/outreach/replies?tab=ours'),
        ]);
    }
}
