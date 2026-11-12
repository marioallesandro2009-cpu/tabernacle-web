<?php

namespace App\Jobs;

use App\Mail\NewLeadMail;
use App\Models\Lead;
use App\Models\LeadRecipient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Emails every active notification address (up to 10) when a new lead arrives. */
class SendLeadNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $leadId) {}

    public function handle(): void
    {
        $lead = Lead::with('tour')->find($this->leadId);
        if (! $lead) {
            return;
        }
        $emails = LeadRecipient::query()->where('is_active', true)->orderBy('id')->limit(LeadRecipient::MAX)->pluck('email');
        if ($emails->isEmpty()) {
            Log::warning('Lead #'.$lead->id.' masuk, tapi belum ada email penerima notifikasi yang aktif.');

            return;
        }
        foreach ($emails as $email) {
            try {
                Mail::to($email)->send(new NewLeadMail($lead));
            } catch (\Throwable $e) {
                // one bad address must not stop the others
                Log::error('Gagal kirim notifikasi lead #'.$lead->id.' ke '.$email.': '.$e->getMessage());
            }
        }
    }
}
