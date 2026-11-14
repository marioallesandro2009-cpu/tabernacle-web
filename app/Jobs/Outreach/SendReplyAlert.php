<?php

namespace App\Jobs\Outreach;

use App\Mail\OutreachReplyMail;
use App\Models\LeadRecipient;
use App\Models\Outreach\Message;
use App\Services\Outreach\Digest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Tells the team at once that a person answered a campaign email. It goes to the lead notification
 * addresses; when none is set, to the weekly-summary recipients (or the Super Admins).
 */
class SendReplyAlert implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $messageId) {}

    public function handle(Digest $digest): void
    {
        $reply = Message::with(['contact', 'campaign', 'account'])->find($this->messageId);
        if (! $reply || $reply->direction !== 'in' || $reply->kind !== 'reply') {
            return;
        }
        $emails = self::recipients($digest);
        if (! $emails) {
            Log::warning('Balasan Outreach #'.$reply->id.' masuk, tapi tidak ada email penerima notifikasi.');

            return;
        }
        foreach ($emails as $email) {
            try {
                Mail::to($email)->send(new OutreachReplyMail($reply));
            } catch (\Throwable $e) {
                Log::error('Gagal kirim notifikasi balasan Outreach #'.$reply->id.' ke '.$email.': '.$e->getMessage());
            }
        }
    }

    /** @return array<int, string> */
    public static function recipients(Digest $digest): array
    {
        $leads = LeadRecipient::query()->where('is_active', true)->orderBy('id')->limit(LeadRecipient::MAX)->pluck('email')
            ->map(fn ($e) => mb_strtolower((string) $e))->filter()->unique()->values()->all();

        return $leads ?: $digest->recipients();
    }
}
