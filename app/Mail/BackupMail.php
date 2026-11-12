<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The nightly database backup, sent as an attachment so a copy lives outside the server. */
class BackupMail extends Mailable
{
    use Queueable;

    public function __construct(public string $path) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Backup database '.setting('site_name', config('app.name')).' '.now('Asia/Jakarta')->format('d M Y'));
    }

    public function content(): Content
    {
        $kb = number_format((int) @filesize($this->path) / 1024, 0, ',', '.');

        return new Content(htmlString: '<p>Backup database otomatis dari '.e(url('/')).' terlampir ('.$kb.' KB).</p>'
            .'<p>Simpan email ini. Untuk memulihkan, berikan file ini kepada pengelola situs.</p>');
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [Attachment::fromPath($this->path)->as(basename($this->path))->withMime('application/gzip')];
    }
}
