<?php

namespace App\Jobs\Outreach;

use App\Mail\RelayConfirmationMail;
use App\Models\Outreach\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Tells the personal mailbox what happened to the reply it sent: sent, kept as draft, failed or rejected. */
class SendRelayConfirmation implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $messageId, public bool $attachmentsIgnored = false) {}

    public function handle(): void
    {
        $m = Message::with(['contact', 'campaignContact'])->find($this->messageId);
        if (! $m || ! $m->relay_from) {
            return;
        }
        try {
            Mail::to($m->relay_from)->send(new RelayConfirmationMail($m, $this->attachmentsIgnored));
        } catch (\Throwable $e) {
            Log::error('Konfirmasi relay #'.$m->id.' gagal dikirim ke '.$m->relay_from.': '.$e->getMessage());
        }
    }
}
